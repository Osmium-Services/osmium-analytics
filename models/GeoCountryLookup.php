<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumAnalytics\Models;

/**
 * Resolves a visitor's IP address to a two-letter country code, entirely
 * offline - no third-party API call, so no visitor IP ever leaves this
 * server. The IP itself is never stored; only the resulting country code
 * and a count are (see PageHitCountry).
 *
 * Needs a local MaxMind GeoLite2-Country.mmdb file, which is not bundled
 * with the repo (free to obtain, but requires a personal MaxMind account -
 * see GEOIP.md in the service). Until that file is in place, lookup()
 * returns null and country breakdown simply stays empty; nothing else here
 * is affected.
 */
class GeoCountryLookup
{
    private const DB_PATH = 'app/data/geoip/GeoLite2-Country.mmdb';

    private static ?MaxMindDbReader $reader = null;
    private static bool $readerLoadAttempted = false;

    public function isAvailable(): bool
    {
        return \file_exists(self::DB_PATH);
    }

    /**
     * @return string|null Two-letter ISO country code, or null if unresolvable
     *                      (private/reserved IP, database missing, or lookup failure)
     */
    public function lookup(string $ip): ?string
    {
        $isPublicIp = \filter_var(
            $ip,
            \FILTER_VALIDATE_IP,
            \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE
        ) !== false;
        if (!$isPublicIp) return null; // Local/dev requests have no meaningful country

        $reader = $this->getReader();
        if ($reader === null) return null;

        try {
            $record = $reader->lookup($ip);
        } catch (\Throwable $e) {
            \error_log('GeoCountryLookup: lookup failed - ' . $e->getMessage());
            return null;
        }

        $isoCode = $record['country']['iso_code'] ?? $record['registered_country']['iso_code'] ?? null;

        return \is_string($isoCode) ? $isoCode : null;
    }

    /**
     * One reader instance per request - the file is a few MB, no reason to
     * re-parse its metadata on every call within the same request.
     */
    private function getReader(): ?MaxMindDbReader
    {
        if (self::$readerLoadAttempted) return self::$reader;

        self::$readerLoadAttempted = true;

        $unavailable = !$this->isAvailable();
        if ($unavailable) return null;

        try {
            self::$reader = new MaxMindDbReader(self::DB_PATH);
        } catch (\Throwable $e) {
            \error_log('GeoCountryLookup: failed to load database - ' . $e->getMessage());
            self::$reader = null;
        }

        return self::$reader;
    }
}
