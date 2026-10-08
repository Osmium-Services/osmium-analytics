<?php

namespace Osmium\Services\OsmiumAnalytics\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\OsmiumAnalytics\Models\ReferrerStats;

/**
 * Referrers Controller
 *
 * Analytics view showing top referring domains and traffic statistics
 * Routes:
 * - index: Read-only analytics dashboard with 30-day referral data
 */
class ReferrersController extends AdminController
{
    public function index(): void
    {
        $referrerStats = new ReferrerStats($this->osmium->dataSource);
        $topReferrers = $referrerStats->topList();
        $stats = $referrerStats->summary();
        $dailyReferrals = $referrerStats->dailyTotals();

        $formattedReferrers = [];
        $rank = 1;
        foreach ($topReferrers as $referrer) {
            $formattedReferrers[] = $this->formatReferrer(
                referrer: $referrer,
                rank: $rank++
            );
        }

        require_once 'app/modules/admin/core/ListView.php'; // list definitions use ListView helpers
        $this->data['admin']['lists'] = require __DIR__ . '/../lists/referrers.php';
        $this->data['admin']['topReferrers'] = $formattedReferrers;
        $this->data['admin']['totalReferralHits'] = $stats['totalHits'];
        $this->data['admin']['totalReferralVisitors'] = $stats['totalVisitors'];
        $this->data['admin']['uniqueReferrers'] = $stats['uniqueCount'];
        $this->data['admin']['directTraffic'] = $stats['directTraffic'];
        $this->data['admin']['dailyReferrals'] = $dailyReferrals;

        $this->setView('referrers/index.phtml');
    }

    // =====================================================================
    // HELPERS
    // =====================================================================

    private function formatReferrer(array $referrer, int $rank): array
    {
        $shouldShowMedal = $rank <= 3;

        if ($shouldShowMedal) {
            $rankBadgeClass = match ($rank) {
                1 => 'warning',
                2 => 'secondary',
                3 => 'danger',
                default => ''
            };
            $rankBadgeStyle = match ($rank) {
                2 => 'background-color: #c0c0c0 !important;',
                3 => 'background-color: #cd7f32 !important;',
                default => ''
            };
        } else {
            $rankBadgeClass = '';
            $rankBadgeStyle = '';
        }

        $lastSeenFormatted = \date(
            format: 'j M Y',
            timestamp: \strtotime($referrer['last_seen'])
        );

        $pagesReferred = (int)$referrer['pages_referred'];
        $daysActive = (int)$referrer['days_active'];
        $pagesLabel = $pagesReferred . ' page' . ($pagesReferred !== 1 ? 's' : '');
        $daysLabel = $daysActive . ' day' . ($daysActive !== 1 ? 's' : '');

        $referrerUrl = 'https://' . ($referrer['referrer_domain'] ?? '');

        return [
            'referrer_domain' => $referrer['referrer_domain'] ?? '',
            'total_hits' => (int)$referrer['total_hits'],
            'total_unique' => (int)$referrer['total_unique'],
            'pages_referred' => $pagesReferred,
            'days_active' => $daysActive,
            'last_seen' => $referrer['last_seen'],
            'rank' => $rank,
            'rankBadgeClass' => $rankBadgeClass,
            'rankBadgeStyle' => $rankBadgeStyle,
            'rankShowMedal' => $shouldShowMedal,
            'lastSeenFormatted' => $lastSeenFormatted,
            'pagesLabel' => $pagesLabel,
            'daysLabel' => $daysLabel,
            'referrerUrl' => $referrerUrl,
        ];
    }
}
