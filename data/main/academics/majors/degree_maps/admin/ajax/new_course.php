<?php
require_once('../map_edit_functions.php');

if (!isset($_REQUEST['degree_map_id'], $_REQUEST['year'], $_REQUEST['semester'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$degree_map_id = (int) $_REQUEST['degree_map_id'];
$year = (int) $_REQUEST['year'];
$semester = (int) $_REQUEST['semester'];

// Get the highest current order in this map, year, and semester
$query = "
    SELECT COALESCE(MAX(`order`), 0) AS `max_order` 
    FROM `degree_maps_courses` 
    WHERE `degree_map_id` = ? AND `year` = ? AND `semester` = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("iii", $degree_map_id, $year, $semester);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

// Set the default order one past the highest
$new_order = $row['max_order'] + 1;

// Return the new course data (not stored yet)
$new_course = [
    'id' => 'new_' . time(),
    'degree_map_id' => $degree_map_id,
    'year' => $year,
    'semester' => $semester,
    'order' => $new_order,
    'course_info' => '',
    'hours' => '',
    'scbcrse_subj_code' => '',
    'scbcrse_crse_numb' => '',
    'extra' => '',
    'footnote_ids' => []
];

echo json_encode(['success' => true, 'course' => $new_course]);
exit;