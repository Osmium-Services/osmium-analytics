SELECT SUM(hit_count) as total
FROM { PREFIX }page_hits
WHERE hit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  AND referrer_id IS NULL
