<?php
require_once '../map_edit_functions.php'; 
error_log("POST Data: " . print_r($_POST, true));
// Tell the browser we’re returning JSON.
header('Content-Type: application/json');

// Make sure we’re handling a POST request.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
// Collect and sanitize header data.
$degree_map_id       = isset($_POST['degree_map_id']) ? intval($_POST['degree_map_id']) : 0;
$major        = isset($_POST['major']) ? trim($_POST['major']) : '';
$degree_type  = isset($_POST['degree_type']) ? trim($_POST['degree_type']) : '';
$college      = isset($_POST['college']) ? trim($_POST['college']) : '';
$department   = isset($_POST['department']) ? trim($_POST['department']) : '';
$note         = isset($_POST['note']) ? trim($_POST['note']) : '';
$academic_year = isset($_POST['academicyear']) ? intval($_POST['academicyear']) : 0;
$program_id  = isset($_POST['program_id']) ? (int)$_POST['program_id'] : 0;
$hours_to_graduate = isset($_POST['hours_to_graduate']) ? strval(trim($_POST['hours_to_graduate'])) : '';


$mysqli->begin_transaction();

try {
    // If there's no degree_map_id, then insert a new degree map header.
    if ($degree_map_id === 0) {
        $sqlInsertMap = "INSERT INTO `degree_maps` 
                           (`major`, `degree_type`, `college`, `department`, `note`, `academic_year`, `program_id`, `hours_to_graduate`, `timestamp`)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        if (!($stmtMap = $mysqli->prepare($sqlInsertMap))) {
            throw new Exception("Prepare (insert degree map) failed: " . $mysqli->error);
        }
        $stmtMap->bind_param('sssssiis', $major, $degree_type, $college, $department, $note, $academic_year, $program_id, $hours_to_graduate);
        if (!$stmtMap->execute()) {
            throw new Exception("Insert degree map failed: " . $stmtMap->error);
        }
        // Get the new degree map ID.
        $degree_map_id = $mysqli->insert_id;
        $stmtMap->close();
    } else {
        // For an existing degree map, update the header.
        $sqlUpdateMap = "UPDATE `degree_maps` 
                         SET `major` = ?, `degree_type` = ?, `college` = ?, `department` = ?, `note` = ?, `academic_year` = ?, `program_id` = ?, `hours_to_graduate` = ?, `timestamp` = NOW()
                         WHERE `id` = ?";
        if (!($stmtUpdateMap = $mysqli->prepare($sqlUpdateMap))) {
            throw new Exception("Prepare (update degree map) failed: " . $mysqli->error);
        }
        $stmtUpdateMap->bind_param('sssssiisi', $major, $degree_type, $college, $department, $note, $academic_year, $program_id, $hours_to_graduate, $degree_map_id);
        if (!$stmtUpdateMap->execute()) {
            throw new Exception("Update degree map failed: " . $stmtUpdateMap->error);
        }
        $stmtUpdateMap->close();
    }

    // === Now continue processing courses as before ===
    if (isset($_POST['course']) && is_array($_POST['course'])) {
        foreach ($_POST['course'] as $courseId => $courseData) {
            // Convert the footnote_ids array (if set) into JSON.
            $footnotes_json = null;
            if (isset($courseData['footnote_ids']) && is_array($courseData['footnote_ids'])) {
                $footnotes_json = json_encode($courseData['footnote_ids']);
            }

            if (strpos($courseId, 'new_') === 0) {
                // Calculate the next order for new courses.
                $sqlMax = "SELECT MAX(`order`) AS max_order 
                           FROM degree_maps_courses 
                           WHERE degree_map_id = ? AND year = ? AND semester = ?";
                if (!($stmtMax = $mysqli->prepare($sqlMax))) {
                    throw new Exception("Prepare (max order) failed: " . $mysqli->error);
                }
                $stmtMax->bind_param('iii', $degree_map_id, $courseData['year'], $courseData['semester']);
                $stmtMax->execute();
                $resultMax = $stmtMax->get_result();
                $rowMax = $resultMax->fetch_assoc();
                $nextOrder = $rowMax['max_order'] ? $rowMax['max_order'] + 1 : 1;
                $stmtMax->close();

                // Insert the new course.
                $sqlInsert = "INSERT INTO `degree_maps_courses` 
                (`degree_map_id`, `course_info`, `hours`, `footnote_ids`, `sge`, `extra`, `order`, `semester`, `year`, `scbcrse_crse_numb`, `scbcrse_subj_code`, `timestamp`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                if (!($stmtInsert = $mysqli->prepare($sqlInsert))) {
                    throw new Exception("Prepare (insert course) failed: " . $mysqli->error);
                }
                $stmtInsert->bind_param(
                    'isssssiiiss',
                    $degree_map_id,
                    $courseData['course_info'],
                    $courseData['hours'],
                    $footnotes_json,
                    $courseData['sge'],
                    $courseData['extra'],
                    $nextOrder,
                    $courseData['semester'],
                    $courseData['year'],
                    $courseData['scbcrse_crse_numb'],
                    $courseData['scbcrse_subj_code']
                
                );
                if (!$stmtInsert->execute()) {
                    throw new Exception("Insert course execute failed: " . $stmtInsert->error);
                }
                $stmtInsert->close();
            } else {
                // Update an existing course.
                $sqlUpdate = "UPDATE `degree_maps_courses` 
                    SET `course_info` = ?, `hours` = ?, `footnote_ids` = ?, `sge` = ?, `extra` = ?, `order` = ?, `semester` = ?, `year` = ?, `scbcrse_crse_numb` = ?, `scbcrse_subj_code` = ?, `timestamp` = NOW()
                    WHERE `id` = ? AND `degree_map_id` = ?";
                if (!($stmtUpdate = $mysqli->prepare($sqlUpdate))) {
                    throw new Exception("Prepare (update course) failed: " . $mysqli->error);
                }
                $stmtUpdate->bind_param(
                    'sssssiiissii',
                    $courseData['course_info'],
                    $courseData['hours'],
                    $footnotes_json,
                    $courseData['sge'],
                    $courseData['extra'],
                    $courseData['order'],
                    $courseData['semester'],
                    $courseData['year'],
                    $courseData['scbcrse_crse_numb'],
                    $courseData['scbcrse_subj_code'],
                    $courseId,
                    $degree_map_id
                );
                if (!$stmtUpdate->execute()) {
                    throw new Exception("Update course execute failed: " . $stmtUpdate->error);
                }
                $stmtUpdate->close();
            }
        }
    }

    // --- Save Semester Hours ---
    if (isset($_POST['degree_map']['hours']) && is_array($_POST['degree_map']['hours'])) {
        // Loop through each year (the keys in the hours array are the year numbers)
        foreach ($_POST['degree_map']['hours'] as $year => $data) {
            // For each semester: keys "1", "2", and "3" (assuming these represent Fall, Spring, Summer)
            foreach (['1', '2', '3'] as $semester) {
                if (isset($data[$semester])) {
                    $hours = trim($data[$semester]);
                    if ($hours === '') {
                        // If empty, delete any existing record for this combination.
                        if ($stmt = $mysqli->prepare("DELETE FROM `degree_maps_semester_hours` WHERE `degree_map_id` = ? AND `year` = ? AND `semester` = ?")) {
                            $stmt->bind_param('iii', $degree_map_id, $year, $semester);
                            $stmt->execute();
                            $stmt->close();
                        }
                    } else {
                        // Otherwise, use REPLACE to update or insert the row.
                        if ($stmt = $mysqli->prepare("REPLACE INTO `degree_maps_semester_hours` (`degree_map_id`, `year`, `semester`, `hours`, `timestamp`) VALUES (?, ?, ?, ?, NOW())")) {
                            // Note: if hours is sometimes a range (like "2-4"), it should be bound as a string.
                            $stmt->bind_param('iiis', $degree_map_id, $year, $semester, $hours);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
            }
        }
    }

    // --- Save Year Hours ---
    if (isset($_POST['degree_map']['hours']) && is_array($_POST['degree_map']['hours'])) {
        // Loop through each year to process the 'total_hours' row.
        foreach ($_POST['degree_map']['hours'] as $year => $data) {
            if (isset($data['total_hours'])) {
                $totalHours = trim($data['total_hours']);
                if ($totalHours === '') {
                    // If the total is empty, delete any record.
                    if ($stmt = $mysqli->prepare("DELETE FROM `degree_maps_year_hours` WHERE `degree_map_id` = ? AND `year` = ?")) {
                        $stmt->bind_param('ii', $degree_map_id, $year);
                        $stmt->execute();
                        $stmt->close();
                    }
                } else {
                    // Otherwise, insert or update via REPLACE.
                    if ($stmt = $mysqli->prepare("REPLACE INTO `degree_maps_year_hours` (`degree_map_id`, `year`, `hours`, `timestamp`) VALUES (?, ?, ?, NOW())")) {
                        // Bind as string if totals can be ranges, or integer if they are strictly numeric.
                        $stmt->bind_param('iis', $degree_map_id, $year, $totalHours);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
        }
    }

    // Process footnotes updates/inserts.
    if (isset($_POST['footnotes']) && is_array($_POST['footnotes'])) {
        foreach ($_POST['footnotes'] as $footnoteId => $footnoteData) {
            // Trim note and cast order (if needed)
            $order = isset($footnoteData['order']) ? (int)$footnoteData['order'] : 1;
            $note  = isset($footnoteData['note']) ? trim($footnoteData['note']) : '';

            if (strpos($footnoteId, 'new_') === 0) {
                // Insert a new footnote.
                $sqlInsertFootnote = "INSERT INTO `degree_maps_footnotes` 
                    (`degree_map_id`, `order`, `note`, `timestamp`)
                    VALUES (?, ?, ?, NOW())";
                if (!($stmtInsertFootnote = $mysqli->prepare($sqlInsertFootnote))) {
                    throw new Exception("Prepare (insert footnote) failed: " . $mysqli->error);
                }
                $stmtInsertFootnote->bind_param("iis", $degree_map_id, $order, $note);
                if (!$stmtInsertFootnote->execute()) {
                    throw new Exception("Insert footnote failed: " . $stmtInsertFootnote->error);
                }
                $stmtInsertFootnote->close();
            } else {
                // Update an existing footnote.
                $sqlUpdateFootnote = "UPDATE `degree_maps_footnotes` 
                    SET `order` = ?, `note` = ? 
                    WHERE `id` = ? AND `degree_map_id` = ?";
                if (!($stmtUpdateFootnote = $mysqli->prepare($sqlUpdateFootnote))) {
                    throw new Exception("Prepare (update footnote) failed: " . $mysqli->error);
                }
                $stmtUpdateFootnote->bind_param("isii", $order, $note, $footnoteId, $degree_map_id);
                if (!$stmtUpdateFootnote->execute()) {
                    throw new Exception("Update footnote failed: " . $stmtUpdateFootnote->error);
                }
                $stmtUpdateFootnote->close();
            }
        }
    }
    // Process removal of footnotes.
    if (isset($_POST['remove_footnotes']) && is_array($_POST['remove_footnotes'])) {
        // Make sure all IDs are integers.
        $removeFootnotes = array_map('intval', $_POST['remove_footnotes']);
        if (count($removeFootnotes) > 0) {
            // Create a list of placeholders, one per footnote id.
            $placeholders = implode(',', array_fill(0, count($removeFootnotes), '?'));
            // Build the DELETE SQL. We also check that the footnote belongs to the current degree map.
            $sqlDeleteFootnotes = "DELETE FROM `degree_maps_footnotes` WHERE `id` IN ($placeholders) AND `degree_map_id` = ?";
            
            if (!($stmtDelete = $mysqli->prepare($sqlDeleteFootnotes))) {
                throw new Exception("Prepare (delete footnotes) failed: " . $mysqli->error);
            }
            
            // Build the types string: one 'i' for each footnote id, plus one for the degree_map_id.
            $types = str_repeat('i', count($removeFootnotes)) . 'i';
            // Merge the footnote ids and the current degree map id into one array.
            $params = array_merge($removeFootnotes, [$degree_map_id]);
            
            // Bind the parameters dynamically.
            $stmtDelete->bind_param($types, ...$params);
            if (!$stmtDelete->execute()) {
                throw new Exception("Delete footnotes failed: " . $stmtDelete->error);
            }
            $stmtDelete->close();
        }
    }    
    // Process removals of courses.
    if (isset($_POST['remove_course_ids']) && is_array($_POST['remove_course_ids'])) {
        $remove_ids = array_map('intval', $_POST['remove_course_ids']);
        if (count($remove_ids) > 0) {
            $placeholders = implode(',', array_fill(0, count($remove_ids), '?'));
            $sqlDelete = "DELETE FROM `degree_maps_courses` WHERE `id` IN ($placeholders) AND `degree_map_id` = ?";
            if (!($stmtDelete = $mysqli->prepare($sqlDelete))) {
                throw new Exception("Prepare (delete courses) failed: " . $mysqli->error);
            }
            $types = str_repeat('i', count($remove_ids)) . 'i';
            $params = array_merge($remove_ids, [$degree_map_id]);
            $stmtDelete->bind_param($types, ...$params);
            if (!$stmtDelete->execute()) {
                throw new Exception("Deletion failed: " . $stmtDelete->error);
            }
            $stmtDelete->close();
        }
    }

    echo json_encode([
        'status'  => 'success',
        'message' => 'Degree map saved successfully!'
    ]);
} catch (Exception $ex) {
    $mysqli->rollback();
    echo json_encode([
        'status'  => 'error',
        'message' => $ex->getMessage()
    ]);
}
exit;
}