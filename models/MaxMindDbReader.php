<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumAnalytics\Models;

/**
 * A minimal reader for the MaxMind DB binary format (used by GeoLite2), just
 * enough to resolve an IP to a value in the data section. Format spec:
 * https://maxmind.github.io/MaxMind-DB/
 *
 * Not a general-purpose client — no caching beyond one load, no write
 * support, and only the data types GeoLite2-Country actually uses (map,
 * array, string, uint16/32, boolean) are decoded. Anything unexpected or
 * out of range fails to null rather than throwing, so a bad byte offset
 * never becomes a fatal error on a live page.
 */
class MaxMindDbReader
{
    private const METADATA_MARKER = "\xAB\xCD\xEFMaxMind.com";
    private const DATA_SECTION_SEPARATOR_BYTES = 16;

    private string $data;
    private int $nodeCount = 0;
    private int $recordSize = 0;
    private int $ipVersion = 4;
    private int $nodeByteSize = 0;
    // Defaults let readMetadata() run decodeValueAt() before these are known
    // from the metadata itself - harmless in practice, since the metadata map
    // never contains pointers, but avoids a hard failure if it ever did.
    private int $searchTreeSize = 0;

    /**
     * @throws \RuntimeException if the file can't be read or parsed
     */
    public function __construct(string $path)
    {
        $contents = @\file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException("Cannot read MaxMind DB at {$path}");
        }
        $this->data = $contents;

        $metadata = $this->readMetadata();

        $this->nodeCount = (int)($metadata['node_count'] ?? 0);
        $this->recordSize = (int)($metadata['record_size'] ?? 0);
        $this->ipVersion = (int)($metadata['ip_version'] ?? 4);

        $validRecordSize = \in_array($this->recordSize, [24, 28, 32], true);
        if ($this->nodeCount <= 0 || !$validRecordSize) {
            throw new \RuntimeException('Unreadable or unsupported MaxMind DB metadata');
        }

        $this->nodeByteSize = (int)($this->recordSize / 4);
        $this->searchTreeSize = $this->nodeCount * $this->nodeByteSize;
    }

    /**
     * @return mixed The decoded data-section value for this IP, or null if
     *               the address isn't in the database
     */
    public function lookup(string $ip): mixed
    {
        $addressBytes = $this->addressToBytes($ip);
        if ($addressBytes === null) return null;

        $node = 0;
        $totalBits = \strlen($addressBytes) * 8;

        for ($bitIndex = 0; $bitIndex < $totalBits; $bitIndex++) {
            $isLeaf = $node >= $this->nodeCount;
            if ($isLeaf) break;

            $byte = \ord($addressBytes[\intdiv($bitIndex, 8)]);
            $bit = ($byte >> (7 - ($bitIndex % 8))) & 1;

            $node = $this->readRecord($node, $bit);
        }

        $noData = $node === $this->nodeCount;
        if ($noData) return null;

        $notFound = $node < $this->nodeCount;
        if ($notFound) return null; // Loop exhausted without reaching a leaf — malformed lookup

        $dataOffset = $this->searchTreeSize + ($node - $this->nodeCount);

        return $this->decodeValueAt($dataOffset)['value'] ?? null;
    }

    /**
     * Converts an IP string to the byte string the tree is keyed on. GeoLite2
     * databases are ip_version 6 and store IPv4 addresses under the ::/96
     * prefix - a bare 4-byte address is padded to 16 bytes with leading
     * zeroes and walked as if it were IPv6.
     */
    private function addressToBytes(string $ip): ?string
    {
        $packed = @\inet_pton($ip);
        if ($packed === false) return null;

        $isIpv4 = \strlen($packed) === 4;
        $isIpv6 = \strlen($packed) === 16;

        if ($isIpv4 && $this->ipVersion === 6) {
            return \str_repeat("\x00", 12) . $packed;
        }

        if ($isIpv4 && $this->ipVersion === 4) return $packed;
        if ($isIpv6 && $this->ipVersion === 6) return $packed;

        return null; // IPv6 address against an IPv4-only database, or vice versa
    }

    /**
     * Reads one record (left if $bit is 0, right if 1) from the node at
     * $nodeIndex. Record layout depends on record_size — see the format spec
     * for why 28-bit records split a shared middle byte across both halves.
     */
    private function readRecord(int $nodeIndex, int $bit): int
    {
        $offset = $nodeIndex * $this->nodeByteSize;

        if ($this->recordSize === 24) {
            return $bit === 0
                ? $this->readUint($offset, 3)
                : $this->readUint($offset + 3, 3);
        }

        if ($this->recordSize === 32) {
            return $bit === 0
                ? $this->readUint($offset, 4)
                : $this->readUint($offset + 4, 4);
        }

        // 28-bit: 7 bytes total, middle byte's two nibbles split between records
        $b0 = \ord($this->data[$offset]);
        $b1 = \ord($this->data[$offset + 1]);
        $b2 = \ord($this->data[$offset + 2]);
        $b3 = \ord($this->data[$offset + 3]);
        $b4 = \ord($this->data[$offset + 4]);
        $b5 = \ord($this->data[$offset + 5]);
        $b6 = \ord($this->data[$offset + 6]);

        if ($bit === 0) {
            return ($b0 << 20) | ($b1 << 12) | ($b2 << 4) | ($b3 >> 4);
        }

        return (($b3 & 0x0F) << 24) | ($b4 << 16) | ($b5 << 8) | $b6;
    }

    private function readUint(int $offset, int $numBytes): int
    {
        $value = 0;
        for ($i = 0; $i < $numBytes; $i++) {
            $value = ($value << 8) | \ord($this->data[$offset + $i]);
        }
        return $value;
    }

    /**
     * Decodes one self-describing data-section value at an absolute file
     * offset. Returns ['value' => mixed, 'next' => int] so callers reading a
     * sequence (map entries, array items) know where the next value starts.
     */
    private function decodeValueAt(int $offset): array
    {
        $controlByte = \ord($this->data[$offset]);
        $type = $controlByte >> 5;
        $offset++;

        $isPointer = $type === 1;
        if ($isPointer) {
            return $this->decodePointer($controlByte, $offset);
        }

        $isExtendedType = $type === 0;
        if ($isExtendedType) {
            $extendedTypeByte = \ord($this->data[$offset]);
            $offset++;
            $type = 7 + $extendedTypeByte;
        }

        [$size, $offset] = $this->decodeSize($controlByte, $offset);

        return match ($type) {
            2 => $this->decodeString($offset, $size),
            4 => ['value' => \substr($this->data, $offset, $size), 'next' => $offset + $size],
            5 => ['value' => $this->readUint($offset, $size), 'next' => $offset + $size], // uint16
            6 => ['value' => $this->readUint($offset, $size), 'next' => $offset + $size], // uint32
            7 => $this->decodeMap($offset, $size),
            8 => ['value' => $this->readUint($offset, $size), 'next' => $offset + $size], // int32 (unsigned read is fine for our use)
            9 => ['value' => $this->readUint($offset, $size), 'next' => $offset + $size], // uint64
            11 => $this->decodeArray($offset, $size),
            14 => ['value' => $size === 1, 'next' => $offset], // boolean — value is the size bits themselves, no payload
            default => ['value' => null, 'next' => $offset + $size], // double/float/uint128/etc — skip, not needed for country lookup
        };
    }

    private function decodeSize(int $controlByte, int $offset): array
    {
        $size = $controlByte & 0x1F;

        if ($size < 29) return [$size, $offset];

        if ($size === 29) {
            $size = 29 + \ord($this->data[$offset]);
            return [$size, $offset + 1];
        }

        if ($size === 30) {
            $size = 285 + $this->readUint($offset, 2);
            return [$size, $offset + 2];
        }

        $size = 65821 + $this->readUint($offset, 3);
        return [$size, $offset + 3];
    }

    private function decodeString(int $offset, int $size): array
    {
        return [
            'value' => \substr($this->data, $offset, $size),
            'next' => $offset + $size,
        ];
    }

    private function decodeMap(int $offset, int $pairCount): array
    {
        $map = [];

        for ($i = 0; $i < $pairCount; $i++) {
            $key = $this->decodeValueAt($offset);
            $value = $this->decodeValueAt($key['next']);

            $map[(string)$key['value']] = $value['value'];
            $offset = $value['next'];
        }

        return ['value' => $map, 'next' => $offset];
    }

    private function decodeArray(int $offset, int $itemCount): array
    {
        $items = [];

        for ($i = 0; $i < $itemCount; $i++) {
            $item = $this->decodeValueAt($offset);
            $items[] = $item['value'];
            $offset = $item['next'];
        }

        return ['value' => $items, 'next' => $offset];
    }

    /**
     * Pointers inside the data section (used for string/value interning) are
     * a different encoding to search-tree records: the size bits select how
     * many extra bytes carry the value, each size tier has a fixed additive
     * offset, and the resolved value is an offset from the start of the data
     * section rather than the whole file.
     */
    private function decodePointer(int $controlByte, int $offset): array
    {
        $sizeFlag = ($controlByte >> 3) & 0x3;
        $dataSectionStart = $this->searchTreeSize + self::DATA_SECTION_SEPARATOR_BYTES;

        if ($sizeFlag === 0) {
            $value = (($controlByte & 0x7) << 8) | \ord($this->data[$offset]);
            $pointer = $value;
            $next = $offset + 1;
        } elseif ($sizeFlag === 1) {
            $value = (($controlByte & 0x7) << 16) | $this->readUint($offset, 2);
            $pointer = $value + 2048;
            $next = $offset + 2;
        } elseif ($sizeFlag === 2) {
            $value = (($controlByte & 0x7) << 24) | $this->readUint($offset, 3);
            $pointer = $value + 526336;
            $next = $offset + 3;
        } else {
            $pointer = $this->readUint($offset, 4);
            $next = $offset + 4;
        }

        $resolved = $this->decodeValueAt($dataSectionStart + $pointer);

        return ['value' => $resolved['value'], 'next' => $next];
    }

    private function readMetadata(): array
    {
        $markerPos = \strrpos($this->data, self::METADATA_MARKER);
        if ($markerPos === false) {
            throw new \RuntimeException('MaxMind DB metadata marker not found');
        }

        $metadataOffset = $markerPos + \strlen(self::METADATA_MARKER);
        $decoded = $this->decodeValueAt($metadataOffset);

        return \is_array($decoded['value']) ? $decoded['value'] : [];
    }
}
