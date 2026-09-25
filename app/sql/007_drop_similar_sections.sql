-- 007: the first import stored each page's "Similar Programs" card as a section of kind
-- 'similar' as well as in majors_similar_programs. The page never rendered those rows and
-- the editor ignores them, but they sat at the last position and made "Move down" a no-op
-- on the real last section. Remove them and renumber positions. Idempotent.
--   mysql formshandlerdb < /data/www/config/majors/sql/007_drop_similar_sections.sql

DELETE FROM `majors_program_sections` WHERE `kind` = 'similar';

SET @pid := 0, @pos := 0;
UPDATE `majors_program_sections` s
  JOIN (SELECT `id`, (@pos := IF(@pid = `program_id`, @pos + 1, 1)) AS new_pos, (@pid := `program_id`) AS pid
          FROM `majors_program_sections` ORDER BY `program_id`, `position`, `id`) o ON o.`id` = s.`id`
   SET s.`position` = o.new_pos;
