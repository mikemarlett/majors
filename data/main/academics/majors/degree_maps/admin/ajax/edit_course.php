<?php
require_once('../map_edit_functions.php');

header('Content-Type: application/json');

$order = !empty($_REQUEST['order']) ? intval($_REQUEST['order']) : null;
$semester = !empty($_REQUEST['semester']) ? intval($_REQUEST['semester']) : null;
$year = !empty($_REQUEST['year']) ? intval($_REQUEST['year']) : null;

if (!empty($_REQUEST['degree_map_id'])) {
    $degree_map_id = intval($_REQUEST['degree_map_id']);
    $degree_map = get_map_by_id($degree_map_id);
}

if (!empty($_REQUEST['id'])) {
    $course_id = intval($_REQUEST['id']); // Sanitize input
    $course = get_course($course_id);
} elseif (!empty($degree_map_id)) {
    $course = get_blank_course('new', $degree_map, $order, $semester, $year);
} else {
    echo json_encode(["error" => "Missing course ID and degree_map_id"]);
    exit;
}

if ($course['footnote_ids'] == '[]'){
	$course['footnote_ids'] = NULL;
}
if ($course['extra'] == '0'){
	$course['extra'] = NULL;
}

$modal = build_single_course_form($course, $degree_map, $year, $semester);
echo json_encode(['modal' => $modal]);

exit;