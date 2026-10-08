SELECT
    country_code,
    SUM(hit_count) AS total_hits
FROM
    { TABLE }
WHERE
    hit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY
    country_code
ORDER BY
    total_hits DESC;
