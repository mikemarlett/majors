-- 003: the whole schema migration as plain SQL, for the mysql client when PHP
-- is not handy. Same result as bin/migrate.php. Idempotent: every structural
-- change is guarded by an information_schema check, so it can be re-run and
-- can finish a run that stopped half-way.
--
--   mysql formshandlerdb < /data/www/config/majors/sql/003_migration_plain.sql
--
-- Roles: advisor (degree maps, scoped by majors_user_colleges), advisor_admin
-- (degree maps, every college), marketing (majors pages), super_admin
-- (everything + users), none.

-- ---------------------------------------------------------------- majors_users
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_users' AND COLUMN_NAME = 'netid') = 0,
  'ALTER TABLE `majors_users` ADD COLUMN `netid` CHAR(8) NULL AFTER `email`', 'SELECT ''netid: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- role: add it if missing, then widen to text so legacy values (or an ENUM
-- that lacks the new names) can be normalized without truncation errors.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_users' AND COLUMN_NAME = 'role') = 0,
  'ALTER TABLE `majors_users` ADD COLUMN `role` VARCHAR(32) NULL AFTER `netid`', 'SELECT ''role: present''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

ALTER TABLE `majors_users` MODIFY COLUMN `role` VARCHAR(32) NULL;

-- Older builds kept the role in permission_level (editor|approver|administrator).
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_users' AND COLUMN_NAME = 'permission_level') = 1,
  'UPDATE `majors_users` SET `role` = `permission_level` WHERE `role` IS NULL OR `role` = ''''', 'SELECT ''permission_level: none''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE `majors_users` SET `role` = 'super_admin' WHERE LOWER(`role`) IN ('admin', 'administrator');
UPDATE `majors_users` SET `role` = 'advisor'     WHERE LOWER(`role`) IN ('editor', 'approver');
UPDATE `majors_users` SET `role` = 'none'        WHERE `role` IS NULL OR `role` NOT IN ('advisor', 'advisor_admin', 'marketing', 'super_admin', 'none');

ALTER TABLE `majors_users` MODIFY COLUMN `role` ENUM('advisor','advisor_admin','marketing','super_admin','none') NOT NULL DEFAULT 'none';

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_users' AND COLUMN_NAME = 'is_active') = 0,
  'ALTER TABLE `majors_users` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1', 'SELECT ''is_active: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_users' AND COLUMN_NAME = 'last_login_at') = 0,
  'ALTER TABLE `majors_users` ADD COLUMN `last_login_at` DATETIME NULL', 'SELECT ''last_login_at: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_users' AND COLUMN_NAME = 'created_at') = 0,
  'ALTER TABLE `majors_users` ADD COLUMN `created_at` DATETIME NULL', 'SELECT ''created_at: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_users' AND COLUMN_NAME = 'updated_at') = 0,
  'ALTER TABLE `majors_users` ADD COLUMN `updated_at` DATETIME NULL', 'SELECT ''updated_at: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- A netid that was stored in ouauth_id moves to the netid column.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_users' AND COLUMN_NAME = 'ouauth_id') = 1,
  'UPDATE `majors_users` SET `netid` = LOWER(`ouauth_id`) WHERE `netid` IS NULL AND `ouauth_id` REGEXP ''^[A-Za-z][A-Za-z0-9]{2,7}$''', 'SELECT ''ouauth_id: none''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_users' AND INDEX_NAME = 'uq_majors_users_netid') = 0,
  'ALTER TABLE `majors_users` ADD UNIQUE KEY `uq_majors_users_netid` (`netid`)', 'SELECT ''uq_majors_users_netid: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Unique email: skipped (with a notice) while duplicate emails exist.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_users' AND INDEX_NAME = 'uq_majors_users_email') = 1,
  'SELECT ''uq_majors_users_email: ok''',
  IF((SELECT COUNT(*) FROM (SELECT `email` FROM `majors_users` GROUP BY `email` HAVING COUNT(*) > 1) d) = 0,
     'ALTER TABLE `majors_users` ADD UNIQUE KEY `uq_majors_users_email` (`email`)',
     'SELECT ''uq_majors_users_email: SKIPPED, duplicate emails present (fix them, then re-run)'' AS notice'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- --------------------------------------------------------- majors_user_colleges
CREATE TABLE IF NOT EXISTS `majors_user_colleges` (
  `user_id` INT UNSIGNED NOT NULL, `college_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`, `college_id`), KEY `idx_college` (`college_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_users' AND COLUMN_NAME = 'default_college_id') = 1,
  'INSERT IGNORE INTO `majors_user_colleges` (`user_id`, `college_id`) SELECT `id`, `default_college_id` FROM `majors_users` WHERE `default_college_id` > 0', 'SELECT ''default_college_id: none''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- --------------------------------------------------- degree_maps_semester_hours
-- Never had a unique key, so the old REPLACE INTO saves piled up duplicate
-- rows. Keep the newest row per (map, year, semester), then add the key.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'degree_maps_semester_hours' AND INDEX_NAME = 'uq_sem_hours') = 0,
  'DELETE h FROM `degree_maps_semester_hours` h
     JOIN (SELECT `degree_map_id`, `year`, `semester`, MAX(`id`) AS keep_id FROM `degree_maps_semester_hours` GROUP BY 1, 2, 3 HAVING COUNT(*) > 1) k
       ON k.`degree_map_id` = h.`degree_map_id` AND k.`year` = h.`year` AND k.`semester` = h.`semester` AND h.`id` <> k.keep_id', 'SELECT ''uq_sem_hours: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'degree_maps_semester_hours' AND INDEX_NAME = 'uq_sem_hours') = 0,
  'ALTER TABLE `degree_maps_semester_hours` ADD UNIQUE KEY `uq_sem_hours` (`degree_map_id`, `year`, `semester`)', 'SELECT ''uq_sem_hours: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ------------------------------------------------------------------ degree_maps
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'degree_maps' AND INDEX_NAME = 'idx_dm_year_college') = 0,
  'ALTER TABLE `degree_maps` ADD INDEX `idx_dm_year_college` (`academic_year`, `college`)', 'SELECT ''idx_dm_year_college: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'degree_maps' AND INDEX_NAME = 'idx_dm_family') = 0,
  'ALTER TABLE `degree_maps` ADD INDEX `idx_dm_family` (`major`(100), `degree_type`(20), `college`(100))', 'SELECT ''idx_dm_family: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'degree_maps' AND INDEX_NAME = 'idx_dm_program') = 0,
  'ALTER TABLE `degree_maps` ADD INDEX `idx_dm_program` (`program_id`)', 'SELECT ''idx_dm_program: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT `id`, `email`, `netid`, `role`, `is_active` FROM `majors_users` ORDER BY `id`;
