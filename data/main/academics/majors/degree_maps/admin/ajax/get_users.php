<?php
// File: ajax/get_users.php
require_once('../map_edit_functions.php');

header('Content-Type: application/json');

$colleges = get_all_colleges_array();
$departments = get_departments_array();

try {
    $query = "SELECT * FROM `majors_users` ORDER BY `last_name`, `first_name`";
    $result = $mysqli->query($query);
    if (!$result) {
        throw new Exception("Database query failed: " . $mysqli->error);
    }
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        // Optionally, you might want to cast numeric fields
        $row['id'] = (int)$row['id'];
        $row['default_college_id'] = (int)$row['default_college_id'];
        $row['default_department_id'] = (int)$row['default_department_id'];
        if (! empty($row['default_college_id'])) {
            $row['college'] = $colleges[$row['default_college_id']]['name'];
        } else {
            $row['college'] = '';
        }
        if (! empty($row['default_department_id'])) {
            $row['department'] = $departments[$row['default_department_id']]['department'];
        } else {
            $row['department'] = '';
        }
        $users[] = $row;
    }
    
    echo json_encode([
        'status' => 'success',
        'users'  => $users
    ]);
} catch (Exception $ex) {
    echo json_encode([
        'status'  => 'error',
        'message' => $ex->getMessage()
    ]);
}
exit;