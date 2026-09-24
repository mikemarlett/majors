-- 005: Program Details for the graduate template (catalog link, degree title, credit hours,
-- modality, entry terms, STEM flag, coordinator). Idempotent. Same as bin/migrate.php.
--   mysql formshandlerdb < /data/www/config/majors/sql/005_program_details.sql

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'catalog_url') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `catalog_url` VARCHAR(255) NULL', 'SELECT ''catalog_url: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'degree_title') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `degree_title` VARCHAR(120) NULL', 'SELECT ''degree_title: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'credit_hours') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `credit_hours` VARCHAR(40) NULL', 'SELECT ''credit_hours: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'modality') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `modality` VARCHAR(40) NULL', 'SELECT ''modality: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'entry_terms') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `entry_terms` VARCHAR(80) NULL', 'SELECT ''entry_terms: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'is_stem') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `is_stem` TINYINT(1) NOT NULL DEFAULT 0', 'SELECT ''is_stem: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'coordinator_name') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `coordinator_name` VARCHAR(120) NULL', 'SELECT ''coordinator_name: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'coordinator_email') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `coordinator_email` VARCHAR(120) NULL', 'SELECT ''coordinator_email: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'coordinator_phone') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `coordinator_phone` VARCHAR(40) NULL', 'SELECT ''coordinator_phone: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
