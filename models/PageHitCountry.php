<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumAnalytics\Models;

use Osmium\Core\Library\OsmiumPDO;

class PageHitCountry extends PageHitBase
{
    public function __construct(OsmiumPDO $database)
    {
        parent::__construct($database, 'page_hit_countries');
    }

    /**
     * Record a hit for a country, upserting today's count.
     *
     * @param string $countryCode ISO 3166-1 alpha-2, e.g. "GB"
     */
    public function record(string $countryCode): void
    {
        $sql = $this->loadSqlFile('pagehit-country-record.sql');

        $this->database->prepare(sql: $sql, bindings: [
            ':country_code' => \mb_strtoupper($countryCode),
        ])->execute();
    }

    /**
     * Country breakdown for the last 30 days, most-hit first.
     */
    public function breakdown(): array
    {
        $sql = $this->loadSqlFile('pagehit-country-breakdown.sql');

        return $this->database->prepare($sql)->resultset();
    }

    public function cleanupOldRecords(int $days): int
    {
        $sql = $this->loadSqlFile('pagehit-country-cleanup-old.sql');
        $this->database->prepare($sql, [':days' => $days])->execute();

        return $this->database->rowCount();
    }
}
