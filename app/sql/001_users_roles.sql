-- 001: roles, netid, activity flag, college scope for majors_users.
--
-- Reference only. Run bin/migrate.php instead: it inspects the live table
-- (the www-test copy has drifted — some builds have `role`, others
-- `permission_level`) and applies only the statements that are missing.
--
-- Roles: advisor (degree maps, scoped by majors_user_colleges), advisor_admin (degree maps,
--        every college), marketing (majors pages), super_admin (everything + users), none.

ALTER TABLE `majors_users`
    ADD COLUMN `netid` CHAR(8) NULL AFTER `email`,
    ADD COLUMN `role` ENUM('advisor','advisor_admin','marketing','super_admin','none') NOT NULL DEFAULT 'none' AFTER `netid`,
    ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN `last_login_at` DATETIME NULL,
    ADD UNIQUE KEY `uq_majors_users_netid` (`netid`),
    ADD UNIQUE KEY `uq_majors_users_email` (`email`);

-- Legacy values seen in the code: role 'admin'; permission_level editor|approver|administrator.
UPDATE `majors_users` SET `role` = 'super_admin' WHERE `role` IN ('admin', 'administrator');
-- (when the column was permission_level) editor/approver -> advisor, administrator -> super_admin

-- ouauth_id sometimes held the 8-char netid; copy it across where it looks like one.
UPDATE `majors_users` SET `netid` = LOWER(`ouauth_id`)
 WHERE `netid` IS NULL AND `ouauth_id` REGEXP '^[A-Za-z][A-Za-z0-9]{2,7}$';

CREATE TABLE IF NOT EXISTS `majors_user_colleges` (
    `user_id`    INT UNSIGNED NOT NULL,
    `college_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`user_id`, `college_id`),
    KEY `idx_college` (`college_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed college scope from the existing default_college_id.
INSERT IGNORE INTO `majors_user_colleges` (`user_id`, `college_id`)
SELECT `id`, `default_college_id` FROM `majors_users` WHERE `default_college_id` > 0;

-- Indexes the viewer and the version lookup rely on.
ALTER TABLE `degree_maps`
    ADD INDEX `idx_dm_year_college` (`academic_year`, `college`),
    ADD INDEX `idx_dm_family` (`major`(100), `degree_type`(20), `college`(100)),
    ADD INDEX `idx_dm_program` (`program_id`);
