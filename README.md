# Osmium Analytics

Cookieless, first-party visitor statistics for Osmium sites: visits over time, top pages, top referrers,
device type and visitor country. IP addresses are never stored. No cookie is set, so no consent is needed.

- Admin pages sit in the Traffic group: Visits, Top Pages, Top Referrers. Settings live under Services.
- Records through core's `page.viewed` hook, which fires once per real page request. Skips bots, action
  endpoints, HEAD requests, error pages and (setting) signed-in admins.
- Retention is a setting (1, 2, 3, 5 or 10 years, or forever; default 5). Old rows are pruned at most once a
  day from inside the hook, so no cron is needed.
- Country needs a MaxMind `GeoLite2-Country.mmdb` at `app/data/geoip/` (see GEOIP.md). Without it, only the
  country breakdown stays empty.
- Tables: `page_hits`, `page_hit_urls`, `page_hit_referrers`, `page_hit_devices`, `page_hit_countries`.
  Uninstalling leaves them in place.
