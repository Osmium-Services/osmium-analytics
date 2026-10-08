# GeoIP database

Osmium Analytics (a Store service) shows a country breakdown in admin and needs `GeoLite2-Country.mmdb` here. It's not
bundled with the repo — MaxMind's license doesn't allow redistributing it,
and it needs a (free) personal account to download.

## Staging and live: automatic (per site)

This template has no deploy workflows. A site that wants country data adds a step to its own
`deploy-staging.yml` (see `solargo2`, `allfast` or `notescheck`). That step downloads the database from
MaxMind using the `MAXMIND_LICENSE_KEY` repo secret, caches it by ISO week (so a push in a new week fetches
a fresh copy and other pushes reuse it, which keeps clear of MaxMind's daily download limit), and rsyncs it
in. `deploy-live.yml` carries it over from staging with everything else. If the secret isn't set, or the
download fails, the step skips quietly and the deploy still succeeds. Country tracking just stays empty.

To set it up: create a free account at
https://www.maxmind.com/en/geolite2/signup, generate a license key, and add
it as a GitHub Actions secret named `MAXMIND_LICENSE_KEY`.

## Local: manual

Local dev doesn't run the GitHub Actions workflow, so the file needs
placing by hand:

1. Create a free account at https://www.maxmind.com/en/geolite2/signup
2. Download **GeoLite2 Country** (binary `.mmdb` format, not CSV)
3. Place the file at `www/app/data/geoip/GeoLite2-Country.mmdb`

Until this file exists, the Osmium Analytics service reports no GeoIP database and
country tracking silently stays empty — nothing else is affected. Device
type breakdown works today regardless, since it's read straight off the
User-Agent header rather than needing this database.

This file is gitignored (`*.mmdb` in `www/app/data/geoip/`) — it's fetched
fresh on staging/live rather than committed, and placed by hand locally.
