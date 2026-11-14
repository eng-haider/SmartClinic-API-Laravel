-- =====================================================================
-- Smart Clinic - Orthodontics module ROLLBACK (MySQL)
--
-- !!! DESTRUCTIVE: deletes ALL orthodontic analyses, files metadata, CBCT
-- study records, treatment plans and audit history of this clinic. The
-- uploaded files on disk are NOT removed by this script.
--
-- To only hide the module, DO NOT run this - switch the setting off:
--   UPDATE `clinic_settings` SET `setting_value` = '0' WHERE `setting_key` = 'enable_orthodontics';
--
-- Run in the clinic (tenant) database. Take a backup first.
-- =====================================================================

SET NAMES utf8mb4;

drop table if exists `orthodontic_audit_logs`;
drop table if exists `orthodontic_treatment_plans`;
drop table if exists `orthodontic_analysis_revisions`;
drop table if exists `orthodontic_analyses`;
drop table if exists `orthodontic_cbct_instances`;
drop table if exists `orthodontic_cbct_studies`;
drop table if exists `orthodontic_files`;

DELETE rhp FROM `role_has_permissions` rhp
JOIN `permissions` p ON p.`id` = rhp.`permission_id`
WHERE p.`guard_name` = 'web' AND p.`name` IN ('view-orthodontics', 'manage-orthodontics');

DELETE mhp FROM `model_has_permissions` mhp
JOIN `permissions` p ON p.`id` = mhp.`permission_id`
WHERE p.`guard_name` = 'web' AND p.`name` IN ('view-orthodontics', 'manage-orthodontics');

DELETE FROM `permissions` WHERE `guard_name` = 'web' AND `name` IN ('view-orthodontics', 'manage-orthodontics');

DELETE FROM `clinic_settings` WHERE `setting_key` = 'enable_orthodontics';

DELETE FROM `migrations` WHERE `migration` IN (
    '2026_11_14_000001_add_enable_orthodontics_setting',
    '2026_11_14_000002_add_orthodontics_permissions',
    '2026_11_14_000003_create_orthodontics_tables'
);
