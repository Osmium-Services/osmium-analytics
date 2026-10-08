<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumAnalytics\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\OsmiumAnalytics\Models\PageHit;

/**
 * Page Hits controller - displays page hit analytics.
 *
 * Routes:
 *   - index() → /admin/traffic/page-hits/
 */
class PageHitsController extends AdminController
{
    /** Reporting periods offered for Top Pages, in days. Capped by the retention setting, since older rows are deleted. */
    private const TOP_PAGES_DAY_RANGES = [30, 90, 180, 365, 730, 1825];

    private const TOP_PAGES_DEFAULT_DAYS = 30;

    // =========================================================================
    // Public Actions
    // =========================================================================

    /**
     * Page hits analytics view - displays top pages by hits and stats summary.
     */
    public function index(): void
    {
        $hits = new PageHit($this->osmium->dataSource);
        $days = $this->parseTopPagesDays();
        $topPages = $hits->topPages($days);
        $stats = $hits->statsSummary();

        $formattedPages = [];
        $rank = 1;
        foreach ($topPages as $page) {
            $formattedPages[] = $this->formatTopPage(page: $page, rank: $rank);
            $rank++;
        }

        require_once 'app/modules/admin/core/ListView.php'; // list definitions use ListView helpers
        $this->data['admin']['lists'] = require __DIR__ . '/../lists/page-hits.php';
        $this->data['admin']['topPages'] = $formattedPages;
        $this->data['admin']['topPagesDays'] = $days;
        $this->data['admin']['topPagesDayRanges'] = self::TOP_PAGES_DAY_RANGES;
        $this->data['admin']['totalHits'] = $stats['totalHits'];
        $this->data['admin']['totalUniqueVisitors'] = $stats['totalUniqueVisitors'];
        $this->data['admin']['uniquePages'] = $stats['uniquePages'];
        $this->data['admin']['todayHits'] = $stats['todayHits'];

        $this->setView('page-hits/index.phtml');
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Reporting period for the Top Pages cards, from ?days=. Anything not on
     * the offered list falls back to the default rather than reaching the query.
     */
    private function parseTopPagesDays(): int
    {
        // Admin pages receive their query string via the session stash, not $_GET
        $requestedDays = (int)$this->osmium->getStashedParam(key: 'days', default: self::TOP_PAGES_DEFAULT_DAYS);
        $this->osmium->clearStashedQuerystring();
        $isOffered = \in_array(needle: $requestedDays, haystack: self::TOP_PAGES_DAY_RANGES, strict: true);

        return $isOffered ? $requestedDays : self::TOP_PAGES_DEFAULT_DAYS;
    }

    /**
     * Format a top page for display - pre-computes rank badges, dates, averages.
     */
    private function formatTopPage(array $page, int $rank): array
    {
        $daysActive = (int)$page['days_active'];
        $totalHits = (int)$page['total_hits'];

        $rankBadge = $this->getRankBadge($rank);
        $pieRankBadge = $this->getPieRankBadge($rank);

        $avgPerDay = $daysActive > 0
            ? \number_format(num: $totalHits / $daysActive, decimals: 1)
            : '-';

        $daysActiveLabel = $daysActive . ' day' . ($daysActive != 1 ? 's' : '');

        $timestamp = \strtotime($page['last_hit']);
        $lastHitFormatted = \date(format: 'j M Y', timestamp: $timestamp);

        return [
            'rank' => $rank,
            'rank_badge' => $rankBadge,
            'pie_rank_badge' => $pieRankBadge,
            'page_url' => $page['page_url'] ?? '',
            'total_hits' => $totalHits,
            'total_unique' => (int)$page['total_unique'],
            'days_active' => $daysActive,
            'days_active_label' => $daysActiveLabel,
            'avg_per_day' => $avgPerDay,
            'avg_per_day_sort' => $daysActive > 0 ? $totalHits / $daysActive : 0,
            'last_hit' => $page['last_hit'],
            'last_hit_formatted' => $lastHitFormatted,
        ];
    }

    /**
     * Get rank badge HTML for table display.
     */
    private function getRankBadge(int $rank): string
    {
        if ($rank <= 3) {
            $badgeClass = match ($rank) {
                1 => 'bg-warning',
                2 => 'bg-secondary',
                3 => 'bg-danger',
            };

            $style = match ($rank) {
                2 => 'background-color: #c0c0c0 !important;',
                3 => 'background-color: #cd7f32 !important;',
                default => '',
            };

            $styleAttr = $style ? " style=\"{$style}\"" : '';
            return "<span class=\"badge {$badgeClass}\"{$styleAttr}><i class=\"bx bxs-medal\"></i></span>";
        }

        return "<span class=\"text-muted\">{$rank}</span>";
    }

    /**
     * Get rank badge HTML for pie chart display.
     */
    private function getPieRankBadge(int $rank): string
    {
        if ($rank <= 3) {
            $bgColor = match ($rank) {
                1 => '#FFD700',
                2 => '#A9A9A9',
                3 => '#CD7F32',
            };

            return "<span class=\"badge me-3\" style=\"background-color: {$bgColor} !important;\"><i class=\"bx bxs-medal\"></i></span>";
        }

        return "<span class=\"badge bg-label-secondary me-3\">{$rank}</span>";
    }
}
