INSERT INTO { TABLE }
    (page_url_id, hit_date, hit_count, unique_visitors, referrer_id)
VALUES
    (:page_url_id, CURDATE(), 1, :unique_increment, :referrer_id)
ON DUPLICATE KEY UPDATE
    hit_count = hit_count + 1,
    unique_visitors = unique_visitors + :unique_increment_update,
    updated_at = NOW()
