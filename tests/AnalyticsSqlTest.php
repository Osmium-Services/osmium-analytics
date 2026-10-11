<?php

declare(strict_types=1);

namespace Tests\Services\OsmiumAnalytics;

use Osmium\Services\OsmiumAnalytics\Models\PageHit;
use Osmium\Services\OsmiumAnalytics\Models\PageHitCountry;
use Osmium\Services\OsmiumAnalytics\Models\PageHitDevice;
use Osmium\Services\OsmiumAnalytics\Models\ReferrerStats;
use Tests\Integration\DatabaseTestCase;

require_once __DIR__ . '/../models/PageHitBase.php';
require_once __DIR__ . '/../models/PageHit.php';
require_once __DIR__ . '/../models/ReferrerStats.php';
require_once __DIR__ . '/../models/PageHitDevice.php';
require_once __DIR__ . '/../models/PageHitCountry.php';

/**
 * The SQL behind traffic_summary, run against real rows: only the last 30 days count, direct visits are the hits
 * with no referrer, and the lists come most-hit first. Totals are compared before and after because the dev
 * database already holds real visits.
 */
class AnalyticsSqlTest extends DatabaseTestCase
{
    private PageHit $hits;
    private ReferrerStats $referrers;
    private string $runTag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hits = new PageHit($this->db);
        $this->referrers = new ReferrerStats($this->db);
        $this->runTag = 'zq' . \substr(\uniqid(), -6);

        $prefix = $this->db->tablePrefix();
        $tableExists = $this->db->prepare("SHOW TABLES LIKE '{$prefix}page_hits'")->single() !== false;
        if (!$tableExists) $this->markTestSkipped('The Analytics tables are not installed');
    }

    private function url(string $suffix): int
    {
        $prefix = $this->db->tablePrefix();
        $this->db->prepare("INSERT INTO {$prefix}page_hit_urls (page_url) VALUES (:url)", [':url' => "/{$this->runTag}-{$suffix}"])->execute();

        return (int) $this->db->lastInsertId();
    }

    private function referrer(string $suffix): int
    {
        $prefix = $this->db->tablePrefix();
        $this->db->prepare("INSERT INTO {$prefix}page_hit_referrers (domain) VALUES (:domain)", [':domain' => "{$this->runTag}-{$suffix}.example"])->execute();

        return (int) $this->db->lastInsertId();
    }

    private function hit(int $urlId, int $daysAgo, int $hits, int $unique, ?int $referrerId = null): void
    {
        $prefix = $this->db->tablePrefix();
        $this->db->prepare(
            "INSERT INTO {$prefix}page_hits (page_url_id, hit_date, hit_count, unique_visitors, referrer_id) VALUES (:url, DATE_SUB(CURDATE(), INTERVAL :days DAY), :hits, :unique, :referrer)",
            [':url' => $urlId, ':days' => $daysAgo, ':hits' => $hits, ':unique' => $unique, ':referrer' => $referrerId],
        )->execute();
    }

    /** @return array<int, array<string, mixed>> this run's rows from a list that has a text column */
    private function mine(array $rows, string $column): array
    {
        return \array_values(\array_filter($rows, fn(array $row): bool => \str_contains((string) $row[$column], $this->runTag)));
    }

    public function test_the_summary_counts_only_the_last_30_days_and_todays_hits_separately(): void
    {
        $before = $this->hits->statsSummary();

        $this->hit($this->url('recent'), 2, 5, 3);
        $this->hit($this->url('edge'), 29, 2, 1);
        $this->hit($this->url('old'), 31, 100, 50);
        $this->hit($this->url('today'), 0, 4, 4);
        $this->hit($this->url('yesterday'), 1, 9, 9);

        $after = $this->hits->statsSummary();

        $this->assertSame(20, $after['totalHits'] - $before['totalHits']);
        $this->assertSame(17, $after['totalUniqueVisitors'] - $before['totalUniqueVisitors']);
        $this->assertSame(4, $after['uniquePages'] - $before['uniquePages']);
        $this->assertSame(4, $after['todayHits'] - $before['todayHits']);
    }

    public function test_the_daily_totals_add_up_each_day_and_leave_out_old_days(): void
    {
        $day = \date('Y-m-d', \strtotime('-27 days'));
        $old = \date('Y-m-d', \strtotime('-33 days'));
        $total = static function (array $rows, string $date): int {
            foreach ($rows as $row) {
                if ((string) $row['hit_date'] === $date) return (int) $row['daily_total'];
            }

            return 0;
        };
        $before = $this->hits->dailyTotals();

        $this->hit($this->url('d1'), 27, 3, 2);
        $this->hit($this->url('d2'), 27, 4, 3);
        $this->hit($this->url('d3'), 33, 50, 50);

        $after = $this->hits->dailyTotals();

        $this->assertSame(7, $total($after, $day) - $total($before, $day));
        $this->assertSame(0, $total($after, $old));
    }

    public function test_the_top_pages_are_most_hit_first_and_leave_out_old_hits(): void
    {
        $this->hit($this->url('small'), 1, 1000001, 1);
        $this->hit($this->url('big'), 1, 1000009, 1);
        $this->hit($this->url('stale'), 40, 1000050, 1);

        $pages = \array_column($this->mine($this->hits->topPages(30), 'page_url'), 'page_url');

        $this->assertSame(["/{$this->runTag}-big", "/{$this->runTag}-small"], $pages);
    }

    public function test_direct_visits_are_the_hits_with_no_referrer_inside_the_window(): void
    {
        $before = $this->referrers->summary()['directTraffic'];
        $site = $this->referrer('direct-test');

        $this->hit($this->url('direct'), 3, 6, 4);
        $this->hit($this->url('direct-old'), 40, 60, 40);
        $this->hit($this->url('referred'), 3, 9, 5, $site);

        $this->assertSame(6, $this->referrers->summary()['directTraffic'] - $before);
    }

    public function test_the_referrer_list_groups_by_site_counts_pages_and_leaves_out_direct_and_old_hits(): void
    {
        $site = $this->referrer('big');
        $quiet = $this->referrer('quiet');
        $this->hit($this->url('r1'), 1, 1000008, 5, $site);
        $this->hit($this->url('r2'), 2, 2, 1, $site);
        $this->hit($this->url('r3'), 1, 1000001, 1, $quiet);
        $this->hit($this->url('r4'), 45, 1000090, 1, $quiet);
        $this->hit($this->url('r5'), 1, 1000077, 1, null);

        $rows = $this->mine($this->referrers->topList(), 'referrer_domain');

        $this->assertSame(["{$this->runTag}-big.example", "{$this->runTag}-quiet.example"], \array_column($rows, 'referrer_domain'));
        $this->assertSame(1000010, (int) $rows[0]['total_hits']);
        $this->assertSame(2, (int) $rows[0]['pages_referred']);
        $this->assertSame(1000001, (int) $rows[1]['total_hits']);
    }

    public function test_devices_and_countries_count_only_the_last_30_days(): void
    {
        $prefix = $this->db->tablePrefix();
        $devices = new PageHitDevice($this->db);
        $countries = new PageHitCountry($this->db);
        $sum = static function (array $rows, string $key, string $value): int {
            foreach ($rows as $row) {
                if ($row[$key] === $value) return (int) $row['total_hits'];
            }

            return 0;
        };
        $deviceBefore = $sum($devices->breakdown(), 'device_type', 'tablet');
        $countryBefore = $sum($countries->breakdown(), 'country_code', 'ZQ');

        foreach ([[2, 5], [33, 70]] as [$days, $count]) {
            $this->db->prepare(
                "INSERT INTO {$prefix}page_hit_devices (hit_date, device_type, hit_count) VALUES (DATE_SUB(CURDATE(), INTERVAL :days DAY), 'tablet', :count) ON DUPLICATE KEY UPDATE hit_count = hit_count + VALUES(hit_count)",
                [':days' => $days, ':count' => $count],
            )->execute();
            $this->db->prepare(
                "INSERT INTO {$prefix}page_hit_countries (hit_date, country_code, hit_count) VALUES (DATE_SUB(CURDATE(), INTERVAL :days DAY), 'ZQ', :count) ON DUPLICATE KEY UPDATE hit_count = hit_count + VALUES(hit_count)",
                [':days' => $days, ':count' => $count],
            )->execute();
        }

        $this->assertSame(5, $sum($devices->breakdown(), 'device_type', 'tablet') - $deviceBefore);
        $this->assertSame(5, $sum($countries->breakdown(), 'country_code', 'ZQ') - $countryBefore);
    }
}
