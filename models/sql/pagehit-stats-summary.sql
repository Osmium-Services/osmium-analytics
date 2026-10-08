SELECT
    SUM(hit_count) AS total_hits,
    SUM(unique_visitors) AS total_unique_visitors,
    COUNT(DISTINCT page_url_id) AS unique_pages,
    SUM(CASE WHEN hit_date = CURDATE() THEN hit_count ELSE 0 END) AS today_hits
FROM
    { TABLE }
WHERE
    hit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY);
