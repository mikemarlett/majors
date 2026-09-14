<?php
require_once('../map_edit_functions.php');
$degree_map_id = isset($_REQUEST['degree_map_id']) ? intval($_REQUEST['degree_map_id']) : 0;

if (!$degree_map_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid map ID']);
    exit;
}

global $mysqli;
$sql = "SELECT * FROM `degree_maps_footnotes` WHERE `degree_map_id` = ".$degree_map_id." ORDER BY `order` ASC";
$result = $mysqli->query($sql);

$footnotes = [];
while ($row = $result->fetch_assoc()) {
    $text = $row["order"].". ".$row["note"];
    $footnotes[] = ["id" => (string) $row["id"], "text" => $text]; // Ensure ID is a string
}

echo json_encode($footnotes);
?>