<?php
// File: ajax/get_user_form.php
require_once('../map_edit_functions.php');

$user_template = array(
  'id' => '',
  'first_name' => '',
  'last_name' => '',
  'email' => '',
  'permission_level' => '',
  'default_college_id' => '',
  'default_department_id' => ''
);
$user_id = isset($_REQUEST['user_id']) ? trim($_REQUEST['user_id']) : '';
if (! empty($user_id)){
	$user = get_user($user_id);
}else{
  $user = $user_template;
}

$colleges = get_all_colleges_array();
$departments = get_departments_array();
$college_options = get_all_colleges_options($user['default_college_id'], true); 
$department_options = get_department_options($user['default_department_id'], $user['default_college_id'], true );

$permisions = ['editor' => 'Editor', 'approver'=>'Approver', 'administrator'=>'Administrator'];
$permision_options = build_options($permisions, $user['permission_level'], false, true);

$form = '
    <form id="user-form">
      <input type="hidden" name="user_id" id="user_id" value="'.$user['id'].'">
      <div>
        <label for="first_name">First Name:</label>
        <input type="text" name="first_name" id="first_name" value="'.$user['first_name'].'"> 
      </div>
      <div>
        <label for="last_name">Last Name:</label>
        <input type="text" name="last_name" id="last_name" value="'.$user['last_name'].'">
      </div>
      <div>
        <label for="email">Email:</label>
        <input type="email" name="email" id="email" value="'.$user['email'].'">
      </div>
      <div>
        <label for="permission_level">Permission Level:</label>
        <select name="permission_level" id="permission_level">
          '.$permision_options.'
        </select>
      </div>
      <div>
        <label for="college">College:</label>
        <select name="college" id="college">
          '.$college_options.'
        </select>
      </div>
      <div>
        <label for="department">Department:</label>
        <select name="department" id="department">
          '.$department_options.'
        </select>
      </div>
      <button type="submit" class="button">Save User</button>
      <button type="button" id="cancel-user-btn" class="button">Cancel</button>
    </form>
';
echo $form;

exit;