<?php

declare(strict_types=1);

/**
 * Top Pages table, rendered by Osmium\Modules\Admin\Core\ListView. Read-only.
 * Rows come from PageHitsController::formatTopPage() (already ranked by hits,
 * so the table keeps that order). The reporting period is a server-side
 * filter (reloads with ?days=).
 *
 * Context: topPagesDays, topPagesDayRanges.
 */
return [
    'pages' => [
        'id' => 'top-pages',
        'title' => 'Top Pages',
        'icon' => 'bx-trophy',
        'noun' => ['page', 'pages'],
        'order' => null,
        'badgeLabel' => 'top 100',
        'emptyText' => 'No page hits recorded yet. Visit some pages on the front-end to start tracking.',
        'filters' => [
            [
                'key' => 'days',
                'label' => 'Period',
                'server' => true,
                'param' => 'days',
                'selected' => fn(array $context): ?string => (string) $context['topPagesDays'],
                'options' => fn(array $rows, array $context): array => \array_map(
                    fn(int $days): array => [$days, $days >= 365 && $days % 365 === 0
                        ? 'Last ' . ($days / 365) . ($days === 365 ? ' year' : ' years')
                        : 'Last ' . $days . ' days'],
                    $context['topPagesDayRanges'],
                ),
            ],
        ],
        'columns' => [
            ['key' => 'rank_badge', 'label' => '#', 'type' => 'raw', 'sortKey' => 'rank'],
            [
                'key' => 'page_url',
                'label' => 'Page URL',
                'type' => 'link',
                'href' => '{page_url}/',
                'code' => true,
                'external' => true,
                'fill' => true,
            ],
            ['key' => 'total_hits', 'label' => 'Hits', 'type' => 'badge', 'class' => 'bg-label-primary'],
            ['key' => 'total_unique', 'label' => 'Unique', 'type' => 'badge', 'class' => 'bg-label-success'],
            [
                'key' => 'days_active_label',
                'label' => 'Days Active',
                'type' => 'text',
                'small' => true,
                'nowrap' => true,
                'sortKey' => 'days_active',
            ],
            [
                'key' => 'avg_per_day',
                'label' => 'Avg/Day',
                'type' => 'text',
                'muted' => true,
                'nowrap' => true,
                'sortKey' => 'avg_per_day_sort',
            ],
            [
                'key' => 'last_hit_formatted',
                'label' => 'Last Hit',
                'type' => 'text',
                'small' => true,
                'nowrap' => true,
                'sortKey' => 'last_hit',
            ],
        ],
    ],
];
