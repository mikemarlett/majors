<?php
require_once('../map_edit_functions.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['degree_map_id']) || empty($_POST['degree_map_id'])) {
    echo json_encode(['error' => 'Missing degree_map_id']);
    exit;
}

$degree_map_id = intval($_POST['degree_map_id']);

$query = "SELECT * FROM degree_maps WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $degree_map_id);
$stmt->execute();
$result = $stmt->get_result();
$degree_map = $result->fetch_assoc();

if (!$degree_map) {
    echo json_encode(['error' => 'Degree map not found']);
    exit;
}

// Generate dropdown options
$academic_year_options = get_academic_years_options($degree_map['academic_year']);
$college_options = get_all_colleges_options($degree_map['college']);
$department_options = get_department_options($degree_map['department'], $degree_map['college']);
$program_options = get_program_options($degree_map['program_id'], $degree_map);

echo json_encode([
    'degree_map'            => $degree_map,
    'degree_map_id'         => $degree_map['id'],
    'major'                 => $degree_map['major'],
    'degree_type'           => $degree_map['degree_type'],
    'note'                  => $degree_map['note'],
    'academic_year_options' => $academic_year_options,
    'college_options'       => $college_options,
    'department_options'    => $department_options,
    'program_options'       => $program_options
]);

$stmt->close();
exit;

/*
  'id' => NULL,
  'program_id' => NULL,
  'major' => NULL,
  'college' => NULL,
  'degree_type' => NULL,
  'department' => NULL,
  'note' => NULL,
  'academic_year' => NULL,
  'hours_to_graduate' => NULL,
  'filename' => NULL,
  'approved' => NULL,
  'approved_by' => NULL,
  'timestamp' => NULL,

*/
?>