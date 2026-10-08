SELECT
    hit_date,
    SUM(hit_count) AS daily_total,
    SUM(unique_visitors) AS daily_unique
FROM
    { TABLE }
WHERE
    hit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY
    hit_date
ORDER BY
    hit_date ASC;
