SELECT
    u.page_url,
    SUM(h.hit_count) AS total_hits,
    SUM(h.unique_visitors) AS total_unique,
    MIN(h.hit_date) AS first_hit,
    MAX(h.hit_date) AS last_hit,
    COUNT(DISTINCT h.hit_date) AS days_active
FROM { TABLE } h
JOIN { PREFIX }page_hit_urls u ON h.page_url_id = u.id
WHERE h.hit_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
GROUP BY u.page_url
ORDER BY total_hits DESC
LIMIT 100
