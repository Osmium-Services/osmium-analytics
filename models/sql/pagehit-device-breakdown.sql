SELECT
    device_type,
    SUM(hit_count) AS total_hits
FROM
    { TABLE }
WHERE
    hit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY
    device_type
ORDER BY
    total_hits DESC;
