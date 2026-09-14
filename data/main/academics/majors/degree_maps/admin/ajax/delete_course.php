<?php
require_once('../map_edit_functions.php');

try {
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    if ($course_id <= 0) {
        throw new Exception("Invalid course ID.");
    }
    $delete = delete_course($course_id);

    if (!$delete) {
        throw new Exception("Failed to delete course.");
    }

    echo json_encode([
        'status'  => 'success',
        'message' => 'Course deleted successfully!'
    ]);
    
} catch (Exception $ex) {
    echo json_encode([
        'status'  => 'error',
        'message' => $ex->getMessage()
    ]);
}
exit;

?>
