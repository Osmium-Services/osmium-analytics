<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumAnalytics\Models;

/**
 * This service's settings, read from app/config/services/osmium-analytics.json.php (the file-based
 * convention the other services use). Missing file means the defaults: tracking on, five years kept,
 * signed-in admins ignored.
 */
class AnalyticsSettings
{
    public const CONFIG_PATH = 'app/config/services/osmium-analytics.json.php';
    public const DEFAULT_RETENTION_YEARS = 5;
    public const RETENTION_OPTIONS = [1, 2, 3, 5, 10, 0]; // 0 = keep forever

    private static ?array $settings = null;

    public static function get(): array
    {
        if (self::$settings !== null) return self::$settings;

        $settings = self::defaults();

        if (\file_exists(self::CONFIG_PATH)) {
            $content = (string) \file_get_contents(self::CONFIG_PATH);
            $jsonStart = \strpos(haystack: $content, needle: '{');
            $stored = $jsonStart === false ? null : \json_decode(\substr(string: $content, offset: $jsonStart), associative: true);

            if (\is_array($stored['analytics'] ?? null)) {
                $settings = \array_merge($settings, \array_intersect_key($stored['analytics'], $settings));
            }
        }

        $settings['enabled'] = (bool) $settings['enabled'];
        $settings['ignoreAdmins'] = (bool) $settings['ignoreAdmins'];
        $settings['retentionYears'] = \in_array((int) $settings['retentionYears'], self::RETENTION_OPTIONS, true)
            ? (int) $settings['retentionYears']
            : self::DEFAULT_RETENTION_YEARS;

        return self::$settings = $settings;
    }

    public static function save(bool $enabled, int $retentionYears, bool $ignoreAdmins): void
    {
        $dir = \dirname(self::CONFIG_PATH);
        if (!\is_dir($dir)) \mkdir(directory: $dir, permissions: 0755, recursive: true);

        $data = [
            'analytics' => [
                'enabled' => $enabled,
                'retentionYears' => $retentionYears,
                'ignoreAdmins' => $ignoreAdmins,
            ],
        ];

        $json = \json_encode(value: $data, flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        \file_put_contents(self::CONFIG_PATH, "<?php exit(); ?>\n" . $json . "\n");

        self::$settings = null;
    }

    private static function defaults(): array
    {
        return [
            'enabled' => true,
            'retentionYears' => self::DEFAULT_RETENTION_YEARS,
            'ignoreAdmins' => true,
        ];
    }
}
