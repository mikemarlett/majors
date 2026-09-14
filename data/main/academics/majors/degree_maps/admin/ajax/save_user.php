<?php
// File: ajax/save_user.php
require_once($_SERVER['DOCUMENT_ROOT'].'/../config/functions.php'); 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

try {
    // Collect and sanitize POST data.
    $user_id     = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
    $first_name  = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name   = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $email       = isset($_POST['email']) ? trim($_POST['email']) : '';
    $permission_level  = isset($_POST['permission_level']) ? trim($_POST['permission_level']) : '';
    $college     = isset($_POST['college']) ? trim($_POST['college']) : '';
    $department  = isset($_POST['department']) ? trim($_POST['department']) : '';

    // Validate required fields.
    if (empty($first_name) || empty($last_name) || empty($email) || empty($permission_level)) {
        throw new Exception("Missing required fields.");
    }

    // Convert college and department to integers
    $default_college_id = intval($college);
    $default_department_id = intval($department);

    if (empty($user_id)) {
        // Insert new user
        $sql = "INSERT INTO majors_users (first_name, last_name, email, permission_level, default_college_id, default_department_id) 
                VALUES (?, ?, ?, ?, ?, ?)";
        if (!($stmt = $mysqli->prepare($sql))) {
            throw new Exception("Prepare failed: " . $mysqli->error);
        }
        $stmt->bind_param("ssssii", $first_name, $last_name, $email, $permission_level, $default_college_id, $default_department_id);
        if (!$stmt->execute()) {
            throw new Exception("Insert failed: " . $stmt->error);
        }
        $stmt->close();
    } else {
        // Update existing user
        $sql = "UPDATE majors_users 
                SET first_name = ?, last_name = ?, email = ?, permission_level = ?, default_college_id = ?, default_department_id = ?
                WHERE id = ?";
        if (!($stmt = $mysqli->prepare($sql))) {
            throw new Exception("Prepare failed: " . $mysqli->error);
        }
        $user_id_int = intval($user_id);
        $stmt->bind_param("ssssiii", $first_name, $last_name, $email, $permission_level, $default_college_id, $default_department_id, $user_id_int);
        if (!$stmt->execute()) {
            throw new Exception("Update failed: " . $stmt->error);
        }
        $stmt->close();
    }

    echo json_encode([
        'status'  => 'success',
        'message' => 'User saved successfully!'
    ]);
} catch (Exception $ex) {
    echo json_encode([
        'status'  => 'error',
        'message' => $ex->getMessage()
    ]);
}
exit;