-- Put a revised degree map's content onto the current-year map it replaces. The current map keeps its
-- id, so every link to it keeps working; the revised copy is removed. Run once per pair:
--   mysql formshandlerdb -e "SET @current=<current_id>, @revised=<revised_id>; source degree-maps-replace.sql"
-- @current is the map on the site now (current catalog year); @revised is the copy built in the next
-- year. Nothing changes unless both exist, sit in those years and are the same degree. Years default to
-- the app's (the catalog year starts in August); set @to_year and @from_year to override.
SET @to_year   := COALESCE(@to_year, YEAR(CURDATE()) + IF(MONTH(CURDATE()) >= 8, 1, 0));
SET @from_year := COALESCE(@from_year, @to_year + 1);
SET @ok := (SELECT COUNT(*) FROM degree_maps o JOIN degree_maps r ON r.id = @revised
             WHERE o.id = @current AND o.academic_year = @to_year AND r.academic_year = @from_year
               AND ((o.major = r.major AND o.degree_type = r.degree_type)
                    OR (r.program_id > 0 AND o.program_id = r.program_id)));
START TRANSACTION;
DELETE FROM degree_maps_courses        WHERE degree_map_id = @current AND @ok = 1;
DELETE FROM degree_maps_footnotes      WHERE degree_map_id = @current AND @ok = 1;
DELETE FROM degree_maps_semester_hours WHERE degree_map_id = @current AND @ok = 1;
DELETE FROM degree_maps_year_hours     WHERE degree_map_id = @current AND @ok = 1;
UPDATE degree_maps_courses        SET degree_map_id = @current WHERE degree_map_id = @revised AND @ok = 1;
UPDATE degree_maps_footnotes      SET degree_map_id = @current WHERE degree_map_id = @revised AND @ok = 1;
UPDATE degree_maps_semester_hours SET degree_map_id = @current WHERE degree_map_id = @revised AND @ok = 1;
UPDATE degree_maps_year_hours     SET degree_map_id = @current WHERE degree_map_id = @revised AND @ok = 1;
UPDATE degree_maps o JOIN degree_maps r ON r.id = @revised
   SET o.program_id = r.program_id, o.major = r.major, o.college = r.college, o.degree_type = r.degree_type,
       o.department = r.department, o.note = r.note, o.hours_to_graduate = r.hours_to_graduate, o.timestamp = NOW()
 WHERE o.id = @current AND @ok = 1;
DELETE FROM degree_maps WHERE id = @revised AND @ok = 1;
COMMIT;
SELECT IF(@ok = 1,
          CONCAT('Done: map ', @current, ' now has the revised content, and the copy ', @revised, ' is gone.'),
          CONCAT('Nothing changed. Check the ids: current must be a ', @to_year - 1, '-', @to_year, ' map and revised a ', @from_year - 1, '-', @from_year, ' copy of the same degree.')) AS result;
