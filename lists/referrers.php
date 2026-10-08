<?php

declare(strict_types=1);

/**
 * Top Referrers table, rendered by Osmium\Modules\Admin\Core\ListView.
 * Read-only; rows come from ReferrersController::formatReferrer() already
 * ranked by hits, so the table keeps that order.
 */
return [
    'referrers' => [
        'id' => 'top-referrers',
        'title' => 'Top Referrers',
        'icon' => 'bx-link',
        'noun' => ['referrer', 'referrers'],
        'order' => null,
        'badgeLabel' => 'top 100',
        'emptyText' => 'No referral data recorded yet. External links to your site will appear here.',
        'columns' => [
            [
                'key' => 'rank',
                'label' => '#',
                'type' => 'html',
                'render' => fn(array $row, array $context): string => $row['rankShowMedal']
                    ? '<span class="badge bg-' . \htmlspecialchars($row['rankBadgeClass']) . '" style="'
                        . \htmlspecialchars($row['rankBadgeStyle']) . '"><i class="bx bxs-medal"></i></span>'
                    : '<span class="text-muted">' . (int) $row['rank'] . '</span>',
            ],
            [
                'key' => 'referrer_domain',
                'label' => 'Referrer Domain',
                'type' => 'link',
                'href' => '{referrerUrl}',
                'code' => true,
                'external' => true,
                'fill' => true,
            ],
            ['key' => 'total_hits', 'label' => 'Hits', 'type' => 'badge', 'class' => 'bg-label-primary'],
            ['key' => 'total_unique', 'label' => 'Unique', 'type' => 'badge', 'class' => 'bg-label-success'],
            [
                'key' => 'pagesLabel',
                'label' => 'Pages',
                'type' => 'text',
                'small' => true,
                'nowrap' => true,
                'sortKey' => 'pages_referred',
            ],
            [
                'key' => 'daysLabel',
                'label' => 'Days Active',
                'type' => 'text',
                'small' => true,
                'nowrap' => true,
                'sortKey' => 'days_active',
            ],
            [
                'key' => 'lastSeenFormatted',
                'label' => 'Last Seen',
                'type' => 'text',
                'small' => true,
                'nowrap' => true,
                'sortKey' => 'last_seen',
            ],
        ],
    ],
];
