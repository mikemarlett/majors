-- Read-only. Revised degree maps waiting in next year's catalog, and the current-year map each one
-- would replace (advisors cannot edit a published year, so a revision is built as a copy in the next one).
--   mysql formshandlerdb -t < degree-maps-revisions.sql
-- Years default to the app's: the current catalog year starts in August. Override with
--   mysql formshandlerdb -t -e "SET @to_year=2027, @from_year=2028; source degree-maps-revisions.sql"
SET @to_year   := COALESCE(@to_year, YEAR(CURDATE()) + IF(MONTH(CURDATE()) >= 8, 1, 0));
SET @from_year := COALESCE(@from_year, @to_year + 1);
SELECT r.id AS revised_id,
       CONCAT(r.academic_year - 1, '-', r.academic_year) AS built_in,
       r.major, r.degree_type, r.college,
       DATE(r.timestamp) AS last_saved,
       o.id AS current_id,
       CONCAT(@to_year - 1, '-', @to_year) AS replaces_in
  FROM degree_maps r
  LEFT JOIN degree_maps o
         ON o.academic_year = @to_year AND o.id <> r.id
        AND ((o.major = r.major AND o.degree_type = r.degree_type AND o.college = r.college)
             OR (r.program_id > 0 AND o.program_id = r.program_id))
 WHERE r.academic_year = @from_year
 ORDER BY r.timestamp DESC, r.id;
