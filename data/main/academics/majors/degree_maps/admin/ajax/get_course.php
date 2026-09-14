<?php
require_once('../map_edit_functions.php');

if (!isset($_REQUEST['id']) || empty($_REQUEST['id'])) {
    echo json_encode(["error" => "Missing course ID"]);
    exit;
}

$course_id = intval($_REQUEST['id']); // Sanitize input

$course = get_course($course_id);

if (!$course) {
    echo json_encode(["error" => "Course not found"]);
    exit;
}
echo json_encode($course);

?>
