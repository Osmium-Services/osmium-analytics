<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumAnalytics\Models;

use Osmium\Core\Library\OsmiumPDO;

class PageHit extends PageHitBase
{
    public function __construct(OsmiumPDO $database)
    {
        parent::__construct($database, 'page_hits');
    }

    /**
     * Record a page hit using normalized FK references.
     *
     * @param int $pageUrlId The URL lookup ID from PageHitUrl
     * @param int|null $referrerId The referrer lookup ID from PageHitReferrer (null for direct)
     * @param bool $isUniqueVisitor Whether this is a unique visitor for this page+date+referrer
     */
    public function record(int $pageUrlId, ?int $referrerId, bool $isUniqueVisitor): void
    {
        $uniqueIncrement = $isUniqueVisitor ? 1 : 0;
        $sql = $this->loadSqlFile('pagehit-record.sql');

        $this->database->prepare(sql: $sql, bindings: [
            ':page_url_id' => $pageUrlId,
            ':referrer_id' => $referrerId,
            ':unique_increment' => $uniqueIncrement,
            ':unique_increment_update' => $uniqueIncrement,
        ])->execute();
    }

    /**
     * Get stats summary for the last 30 days.
     */
    public function statsSummary(): array
    {
        $sql = $this->loadSqlFile('pagehit-stats-summary.sql');
        $result = $this->database->prepare($sql)->single();

        return [
            'totalHits' => (int) ($result['total_hits'] ?? 0),
            'totalUniqueVisitors' => (int) ($result['total_unique_visitors'] ?? 0),
            'uniquePages' => (int) ($result['unique_pages'] ?? 0),
            'todayHits' => (int) ($result['today_hits'] ?? 0),
        ];
    }

    /**
     * Get daily totals for the last 30 days (for graphing).
     */
    public function dailyTotals(): array
    {
        $sql = $this->loadSqlFile('pagehit-daily-totals.sql');

        return $this->database->prepare($sql)->resultset();
    }

    /**
     * Get top 100 pages by hits over the last $days days.
     */
    public function topPages(int $days = 30): array
    {
        $sql = $this->loadSqlFile('pagehit-top-pages.sql');

        return $this->database->prepare($sql, [':days' => $days])->resultset();
    }

    /**
     * Delete records older than $days days.
     *
     * @return int Number of deleted records
     */
    public function cleanupOldRecords(int $days): int
    {
        $sql = $this->loadSqlFile('pagehit-cleanup-old.sql');
        $this->database->prepare($sql, [':days' => $days])->execute();

        return $this->database->rowCount();
    }
}
