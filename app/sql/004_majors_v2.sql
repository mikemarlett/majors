-- 004: Majors v2 — programs carry their own page content; ordered sections;
-- shared content blocks. Same result as the Majors part of bin/migrate.php.
-- Idempotent (information_schema guards). Then run:
--   cd /data/www/config/majors && MAJORS_SITE=www-test php bin/majors-import.php
--
--   mysql formshandlerdb < /data/www/config/majors/sql/004_majors_v2.sql

-- InnoDB so the importer runs in one transaction
SET @sql := IF((SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs') <> 'InnoDB', 'ALTER TABLE `majors_academic_programs` ENGINE=InnoDB', 'SELECT ''ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_programs_content') <> 'InnoDB', 'ALTER TABLE `majors_programs_content` ENGINE=InnoDB', 'SELECT ''ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_similar_programs') <> 'InnoDB', 'ALTER TABLE `majors_similar_programs` ENGINE=InnoDB', 'SELECT ''ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_departments') <> 'InnoDB', 'ALTER TABLE `majors_departments` ENGINE=InnoDB', 'SELECT ''ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- new columns on majors_academic_programs
SET @cols := (SELECT GROUP_CONCAT(COLUMN_NAME) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs');
SET @sql := CONCAT('ALTER TABLE `majors_academic_programs` ',
  IF(FIND_IN_SET('basename', @cols),         'MODIFY `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT', 'ADD COLUMN `basename` VARCHAR(200) NULL'), ', ',
  IF(FIND_IN_SET('catalog_number', @cols),   'MODIFY `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT', 'ADD COLUMN `catalog_number` INT UNSIGNED NULL'), ', ',
  IF(FIND_IN_SET('credential', @cols),       'MODIFY `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT', 'ADD COLUMN `credential` VARCHAR(100) NULL'), ', ',
  IF(FIND_IN_SET('college_code', @cols),     'MODIFY `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT', 'ADD COLUMN `college_code` VARCHAR(10) NULL'), ', ',
  IF(FIND_IN_SET('status', @cols),           'MODIFY `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT', 'ADD COLUMN `status` ENUM(''active'',''retired'') NOT NULL DEFAULT ''active'''), ', ',
  IF(FIND_IN_SET('description', @cols),      'MODIFY `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT', 'ADD COLUMN `description` MEDIUMTEXT NULL'), ', ',
  IF(FIND_IN_SET('learn_how', @cols),        'MODIFY `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT', 'ADD COLUMN `learn_how` VARCHAR(255) NULL'), ', ',
  IF(FIND_IN_SET('buttons', @cols),          'MODIFY `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT', 'ADD COLUMN `buttons` TEXT NULL'), ', ',
  IF(FIND_IN_SET('image_url', @cols),        'MODIFY `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT', 'ADD COLUMN `image_url` VARCHAR(255) NULL'), ', ',
  IF(FIND_IN_SET('image_alt', @cols),        'MODIFY `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT', 'ADD COLUMN `image_alt` TEXT NULL'), ', ',
  IF(FIND_IN_SET('image_caption', @cols),    'MODIFY `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT', 'ADD COLUMN `image_caption` TEXT NULL'), ', ',
  IF(FIND_IN_SET('image_credit', @cols),     'MODIFY `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT', 'ADD COLUMN `image_credit` VARCHAR(255) NULL'), ', ',
  IF(FIND_IN_SET('college_url', @cols),      'MODIFY `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT', 'ADD COLUMN `college_url` VARCHAR(255) NULL'), ', ',
  IF(FIND_IN_SET('department_url', @cols),   'MODIFY `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT', 'ADD COLUMN `department_url` VARCHAR(255) NULL'), ', ',
  IF(FIND_IN_SET('meta_description', @cols), 'MODIFY `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT', 'ADD COLUMN `meta_description` TEXT NULL'), ', ',
  IF(FIND_IN_SET('meta_keywords', @cols),    'MODIFY `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT', 'ADD COLUMN `meta_keywords` TEXT NULL'), ', ',
  IF(FIND_IN_SET('cms_path', @cols),         'MODIFY `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT', 'ADD COLUMN `cms_path` VARCHAR(255) NULL'), ', ',
  IF(FIND_IN_SET('cms_file_date', @cols),    'MODIFY `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT', 'ADD COLUMN `cms_file_date` DATETIME NULL'), ', ',
  IF(FIND_IN_SET('imported_at', @cols),      'MODIFY `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT', 'ADD COLUMN `imported_at` DATETIME NULL'));
-- (a no-op MODIFY stands in for columns that already exist, so the statement stays valid)
SET @sql := REPLACE(@sql, 'MODIFY `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT', 'MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT');
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
