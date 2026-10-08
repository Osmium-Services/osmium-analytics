INSERT INTO { TABLE }
    (hit_date, device_type, hit_count)
VALUES
    (CURDATE(), :device_type, 1)
ON DUPLICATE KEY UPDATE
    hit_count = hit_count + 1,
    updated_at = NOW()
