-- 010: the program pages' <title>. Each CMS program page had a title like
-- "Details: Accountancy, Master's, BUS", and analytics reports key on it. The column keeps the
-- CMS's exact string (a few have a stray space before a comma); the app uses it while it still
-- agrees with the program's name, credential and college code, and otherwise builds the same
-- pattern from those fields (new and renamed programs). Idempotent: safe to re-run.
--   mysql formshandlerdb < /data/www/config/majors/sql/010_page_titles.sql
-- Then load the titles: majors-page-titles-YYYYMMDD.sql (generated from the CMS on the sandbox).
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'page_title') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `page_title` VARCHAR(255) NULL', 'SELECT ''page_title: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
