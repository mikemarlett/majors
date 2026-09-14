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
$degree_map_id = isset($_POST['degree_map_id']) ? intval($_POST['degree_map_id']) : '';
if (empty($degree_map_id)){
    $return['message'] = 'Degree map ID is missing!';
    echo json_encode($return);
    exit;
}
$mysqli->begin_transaction();
try {
    //ignore the order that they are sent with and instead reorder them on the fly
    $order = 0;
    if(isset($_POST['footnotes']) && is_array($_POST['footnotes'])){
        foreach ($_POST['footnotes'] as $footnoteId => $footnoteData) {
            // Trim note and cast order (if needed)
            $order++;
            $note  = isset($footnoteData['note']) ? trim($footnoteData['note']) : '';

            if (strpos($footnoteId, 'new_') === 0) {
                // Insert a new footnote.
                $sqlInsertFootnote = "INSERT INTO `degree_maps_footnotes` 
                    (`degree_map_id`, `order`, `note`, `timestamp`)
                    VALUES (?, ?, ?, NOW())";
                if (!($stmtInsertFootnote = $mysqli->prepare($sqlInsertFootnote))) {
                    $return['message'] = "Prepare (insert footnote) failed: " . $mysqli->error;
                    throw new Exception("Prepare (insert footnote) failed: " . $mysqli->error);
                }
                $stmtInsertFootnote->bind_param("iis", $degree_map_id, $order, $note);
                if (!$stmtInsertFootnote->execute()) {
                    $return['message'] = "Insert footnote failed: " . $stmtInsertFootnote->error;
                    throw new Exception("Insert footnote failed: " . $stmtInsertFootnote->error);
                }
                $stmtInsertFootnote->close();
            } else {
                // Update an existing footnote.
                $sqlUpdateFootnote = "UPDATE `degree_maps_footnotes` 
                    SET `order` = ?, `note` = ? 
                    WHERE `id` = ? AND `degree_map_id` = ?";
                if (!($stmtUpdateFootnote = $mysqli->prepare($sqlUpdateFootnote))) {
                    $return['message'] = "Prepare (update footnotes) failed: " . $mysqli->error;
                    throw new Exception("Prepare (update footnote) failed: " . $mysqli->error);
                }
                $stmtUpdateFootnote->bind_param("isii", $order, $note, $footnoteId, $degree_map_id);
                if (!$stmtUpdateFootnote->execute()) {
                    $return['message'] = "Update footnote failed: " . $stmtUpdateFootnote->error;
                    throw new Exception("Update footnote failed: " . $stmtUpdateFootnote->error);
                }
                $stmtUpdateFootnote->close();
            }
        }
    }

} catch (Exception $ex) {
    error_log("Transaction failed: " . $ex->getMessage());
    $mysqli->rollback();
    echo json_encode($return);
    exit;
}
$mysqli->commit(); // Ensure all changes are saved
reorder_footnotes($degree_map_id);

$return['success'] = true;
$return['message'] = 'Updating Degree Footnotes was successful';
echo json_encode($return);
exit;
