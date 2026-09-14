<?php
require_once('../map_edit_functions.php');

header('Content-Type: application/json');

$degree_map_id = isset($_REQUEST['degree_map_id']) ? intval($_REQUEST['degree_map_id']) : 0;

if (!$degree_map_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid map ID']);
    exit;
}

$courses = get_degree_maps_courses($degree_map_id);

if ($courses) {
    echo json_encode(['success' => true, 'courses' => $courses]);
} else {
    echo json_encode(['success' => false, 'message' => 'No courses found']);
}
