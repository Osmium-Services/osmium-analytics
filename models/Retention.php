<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumAnalytics\Models;

use Osmium\Core\Library\OsmiumPDO;

/**
 * Deletes hit rows older than the retention setting. There is no cron (IONOS makes that a chore), so
 * the page.viewed hook calls maybeRun() and a marker file limits it to one run a day. Keep-forever (0)
 * never deletes anything.
 */
class Retention
{
    private const MARKER_PATH = 'app/cache/osmium-analytics-retention';

    public static function maybeRun(OsmiumPDO $database): void
    {
        $years = AnalyticsSettings::get()['retentionYears'];
        if ($years === 0) return;

        $today = \date('Y-m-d');
        $lastRun = \is_file(self::MARKER_PATH) ? \trim((string) \file_get_contents(self::MARKER_PATH)) : '';
        if ($lastRun === $today) return;

        // Mark first, so concurrent requests don't all run the delete
        \file_put_contents(self::MARKER_PATH, $today);

        $days = $years * 365;
        (new PageHit($database))->cleanupOldRecords($days);
        (new PageHitDevice($database))->cleanupOldRecords($days);
        (new PageHitCountry($database))->cleanupOldRecords($days);
    }
}
