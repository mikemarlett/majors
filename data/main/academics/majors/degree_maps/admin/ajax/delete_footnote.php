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
if (isset($_POST['remove_footnote'])){
    if (! empty($_POST['remove_footnote']) && str_starts_with($_POST['remove_footnote'], 'new')){
        $return['success'] = true;
        $return['message'] = '';
        echo json_encode($return);
        exit; //if it's a "new" footnote then it's not in the database and we don't need to delete it, we can just leave
    }
    $remove_footnote = intval($_POST['remove_footnote']);
}
if (empty($remove_footnote)){
    $return['message'] = 'Footnote ID is missing!';
    echo json_encode($return);
    exit;
}
$degree_map_id = isset($_POST['degree_map_id']) ? intval($_POST['degree_map_id']) : '';
if (empty($degree_map_id)){
    $return['message'] = 'Degree map ID is missing!';
    echo json_encode($return);
    exit;
}

$return = delete_footnote($remove_footnote, $degree_map_id, $return);

echo json_encode($return);
exit;
