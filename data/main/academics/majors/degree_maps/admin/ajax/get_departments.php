<?php
require_once('../map_edit_functions.php');

if (isset($_POST['college'])) {
    $colleges_array = get_all_colleges_array();
    $colleges = array();
    foreach ($colleges_array as $college){
        $colleges[] = $college['name'];
    }
    $college = trim(urldecode($_POST['college']));
    if (isset($_POST['department'])){
        $department = trim(urldecode($_POST['department']));
    }else{
        $department = '';
    }
    if (in_array($college, $colleges)){
        $options = get_department_options($department, $college); // Call your PHP function to get options
        echo $options;
    }else{
        echo '<option value="">None selected</option>';
    }

}else{
    exit;
}
?>