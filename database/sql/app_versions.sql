CREATE TABLE `app_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `platform` VARCHAR(20) NOT NULL DEFAULT 'android',
  `version` VARCHAR(50) NOT NULL,
  `build_number` BIGINT UNSIGNED NOT NULL,
  `force_update` TINYINT(1) NOT NULL DEFAULT 0,
  `apk_url` TEXT NOT NULL,
  `message` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `released_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_versions_platform_build_number_unique` (`platform`, `build_number`),
  KEY `app_versions_platform_is_active_build_number_index` (`platform`, `is_active`, `build_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `app_versions` (
  `platform`,
  `version`,
  `build_number`,
  `force_update`,
  `apk_url`,
  `message`,
  `is_active`,
  `released_at`,
  `created_at`,
  `updated_at`
) VALUES (
  'android',
  '1.0.3',
  7,
  0,
  'https://api.smartclinic.software/downloads/smartclinic.apk',
  'إضافة ميزات جديدة وإصلاح بعض المشاكل.',
  1,
  CURRENT_TIMESTAMP,
  CURRENT_TIMESTAMP,
  CURRENT_TIMESTAMP
);
