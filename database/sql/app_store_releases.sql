-- Same as migration 2026_11_21_000001_create_app_store_releases_table.
-- After a new version is live on a store, raise that row's version and
-- build_number. Raise min_build_number to force older builds to update.
CREATE TABLE `app_store_releases` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `platform` VARCHAR(20) NOT NULL,
  `version` VARCHAR(50) NOT NULL,
  `build_number` BIGINT UNSIGNED NOT NULL,
  `min_build_number` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `store_url` TEXT NOT NULL,
  `message_ar` TEXT NULL,
  `message_en` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_store_releases_platform_unique` (`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `app_store_releases`
  (`platform`, `version`, `build_number`, `min_build_number`, `store_url`, `is_active`, `created_at`, `updated_at`)
VALUES
  ('android', '2.1.2', 10, 0, 'https://play.google.com/store/apps/details?id=software.smartclinic.smart_clinic_app', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
  -- Off until its App Store link is filled in.
  ('ios', '2.1.2', 10, 0, 'https://apps.apple.com/app/idAPP_STORE_ID', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

-- Example, after build 16 is live on both stores:
-- UPDATE app_store_releases SET version = '2.1.3', build_number = 16,
--   message_ar = 'تحسينات جديدة', message_en = 'New improvements'
-- WHERE platform IN ('android', 'ios');
