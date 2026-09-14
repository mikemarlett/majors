<?php
// File: ajax/delete_user.php
require_once($_SERVER['DOCUMENT_ROOT'].'/../config/functions.php'); 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

try {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    if ($user_id <= 0) {
        throw new Exception("Invalid user ID.");
    }

    $sql = "DELETE FROM majors_users WHERE id = ?";
    if (!($stmt = $mysqli->prepare($sql))) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception("Delete failed: " . $stmt->error);
    }
    $stmt->close();

    echo json_encode([
        'status'  => 'success',
        'message' => 'User deleted successfully!'
    ]);
} catch (Exception $ex) {
    echo json_encode([
        'status'  => 'error',
        'message' => $ex->getMessage()
    ]);
}
exit;