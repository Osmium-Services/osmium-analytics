<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumAnalytics\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\OsmiumAnalytics\Models\AnalyticsSettings;
use Osmium\Services\OsmiumAnalytics\Models\GeoCountryLookup;

/**
 * Osmium Analytics settings - tracking on/off, how long hits are kept, ignoring signed-in admins.
 *
 * Routes:
 *   - index() → /admin/settings/osmium-analytics/  (GET shows the form, POST saves it)
 */
class SettingsController extends AdminController
{
    public function index(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') $this->handleSubmit();

        require_once __DIR__ . '/../models/MaxMindDbReader.php';
        require_once __DIR__ . '/../models/GeoCountryLookup.php';

        $this->data['admin']['settings'] = AnalyticsSettings::get();
        $this->data['admin']['retentionOptions'] = AnalyticsSettings::RETENTION_OPTIONS;
        $this->data['admin']['geoAvailable'] = (new GeoCountryLookup())->isAvailable();
        $this->data['admin']['settingsSaved'] = $_SESSION['osmium_analytics_settings_saved'] ?? false;
        $this->data['admin']['settingsError'] = $_SESSION['osmium_analytics_settings_error'] ?? false;
        unset($_SESSION['osmium_analytics_settings_saved'], $_SESSION['osmium_analytics_settings_error']);

        $this->setView('settings/index.phtml');
    }

    private function handleSubmit(): void
    {
        if (!$this->admin->auth->validateCsrf()) {
            $_SESSION['osmium_analytics_settings_error'] = 'Invalid form submission. Please try again.';
            $this->redirect('settings/osmium-analytics/');
        }

        $years = (int) ($_POST['retention_years'] ?? AnalyticsSettings::DEFAULT_RETENTION_YEARS);
        if (!\in_array($years, AnalyticsSettings::RETENTION_OPTIONS, true)) $years = AnalyticsSettings::DEFAULT_RETENTION_YEARS;

        AnalyticsSettings::save(
            enabled: isset($_POST['enabled']),
            retentionYears: $years,
            ignoreAdmins: isset($_POST['ignore_admins']),
        );

        $this->admin->model->changelog->log(
            description: 'Updated Osmium Analytics settings',
            recordType: 'settings',
        );

        $_SESSION['osmium_analytics_settings_saved'] = true;
        $this->redirect('settings/osmium-analytics/');
    }
}
