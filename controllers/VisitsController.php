<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumAnalytics\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\OsmiumAnalytics\Models\GeoCountryLookup;
use Osmium\Services\OsmiumAnalytics\Models\PageHit;
use Osmium\Services\OsmiumAnalytics\Models\PageHitCountry;
use Osmium\Services\OsmiumAnalytics\Models\PageHitDevice;

 __DIR__ . '/../../../../core/library/MaxMindDbReader.php';

/**
 * Visits controller - displays traffic trend, device, and country analytics.
 *
 * Routes:
 *   - index() → /admin/traffic/visits/
 */
class VisitsController extends AdminController
{
    // =========================================================================
    // Public Actions
    // =========================================================================

    /**
     * Visits analytics view - traffic trend over time, device and country
     * breakdowns.
     */
    public function index(): void
    {
        $this->data['admin']['dailyTotals'] = (new PageHit($this->osmium->dataSource))->dailyTotals();
        $this->data['admin']['deviceBreakdown'] = (new PageHitDevice($this->osmium->dataSource))->breakdown();
        $this->data['admin']['countryBreakdown'] = $this->formatCountryBreakdown(
            (new PageHitCountry($this->osmium->dataSource))->breakdown()
        );
        $this->data['admin']['geoAvailable'] = (new GeoCountryLookup())->isAvailable();
        $this->data['admin']['countryTop'] = \array_slice($this->data['admin']['countryBreakdown'], 0, 10);
        $this->data['admin']['countryRest'] = \array_slice($this->data['admin']['countryBreakdown'], 10);

        $this->setView('visits/index.phtml');
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Adds a friendly country name (e.g. "GB" -> "United Kingdom") using PHP's
     * intl/ICU region data - the stored country_code stays the compact,
     * stable aggregation key; the name is resolved only for display.
     */
    private function formatCountryBreakdown(array $countryBreakdown): array
    {
        foreach ($countryBreakdown as &$row) {
            $code = $row['country_code'] ?? '';
            $row['country_name'] = $code !== ''
                ? (\Locale::getDisplayRegion('-' . $code, 'en') ?: $code)
                : $code;
        }
        unset($row);

        return $countryBreakdown;
    }
}
