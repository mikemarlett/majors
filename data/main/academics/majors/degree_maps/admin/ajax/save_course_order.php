<?php
require_once('../map_edit_functions.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['updatedOrder'])) {
    echo json_encode(['error' => 'Missing updated order data']);
    exit;
}

// Decode JSON from JavaScript
$updatedOrder = json_decode($_POST['updatedOrder'], true);
if (!is_array($updatedOrder)) {
    echo json_encode(['error' => 'Invalid order data']);
    exit;
}

// Prepare query to update course positions
$query = "UPDATE `degree_maps_courses` 
          SET `order` = ?, `year` = ?, `semester` = ? 
          WHERE id = ?";

$stmt = $mysqli->prepare($query);

foreach ($updatedOrder as $course) {
    $stmt->bind_param("iiii", $course['order'], $course['year'], $course['semester'], $course['id']);
    $stmt->execute();
}

$stmt->close();
echo json_encode(['success' => true]);
exit;
?>