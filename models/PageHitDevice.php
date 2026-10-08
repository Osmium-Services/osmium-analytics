<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumAnalytics\Models;

use Osmium\Core\Library\OsmiumPDO;

class PageHitDevice extends PageHitBase
{
    private const VALID_TYPES = ['desktop', 'mobile', 'tablet', 'other'];

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct($database, 'page_hit_devices');
    }

    /**
     * Record a hit for a device type, upserting today's count.
     */
    public function record(string $deviceType): void
    {
        $isValid = \in_array(needle: $deviceType, haystack: self::VALID_TYPES);
        $type = $isValid ? $deviceType : 'other';

        $sql = $this->loadSqlFile('pagehit-device-record.sql');

        $this->database->prepare(sql: $sql, bindings: [
            ':device_type' => $type,
        ])->execute();
    }

    /**
     * Device type breakdown for the last 30 days, most-hit first.
     */
    public function breakdown(): array
    {
        $sql = $this->loadSqlFile('pagehit-device-breakdown.sql');

        return $this->database->prepare($sql)->resultset();
    }

    public function cleanupOldRecords(int $days): int
    {
        $sql = $this->loadSqlFile('pagehit-device-cleanup-old.sql');
        $this->database->prepare($sql, [':days' => $days])->execute();

        return $this->database->rowCount();
    }
}
