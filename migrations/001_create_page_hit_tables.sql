CREATE TABLE IF NOT EXISTS `{PREFIX}page_hit_countries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `hit_date` date NOT NULL,
  `country_code` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `hit_count` int unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_date_country` (`hit_date`,`country_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `{PREFIX}page_hit_devices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `hit_date` date NOT NULL,
  `device_type` enum('desktop','mobile','tablet','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `hit_count` int unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_date_device` (`hit_date`,`device_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `{PREFIX}page_hit_referrers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `domain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `{PREFIX}page_hit_urls` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `page_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_url` (`page_url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `{PREFIX}page_hits` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `page_url_id` int unsigned NOT NULL,
  `hit_date` date NOT NULL,
  `hit_count` int unsigned NOT NULL DEFAULT '1',
  `unique_visitors` int unsigned NOT NULL DEFAULT '0',
  `referrer_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `referrer_key` int unsigned GENERATED ALWAYS AS (coalesce(`referrer_id`,0)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_page_date_refkey` (`page_url_id`,`hit_date`,`referrer_key`),
  KEY `idx_hit_date` (`hit_date`),
  KEY `idx_page_url_id` (`page_url_id`),
  KEY `fk_page_hits_referrer` (`referrer_id`),
  CONSTRAINT `fk_page_hits_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `{PREFIX}page_hit_referrers` (`id`),
  CONSTRAINT `fk_page_hits_url` FOREIGN KEY (`page_url_id`) REFERENCES `{PREFIX}page_hit_urls` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
