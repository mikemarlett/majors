<?php
require_once('../map_edit_functions.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Validate required fields
$required_fields = ['course_id', 'degree_map_id', 'course_info', 'hours', 'year', 'semester', 'order'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field])) {
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}
$return = array(
    'success'  => false,
    'message' => 'Something went horribly wrong.',
    'data' => $_POST
);

/*
`degree_maps_courses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `degree_map_id` int NOT NULL,
  `course_info` text NOT NULL,
  `hours` varchar(10) DEFAULT NULL,
  `year` tinyint(1) NOT NULL,
  `semester` tinyint(1) NOT NULL,
  `order` tinyint DEFAULT NULL,
  `footnote_ids` varchar(256) DEFAULT NULL,
  `sge` varchar(256) DEFAULT NULL,
  `extra` text,
  `scbcrse_subj_code` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `scbcrse_crse_numb` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `degree_map_id` (`degree_map_id`)
) ENGINE=InnoDB AUTO_INCREMENT=28231 DEFAULT CHARSET=utf8mb3;
*/
// Assign posted values with sanitization
$course_id = intval($_POST['course_id']);
$degree_map_id = intval($_POST['degree_map_id']);
$course_info = trim($_POST['course_info']);
$hours = trim($_POST['hours']);
$year = intval($_POST['year']);
$semester = intval($_POST['semester']);
$order = isset($_POST['order']) ? intval($_POST['order']) : NULL;
$footnote_ids = !empty($_POST['footnotes']) ? json_encode($_POST['footnotes']) : NULL;
$sge = !empty($_POST['sge']) ? trim($_POST['sge']) : NULL;
$extra = !empty($_POST['extra']) ? trim($_POST['extra']) : NULL;
$scbcrse_subj_code = !empty($_POST['scbcrse_subj_code']) ? trim($_POST['scbcrse_subj_code']) : NULL;
$scbcrse_crse_numb = !empty($_POST['scbcrse_crse_numb']) ? trim($_POST['scbcrse_crse_numb']) : NULL;

if ($footnote_ids == '[]') {
    $footnote_ids = NULL;
}
if ($extra == '0') {
    $extra = NULL;
}

if ($course_id > 0) {
    // Update an existing course
    $query = "UPDATE `degree_maps_courses` 
              SET `course_info` = ?, `hours` = ?, `year` = ?, `semester` = ?, `order` = ?, `footnote_ids` = ?, 
                  `sge` = ?, `extra` = ?, `scbcrse_subj_code` = ?, `scbcrse_crse_numb` = ?, `timestamp` = NOW() 
              WHERE id = ?";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ssiiisssssi", $course_info, $hours, $year, $semester, $order, 
                      $footnote_ids, $sge, $extra, $scbcrse_subj_code, $scbcrse_crse_numb, $course_id);
} else {
    // Insert a new course
    $query = "INSERT INTO `degree_maps_courses`
              (`degree_map_id`, `course_info`, `hours`, `year`, `semester`, `order`, `footnote_ids`, `sge`, `extra`, 
               `scbcrse_subj_code`, `scbcrse_crse_numb`, `timestamp`) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW() )";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("issiiisssss", $degree_map_id, $course_info, $hours, $year, $semester, 
                      $order, $footnote_ids, $sge, $extra, $scbcrse_subj_code, $scbcrse_crse_numb);
    $course_id = $stmt->insert_id ?: $mysqli->insert_id;
}
$return['course_id'] = $course_id;

if ($stmt->execute()) {
    $mysqli->commit();
    //clean up the order of the courses
    $return['success'] = reorder_semester_courses($degree_map_id, $year, $semester);
} else {
    $return['message'] ='Database error: ' . $stmt->error;
}
$stmt->close();

echo json_encode($return);
exit;
?>