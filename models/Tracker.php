<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumAnalytics\Models;

use Osmium\Core\Library\IpAddress;

require_once __DIR__ . '/MaxMindDbReader.php';
require_once __DIR__ . '/GeoCountryLookup.php';

/**
 * Answers core's `page.viewed` hook: records page visits, unique visitors, referrer sources, device
 * type and visitor country. Skips bots, action endpoints, HEAD requests, error pages and (by setting)
 * signed-in admins.
 *
 * Device type is read off the User-Agent header and country is resolved from the request IP via a
 * local, offline GeoIP database. The IP is never stored, only the country code and a count. Neither
 * needs a cookie, so no consent is needed.
 *
 * Tracking must never break a page render: any failure costs one row of data, never the page.
 */
class Tracker
{
    private const BOT_SIGNATURES = [
        'bot', 'crawler', 'spider', 'scraper', 'slurp', 'curl', 'wget', 'googlebot', 'bingbot', 'yandex',
        'baidu', 'duckduckbot', 'facebookexternalhit', 'twitterbot', 'linkedinbot', 'ahrefs', 'semrush',
        'moz.com', 'screaming frog', 'pingdom', 'uptimerobot', 'gtmetrix', 'python-requests', 'go-http-client',
    ];

    /**
     * @param array{requestPath: string, pageId: int, slug: string, osmium: object} $payload
     */
    public static function pageViewed(array $payload): void
    {
        try {
            $settings = AnalyticsSettings::get();
            if (!$settings['enabled']) return;
            if (!self::shouldTrack($payload, $settings)) return;

            self::recordHit($payload);
            Retention::maybeRun($payload['osmium']->dataSource);
        } catch (\Throwable $e) {
            \error_log('Osmium Analytics: ' . $e->getMessage());
        }
    }

    private static function shouldTrack(array $payload, array $settings): bool
    {
        if (\str_contains($payload['requestPath'], '/action')) return false;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD') return false; // availability checks

        if ($settings['ignoreAdmins'] && !empty($_SESSION['admin_user_id']) && !empty($_SESSION['admin_login_time'])) return false;

        $userAgent = \mb_strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($userAgent === '' || self::isBot($userAgent)) return false;

        if (!$payload['pageId']) return false; // 404s and the like
        if ($payload['slug'] === 'error') return false; // 404s are tracked in redirect logs

        return true;
    }

    private static function recordHit(array $payload): void
    {
        $database = $payload['osmium']->dataSource;
        $requestPath = $payload['requestPath'];
        $pageUrl = $requestPath === '/' ? '/' : \rtrim($requestPath, '/');

        $referrerDomain = self::extractReferrerDomain();
        $isUniqueVisitor = self::checkAndMarkUniqueVisitor($pageUrl, $referrerDomain);

        $pageUrlId = (new PageHitUrl($database))->getOrCreateId($pageUrl);
        if ($pageUrlId === 0) {
            \error_log("Osmium Analytics: no URL row for {$pageUrl} - hit not recorded");
            return; // Recording against a non-existent id violates fk_page_hits_url
        }

        $referrerId = null;
        if ($referrerDomain !== null) {
            $id = (new PageHitReferrer($database))->getOrCreateId($referrerDomain);
            $referrerId = $id === 0 ? null : $id; // Record the hit without a referrer rather than dropping it
        }

        (new PageHit($database))->record(pageUrlId: $pageUrlId, referrerId: $referrerId, isUniqueVisitor: $isUniqueVisitor);
        (new PageHitDevice($database))->record(self::detectDeviceType($_SERVER['HTTP_USER_AGENT'] ?? ''));

        self::recordCountry($payload);
    }

    /**
     * Best-effort: does nothing until a GeoLite2-Country.mmdb file is in place (see GEOIP.md).
     */
    private static function recordCountry(array $payload): void
    {
        $geo = new GeoCountryLookup();
        if (!$geo->isAvailable()) return;

        $trustProxyHeaders = $payload['osmium']->config->site->trustProxyHeaders ?? false;
        $visitorIp = (new IpAddress())->getVisitorIP($trustProxyHeaders);

        $countryCode = $geo->lookup($visitorIp);
        if ($countryCode === null) return;

        (new PageHitCountry($payload['osmium']->dataSource))->record($countryCode);
    }

    /**
     * Tablets often also match the generic "Mobile" token (iPadOS Safari, most Android tablets), so
     * tablet signatures are checked first or every tablet would be counted as a phone.
     */
    private static function detectDeviceType(string $userAgent): string
    {
        $ua = \mb_strtolower($userAgent);

        $isTablet = \str_contains($ua, 'ipad')
            || \str_contains($ua, 'tablet')
            || (\str_contains($ua, 'android') && !\str_contains($ua, 'mobile'));
        if ($isTablet) return 'tablet';

        $isMobile = \str_contains($ua, 'mobile')
            || \str_contains($ua, 'iphone')
            || \str_contains($ua, 'ipod')
            || \str_contains($ua, 'android')
            || \str_contains($ua, 'blackberry')
            || \str_contains($ua, 'windows phone');

        return $isMobile ? 'mobile' : 'desktop';
    }

    private static function isBot(string $userAgent): bool
    {
        foreach (self::BOT_SIGNATURES as $signature) {
            if (\str_contains($userAgent, $signature)) return true;
        }

        return false;
    }

    private static function extractReferrerDomain(): ?string
    {
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        if (empty($referrer)) return null;

        $referrerHost = \parse_url(url: $referrer, component: PHP_URL_HOST);
        $ownHost = $_SERVER['HTTP_HOST'] ?? '';

        $isExternal = $referrerHost
            && $referrerHost !== $ownHost
            && !\str_ends_with($referrerHost, '.' . $ownHost); // Only external referrers

        return $isExternal ? \mb_strtolower($referrerHost) : null;
    }

    private static function checkAndMarkUniqueVisitor(string $pageUrl, ?string $referrerDomain): bool
    {
        $sessionKey = 'page_hits_' . \date('Y-m-d');
        $visitKey = $pageUrl . '|' . ($referrerDomain ?? 'direct');

        if (!isset($_SESSION[$sessionKey])) $_SESSION[$sessionKey] = [];

        $isUnique = !\in_array($visitKey, $_SESSION[$sessionKey]);
        if ($isUnique) $_SESSION[$sessionKey][] = $visitKey;

        return $isUnique;
    }
}
