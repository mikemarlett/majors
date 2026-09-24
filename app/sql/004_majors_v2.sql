-- 004: Majors v2 — programs carry their own page content; ordered sections;
-- shared content blocks. Same result as the Majors part of bin/migrate.php.
-- Idempotent (information_schema guards). Then run the importer:
--   cd /data/www/config/majors && MAJORS_SITE=www-test php bin/majors-import.php
--
--   mysql formshandlerdb < /data/www/config/majors/sql/004_majors_v2.sql

SET @sql := IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND ENGINE <> 'InnoDB') = 1, 'ALTER TABLE `majors_academic_programs` ENGINE=InnoDB', 'SELECT ''majors_academic_programs: InnoDB''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_programs_content' AND ENGINE <> 'InnoDB') = 1, 'ALTER TABLE `majors_programs_content` ENGINE=InnoDB', 'SELECT ''majors_programs_content: InnoDB''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_similar_programs' AND ENGINE <> 'InnoDB') = 1, 'ALTER TABLE `majors_similar_programs` ENGINE=InnoDB', 'SELECT ''majors_similar_programs: InnoDB''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_departments' AND ENGINE <> 'InnoDB') = 1, 'ALTER TABLE `majors_departments` ENGINE=InnoDB', 'SELECT ''majors_departments: InnoDB''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_department_content_key' AND ENGINE <> 'InnoDB') = 1, 'ALTER TABLE `majors_department_content_key` ENGINE=InnoDB', 'SELECT ''majors_department_content_key: InnoDB''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- new columns on majors_academic_programs (each added only if missing)
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'basename') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `basename` VARCHAR(200) NULL', 'SELECT ''basename: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'catalog_number') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `catalog_number` INT UNSIGNED NULL', 'SELECT ''catalog_number: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'credential') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `credential` VARCHAR(100) NULL', 'SELECT ''credential: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'college_code') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `college_code` VARCHAR(10) NULL', 'SELECT ''college_code: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'status') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `status` ENUM(''active'',''retired'') NOT NULL DEFAULT ''active''', 'SELECT ''status: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'description') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `description` MEDIUMTEXT NULL', 'SELECT ''description: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'learn_how') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `learn_how` VARCHAR(255) NULL', 'SELECT ''learn_how: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'buttons') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `buttons` TEXT NULL', 'SELECT ''buttons: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'image_url') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `image_url` VARCHAR(255) NULL', 'SELECT ''image_url: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'image_alt') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `image_alt` TEXT NULL', 'SELECT ''image_alt: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'image_caption') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `image_caption` TEXT NULL', 'SELECT ''image_caption: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'image_credit') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `image_credit` VARCHAR(255) NULL', 'SELECT ''image_credit: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'college_url') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `college_url` VARCHAR(255) NULL', 'SELECT ''college_url: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'department_url') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `department_url` VARCHAR(255) NULL', 'SELECT ''department_url: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'meta_description') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `meta_description` TEXT NULL', 'SELECT ''meta_description: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'meta_keywords') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `meta_keywords` TEXT NULL', 'SELECT ''meta_keywords: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'cms_path') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `cms_path` VARCHAR(255) NULL', 'SELECT ''cms_path: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'cms_file_date') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `cms_file_date` DATETIME NULL', 'SELECT ''cms_file_date: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'imported_at') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `imported_at` DATETIME NULL', 'SELECT ''imported_at: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE `majors_academic_programs` p JOIN `majors_programs_content` c ON c.`academic_program_id` = p.`id` SET p.`basename` = c.`basename` WHERE p.`basename` IS NULL OR p.`basename` = '';
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND INDEX_NAME = 'uq_majors_programs_basename') = 0,
  'ALTER TABLE `majors_academic_programs` ADD UNIQUE KEY `uq_majors_programs_basename` (`basename`)', 'SELECT ''uq_majors_programs_basename: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS `majors_content_blocks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `slug` VARCHAR(80) NOT NULL, `headline` VARCHAR(255) NOT NULL DEFAULT '',
  `body` MEDIUMTEXT NULL, `links` TEXT NULL, `note` VARCHAR(255) NULL, `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_blocks_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `majors_program_sections` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `program_id` INT UNSIGNED NOT NULL, `position` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `kind` VARCHAR(20) NOT NULL, `label` VARCHAR(100) NOT NULL DEFAULT '', `headline` VARCHAR(255) NULL, `body` MEDIUMTEXT NULL, `links` TEXT NULL,
  `image_url` VARCHAR(255) NULL, `image_alt` TEXT NULL, `block_id` INT UNSIGNED NULL, `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`), KEY `idx_sections_program` (`program_id`, `position`), KEY `idx_sections_block` (`block_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
