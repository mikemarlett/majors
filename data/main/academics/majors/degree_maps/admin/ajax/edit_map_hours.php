<?php
require_once '../map_edit_functions.php'; 

// Set content type to JSON.
header('Content-Type: application/json');


if (!empty($_REQUEST['degree_map_id'])) {
    $degree_map_id = intval($_REQUEST['degree_map_id']);
}

if (! empty ($degree_map_id)){
    $form = map_hours_form($degree_map_id);
    echo json_encode(['modal' => $form]);
}else{
    echo json_encode(["error" => "Empty degree_map"]);
}
exit;
