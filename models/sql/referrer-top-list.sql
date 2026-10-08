SELECT
    r.domain as referrer_domain,
    SUM(h.hit_count) as total_hits,
    SUM(h.unique_visitors) as total_unique,
    COUNT(DISTINCT h.page_url_id) as pages_referred,
    MIN(h.hit_date) as first_seen,
    MAX(h.hit_date) as last_seen,
    COUNT(DISTINCT h.hit_date) as days_active
FROM { PREFIX }page_hits h
JOIN { PREFIX }page_hit_referrers r ON h.referrer_id = r.id
WHERE h.hit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY r.domain
ORDER BY total_hits DESC
LIMIT 100
