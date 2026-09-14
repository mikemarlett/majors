<?php
require_once('../map_edit_functions.php'); 
$year = isset($_REQUEST['year']) ? intval($_REQUEST['year']) : 1;
$semester = isset($_REQUEST['semester']) ? intval($_REQUEST['semester']) : 1;
$courseCount = isset($_REQUEST['course_count']) ? intval($_REQUEST['course_count']) : 0;
$degree_map_id = isset($_REQUEST['degree_map_id']) ? intval($_REQUEST['degree_map_id']) : 0;
if (isset($_REQUEST['new_id']) && preg_match('/^new_\d+$/', $_REQUEST['new_id'])) {
    $id = $_REQUEST['new_id'];
} else {
    $id = 'new_' . time();
}
$course = [
    'id' => $id,
]; // or define defaults if needed

if (! empty($degree_map_id)) {
    $degree_map = get_map_by_id($degree_map_id);
} else {
    $degree_map = build_empty_degree_map(); 
}

$html = build_single_course_form($course, $degree_map, $year, $semester, $courseCount);

echo $html;