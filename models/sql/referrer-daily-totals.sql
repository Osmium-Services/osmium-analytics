SELECT hit_date, SUM(hit_count) as daily_total, SUM(unique_visitors) as daily_unique
FROM { PREFIX }page_hits
WHERE hit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  AND referrer_id IS NOT NULL
GROUP BY hit_date
ORDER BY hit_date ASC
