<?php
require_once('../map_edit_functions.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$return = array(
    'success'  => false,
    'message' => 'Something went horribly wrong.',
    'data' => $_POST
);

$vars = array( 'degree_map_id', 'major', 'degree_type', 'note', 'academic_year', 'academic_year_hidden', 'college', 'department', 'program_id' );
foreach ($vars as $var){
    if (! empty($_POST[$var])){
        $$var = trim($_POST[$var]);
    }else{
        $$var = NULL;
    }
    if (empty($academic_year) && ! empty($academic_year_hidden)){
        $academic_year = $academic_year_hidden;
    }
    if (! empty($$var) && ($var == 'degree_map_id' || $var == 'academic_year' || $var == 'program_id' )){
        $$var = intval($$var);
    }
}
$mysqli->begin_transaction();
try {
    if (! empty($degree_map_id)){
        $query = "UPDATE degree_maps SET  major = ?, degree_type = ?, note = ?, academic_year = ?,  college = ?, department = ?, program_id = ?  WHERE id = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("sssissii", $major, $degree_type, $note, $academic_year, $college, $department, $program_id, $degree_map_id);
    }else{
        $query = "INSERT INTO `degree_maps` (`major`, `degree_type`, `note`, `academic_year`, `college`, `department`, `program_id`, `timestamp`) VALUES (?, ?, ?, ?, ?, ?, ?, NOW());";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("sssissi", $major, $degree_type, $note, $academic_year, $college, $department, $program_id);
    }
    if ($stmt->execute()) {
        if (empty ($degree_map_id)){
            $degree_map_id = $stmt->insert_id ?: $mysqli->insert_id;
        }
        $mysqli->commit();
        $return['success'] = true;
        $return['message'] = 'Map saved successfully.';
        $return['degree_map_id'] = $degree_map_id;
    } else {
        $return['message'] = 'Database error: ' . $stmt->error;
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    $return['message'] = 'Database error: ' . $e->getMessage();
    echo json_encode($return);
    $mysqli->rollback();
    exit;
}
$mysqli->close();


echo json_encode($return);

exit;
?>