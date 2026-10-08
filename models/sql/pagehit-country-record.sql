INSERT INTO { TABLE }
    (hit_date, country_code, hit_count)
VALUES
    (CURDATE(), :country_code, 1)
ON DUPLICATE KEY UPDATE
    hit_count = hit_count + 1,
    updated_at = NOW()
