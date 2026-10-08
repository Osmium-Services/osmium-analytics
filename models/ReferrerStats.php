<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumAnalytics\Models;

use Osmium\Core\Library\OsmiumPDO;

/**
 * Read-only referrer figures for the Top Referrers page (last 30 days).
 */
class ReferrerStats extends PageHitBase
{
    public function __construct(OsmiumPDO $database)
    {
        parent::__construct($database, 'page_hits');
    }

    public function topList(): array
    {
        return $this->database->prepare($this->loadSqlFile('referrer-top-list.sql'))->resultset();
    }

    public function dailyTotals(): array
    {
        return $this->database->prepare($this->loadSqlFile('referrer-daily-totals.sql'))->resultset();
    }

    /** @return array{totalHits: int, totalVisitors: int, uniqueCount: int, directTraffic: int} */
    public function summary(): array
    {
        return [
            'totalHits' => $this->total('referrer-total-hits.sql'),
            'totalVisitors' => $this->total('referrer-total-visitors.sql'),
            'uniqueCount' => $this->total('referrer-unique-count.sql'),
            'directTraffic' => $this->total('referrer-direct-traffic.sql'),
        ];
    }

    private function total(string $file): int
    {
        return (int) ($this->database->prepare($this->loadSqlFile($file))->single()['total'] ?? 0);
    }
}
