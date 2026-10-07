-- 009: degree map approval. A map is on the public site only once an advisor admin or a super
-- admin has approved it; until then only signed-in advisors can see it. Idempotent: safe to re-run.
--   mysql formshandlerdb < /data/www/config/majors/sql/009_degree_maps_approval.sql
-- Run it BEFORE unpacking the app bundle that reads it (the public page filters on `approved` from
-- then on, and nothing is approved until this file has run).
--
-- `approved` and `approved_by` already exist from the original admin (never written by it);
-- `approved_at` is new. On the first run every map for the current catalog year and earlier is
-- approved, so what students see does not change; later years wait for a real approval.

SET @fresh := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'degree_maps' AND COLUMN_NAME = 'approved_at');

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'degree_maps' AND COLUMN_NAME = 'approved') = 0,
  'ALTER TABLE `degree_maps` ADD COLUMN `approved` TINYINT(1) NULL', 'SELECT ''approved: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'degree_maps' AND COLUMN_NAME = 'approved_by') = 0,
  'ALTER TABLE `degree_maps` ADD COLUMN `approved_by` VARCHAR(256) NULL', 'SELECT ''approved_by: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF(@fresh, 'ALTER TABLE `degree_maps` ADD COLUMN `approved_at` DATETIME NULL', 'SELECT ''approved_at: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- First run only: the maps students already see stay visible. (approved_by / approved_at stay
-- NULL: the admin shows these as approved without a name or date.)
SET @sql := IF(@fresh,
  'UPDATE `degree_maps` SET `approved` = 1 WHERE `approved` IS NULL AND `academic_year` <= YEAR(CURDATE()) + IF(MONTH(CURDATE()) >= 8, 1, 0)',
  'SELECT ''approvals: already set''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT `academic_year`, COUNT(*) AS maps, SUM(`approved` = 1) AS approved FROM `degree_maps` GROUP BY `academic_year` ORDER BY `academic_year`;
