# Osmium Analytics service

Visitor statistics moved out of core into the service `osmium-analytics` (repo `Osmium-Services/osmium-analytics`,
extracted 2026-10-08). Not to be confused with `google-analytics` (GTM/GA4, id renamed from `analytics` at v2.0.0 to free the name).

## What moved
- `PageTracking`, `GeoCountryLookup`, `MaxMindDbReader`, the five `page_hit*` models and SQL, the Visits, Top Pages and Top Referrers admin pages, their views, lists and scripts, and the cleanup cron script.
- The `page_hit*` tables left the genesis migration; the service's own migration creates them (`CREATE TABLE IF NOT EXISTS`).

## How it hangs together
- Core fires `page.viewed` (dispatch) from `OsmiumController` once per real page request, after features run. Payload: `requestPath`, `pageId`, `slug`, `osmium`. All filtering (bots, HEAD, action endpoints, error page, signed-in admins) is the service's.
- Menu: items use `"placement": "traffic"`, which `ServiceLoader` passes through and the sidebar renders inside the Traffic group above Redirects. Routes keep their old `admin/traffic/...` URLs so the open-section logic is unchanged.
- Retention is a setting (default 5 years, 0 = forever). No cron: `Retention::maybeRun()` runs from the hook at most once a day, gated by `app/cache/osmium-analytics-retention`.
- The `.mmdb` stays at `app/data/geoip/GeoLite2-Country.mmdb`, so the deploy workflows that download it are unchanged.
- Settings file: `app/config/services/osmium-analytics.json.php`.

## Tested 2026-10-08 (local, real stack)
Install and migration, three tracked requests with a Google referrer, the three admin pages and the settings page in Chrome (no console errors), retention deleting a 2015 row but keeping a 4-year-old one, and the once-a-day gate. 828 PHPUnit tests pass.

## Not tested
Saving the settings form, country recording with a real public IP, behind a proxy with `trustProxyHeaders`, and a Store install from GitHub.
