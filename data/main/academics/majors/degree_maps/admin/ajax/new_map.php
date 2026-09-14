<?php
require_once '../map_edit_functions.php'; 

// Set content type to JSON.
header('Content-Type: application/json');
$return = array(
    'success'  => false,
    'message' => 'Something went horribly wrong.',
    'data' => $_POST
);

$degree_map = build_empty_degree_map();

$vars = array( 'major', 'degree_type', 'college', 'department', 'academic_year');
foreach ($vars as $var){
    if (! empty($_POST[$var])){
        $degree_map[$var] = trim(urldecode($_POST[$var]));
    }
    if (! empty($degree_map[$var]) && ($var == 'academic_year')){
        $degree_map[$var] = intval($degree_map[$var]);
    }
}
$form = build_map_form($degree_map);
if ($form){
    $return['success'] = true;
    $return['message'] = 'Form built successfully';
    $return['modal'] = $form;
}else{
    $return['message'] = 'Failed to build form';
}  
echo json_encode($return);

exit;