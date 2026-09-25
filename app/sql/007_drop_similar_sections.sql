-- 007: the first import stored each page's "Similar Programs" card as a section of kind
-- 'similar' as well as in majors_similar_programs. The page never rendered those rows and
-- the editor ignores them, but they sat at the last position and made "Move down" a no-op
-- on the real last section. Remove them and renumber positions. Idempotent; MySQL 8 / MariaDB 10.2+.
--   mysql formshandlerdb < /data/www/config/majors/sql/007_drop_similar_sections.sql

DELETE FROM `majors_program_sections` WHERE `kind` = 'similar';

UPDATE `majors_program_sections` s
  JOIN (SELECT `id`, ROW_NUMBER() OVER (PARTITION BY `program_id` ORDER BY `position`, `id`) AS rn FROM `majors_program_sections`) o ON o.`id` = s.`id`
   SET s.`position` = o.rn;
