-- 008: listing entries (which listing pages a program appears on and under which names),
-- full-width sections with a background, the Similar Programs background photo (current design),
-- more than one department per program, page-name history (old addresses keep forwarding) and
-- forwarding a retired program to another one. Idempotent: safe to re-run.
--   mysql formshandlerdb < /data/www/config/majors/sql/008_listings_and_more.sql
-- Then bring today's hand-kept CMS listings in (replaces the starting entries seeded below):
--   mysql formshandlerdb < majors-listings-YYYYMMDD.sql          (generated on the sandbox)
--   or: cd /data/www/config/majors && MAJORS_SITE=www-test php bin/majors-listing-import.php --dry-run

-- One row per line a program shows on the listing pages. A program can have several
-- (concentrations, "Public Health Practice, Advanced"-style A-Z cross references); none = not listed.
CREATE TABLE IF NOT EXISTS `majors_listing_entries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `program_id` INT UNSIGNED NOT NULL,
  `position` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `name` VARCHAR(255) NULL,                 -- NULL: the program's own name
  `detail` VARCHAR(500) NULL,               -- NULL: the degree written out from the program type
  `lists` SET('all','undergrad','graduate','online','certificates','badges') NOT NULL DEFAULT '',
  `shown_in` ENUM('both','az','college') NOT NULL DEFAULT 'both',
  `cert_section` ENUM('graduate','undergraduate') NULL,   -- Certificates page: which half
  `cert_topics` VARCHAR(255) NULL,                        -- Certificates page: topic(s), "|"-separated (Education, Health…)
  `source` ENUM('seed','cms','editor') NOT NULL DEFAULT 'editor',
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`), KEY `idx_listing_program` (`program_id`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Earlier page names: a request for one forwards (301) to the program's current address.
CREATE TABLE IF NOT EXISTS `majors_program_aliases` (
  `basename` VARCHAR(200) NOT NULL,
  `program_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`basename`), KEY `idx_alias_program` (`program_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'similar_bg_url') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `similar_bg_url` VARCHAR(255) NULL', 'SELECT ''similar_bg_url: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'more_departments') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `more_departments` TEXT NULL', 'SELECT ''more_departments: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_academic_programs' AND COLUMN_NAME = 'forward_to') = 0,
  'ALTER TABLE `majors_academic_programs` ADD COLUMN `forward_to` INT UNSIGNED NULL', 'SELECT ''forward_to: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'majors_program_sections' AND COLUMN_NAME = 'theme') = 0,
  'ALTER TABLE `majors_program_sections` ADD COLUMN `theme` VARCHAR(20) NULL', 'SELECT ''theme: ok''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Starting entries, only while the table is empty (first run): every active program listed under
-- its own name on the lists its credential implies, the way the CMS lists treat each credential.
-- The CMS listing import then replaces these with the hand-kept lists.
INSERT INTO `majors_listing_entries` (`program_id`, `position`, `name`, `detail`, `lists`, `shown_in`, `cert_section`, `source`, `updated_at`)
SELECT p.`id`, 1, NULL, NULL,
       CONCAT_WS(',', 'all',
         CASE WHEN p.`credential` LIKE '%Certificate%' OR p.`credential` IN ('Endorsement', 'Practicum Placement') OR (COALESCE(p.`credential`, '') = '' AND COALESCE(p.`certificate`, 0) = 1) THEN 'certificates'
              WHEN p.`credential` LIKE '%Badge%' OR (COALESCE(p.`credential`, '') = '' AND COALESCE(p.`badge`, 0) = 1) THEN 'badges'
              WHEN p.`credential` IN ('Master''s', 'Doctorate', 'Postbaccalaureate', 'Post Master', 'Graduate Emphasis') OR (COALESCE(p.`credential`, '') = '' AND COALESCE(p.`graduate`, 0) = 1) THEN 'graduate'
              ELSE 'undergrad' END,
         IF(COALESCE(p.`online_learning`, 0) = 1 OR COALESCE(p.`online_only`, 0) = 1, 'online', NULL)),
       'both',
       CASE WHEN p.`credential` LIKE '%Certificate%' OR p.`credential` IN ('Endorsement', 'Practicum Placement') OR (COALESCE(p.`credential`, '') = '' AND COALESCE(p.`certificate`, 0) = 1)
            THEN IF(p.`credential` LIKE 'Undergraduate%' OR (COALESCE(p.`credential`, '') = '' AND COALESCE(p.`graduate`, 0) = 0), 'undergraduate', 'graduate') END,
       'seed', NOW()
  FROM `majors_academic_programs` p
 WHERE p.`status` = 'active'
   AND NOT EXISTS (SELECT 1 FROM `majors_listing_entries` x);
