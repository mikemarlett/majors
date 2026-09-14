<?php
require_once '../map_edit_functions.php'; 
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
        'success'  => false,
        'message' => 'Must use POST method'
    ]);
    exit;
}
$return = array(
    'success'  => false,
    'message' => 'Something went horribly wrong.',
    'data' => $_POST
);

// Collect and sanitize header data.
$degree_map_id      = isset($_POST['degree_map_id']) ? intval($_POST['degree_map_id']) : '';
$hours_to_graduate  = isset($_POST['hours_to_graduate']) ? strval(trim($_POST['hours_to_graduate'])) : '0';
if (empty($degree_map_id)){
    $return['message'] = 'Degree map ID is missing!';
    echo json_encode($return);
    exit;
}
if (empty($hours_to_graduate)){
    $return['message'] = 'You must set a number of hours to graduate!';
    echo json_encode($return);
    exit;
}


if (!isset($_POST['degree_map']['hours']) || !is_array($_POST['degree_map']['hours'])) {
    $return['message'] = 'Invalid hours data';
    echo json_encode($return);
    exit;
}
$mysqli->begin_transaction();
try {
    // If there's no degree_map_id, then insert a new degree map header.
    // For an existing degree map, update the header.
    $sql = "UPDATE `degree_maps` SET `hours_to_graduate` = ?, `timestamp` = NOW() WHERE `id` = ?";
    if (!($stmt = $mysqli->prepare($sql))) {
        $return['message'] = "Update degree map failed: " . $mysqli->error;
        throw new Exception("Prepare (update degree map) failed: " . $mysqli->error);
    }
    $stmt->bind_param('si', $hours_to_graduate, $degree_map_id);
    if (!$stmt->execute()) {
        $return['message'] = "Update degree map failed: " . $stmt->error;
        throw new Exception("Update degree map failed: " . $stmt->error);
    }
    $stmt->close();
    // Loop through each year (the keys in the hours array are the year numbers)
    foreach ($_POST['degree_map']['hours'] as $year => $data) {
        // --- Save Semester Hours ---
        // For each semester: keys "1", "2", and "3" (assuming these represent Fall, Spring, Summer)
        foreach (['1', '2', '3'] as $semester) {
            if (isset($data[$semester])) {
                $hours = trim($data[$semester]);
                if ($hours === '') {
                    $sql = "DELETE FROM `degree_maps_semester_hours` WHERE `degree_map_id` = ? AND `year` = ? AND `semester` = ?;";
                    if (!($stmt = $mysqli->prepare($sql))) {
                        $return['message'] = "Delete map hours failed: " . $mysqli->error;
                        throw new Exception("Delete map hours failed: " . $mysqli->error);
                    }
                    $stmt->bind_param('iii', $degree_map_id, $year, $semester);
                    if (!$stmt->execute()) {
                        $return['message'] = "Delete map hours failed: " . $stmt->error;
                        throw new Exception("Delete map hours failed: " . $stmt->error);
                    }
                    $stmt->close();
                    continue; // Skip further execution for this semester
                } else {
                    // Otherwise, use REPLACE to update or insert the row.
                    $sql = "REPLACE INTO `degree_maps_semester_hours` (`degree_map_id`, `year`, `semester`, `hours`, `timestamp`) VALUES (?, ?, ?, ?, NOW())";
                    if (!($stmt = $mysqli->prepare($sql))) {
                        $return['message'] = "Delete map hours failed: " . $mysqli->error;
                        throw new Exception("Delete map hours failed: " . $mysqli->error);
                    }
                    $stmt->bind_param('iiis', $degree_map_id, $year, $semester, $hours);
                    if (!$stmt->execute()) {
                        $return['message'] = "Update map hours failed: " . $stmt->error;
                        throw new Exception("Update map hours failed: " . $stmt->error);
                    }
                    $stmt->close();
                }
            }
        }
        // --- Save Year Hours ---
        if (isset($data['total_hours'])) {
            $totalHours = trim($data['total_hours']);
            if ($totalHours === '') {
                // If the total is empty, delete any record.
                $sql = "DELETE FROM `degree_maps_year_hours` WHERE `degree_map_id` = ? AND `year` = ?;";
                if (!($stmt = $mysqli->prepare($sql))) {
                    $return['message'] = "Delete map total hours failed: " . $mysqli->error;
                    throw new Exception("Delete map total hours failed: " . $mysqli->error);
                }
                $stmt->bind_param('ii', $degree_map_id, $year);
                if (!$stmt->execute()) {
                    $return['message'] = "Delete map total hours failed: " . $stmt->error;
                    throw new Exception("Delete map total hours failed: " . $stmt->error);
                }
                $stmt->close();
            } else {
                // Otherwise, insert or update via REPLACE.
                if ($stmt = $mysqli->prepare("REPLACE INTO `degree_maps_year_hours` (`degree_map_id`, `year`, `hours`, `timestamp`) VALUES (?, ?, ?, NOW())")) {
                    // Bind as string if totals can be ranges, or integer if they are strictly numeric.
                    $stmt->bind_param('iis', $degree_map_id, $year, $totalHours);
                    if (!$stmt->execute()) {
                        $return['message'] = "Update map total hours failed: " . $stmt->error;
                        throw new Exception("Update map total hours failed: " . $stmt->error);
                    }
                    $stmt->close();
                }
            }
        }
    }
} catch (Exception $ex) {
    error_log("Transaction failed: " . $ex->getMessage());
    $return['message'] = 'Transaction failed: ' . $ex->getMessage();
    $mysqli->rollback();
    echo json_encode($return);
    exit;
}
$mysqli->commit(); // Ensure all changes are saved
$return['success'] = true;
$return['message'] = 'Updating Degree Map Hours was successful';
echo json_encode($return);
exit;
