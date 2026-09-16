-- 002: remove the two double-cloned 2026-27 degree maps (decision 2026-09-14: keep the later id).
--
--   770 "Applied Engineering — Concentration in Engineering Management" BS  -> keep 965
--   789 "American Sign Language — Structure of Language Track"          BA  -> keep 791
--
-- Already applied to the sandbox copy. Run on www-test (and www after the next
-- table copy). Guarded: nothing is deleted unless the row to drop still has the
-- same major / degree type / college / year as the row we keep.

START TRANSACTION;

CREATE TEMPORARY TABLE tmp_drop_maps AS
SELECT d.id
  FROM degree_maps d
  JOIN degree_maps k
    ON k.major = d.major AND k.degree_type = d.degree_type AND k.college = d.college AND k.academic_year = d.academic_year
 WHERE (d.id = 770 AND k.id = 965)
    OR (d.id = 789 AND k.id = 791);

SELECT CONCAT('deleting ', COUNT(*), ' map(s): ', IFNULL(GROUP_CONCAT(id), 'none')) AS plan FROM tmp_drop_maps;

DELETE FROM degree_maps_courses        WHERE degree_map_id IN (SELECT id FROM tmp_drop_maps);
DELETE FROM degree_maps_footnotes      WHERE degree_map_id IN (SELECT id FROM tmp_drop_maps);
DELETE FROM degree_maps_semester_hours WHERE degree_map_id IN (SELECT id FROM tmp_drop_maps);
DELETE FROM degree_maps_year_hours     WHERE degree_map_id IN (SELECT id FROM tmp_drop_maps);
DELETE FROM degree_maps                WHERE id            IN (SELECT id FROM tmp_drop_maps);

DROP TEMPORARY TABLE tmp_drop_maps;
COMMIT;

-- Afterwards this should return no rows:
SELECT major, degree_type, college, academic_year, COUNT(*) n
  FROM degree_maps GROUP BY 1, 2, 3, 4 HAVING n > 1;
