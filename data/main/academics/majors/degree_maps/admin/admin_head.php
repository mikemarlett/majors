<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/../config/functions.php');


function get_user_by_ouauth_id($ouauth_id){
	global $mysqli;
	$user = array();
	$sql = "SELECT * FROM `majors_users` WHERE `ouauth_id` = ?";
	if (!($stmt = $mysqli->prepare($sql))) {
		throw new Exception("Prepare failed: " . $mysqli->error);
	}
	$stmt->bind_param("s", $ouauth_id);
	if (!$stmt->execute()) {
		throw new Exception("Select failed: " . $stmt->error);
	}
	
	$result = $stmt->get_result();
	while ($row = $result->fetch_assoc()) { 
		$user = $row;
	}
	
	$stmt->close();
	return $user;
}


function get_user_by_id($id){
	global $mysqli;
	$user = array();
	//get the user from the database
	$sql = "SELECT * FROM `majors_users` WHERE `id` = ?";
	if (!($stmt = $mysqli->prepare($sql))) {
		throw new Exception("Prepare failed: " . $mysqli->error);
	}
	$stmt->bind_param("i", $id);
	if (!$stmt->execute()) {
		throw new Exception("Select failed: " . $stmt->error);
	}
	$result = $stmt->get_result();
	while ($row = $result->fetch_assoc()) { 
		$user = $row;
	}
	$stmt->close();
	return $user;
}


function get_user_by_email($email){
	global $mysqli;
	$user = array();
	//get the user from the database
	$sql = "SELECT * FROM `majors_users` WHERE `email` = ?";
	if (!($stmt = $mysqli->prepare($sql))) {
		throw new Exception("Prepare failed: " . $mysqli->error);
	}
	$stmt->bind_param("s", $email);
	if (!$stmt->execute()) {
		throw new Exception("Select failed: " . $stmt->error);
	}
	$result = $stmt->get_result();
	while ($row = $result->fetch_assoc()) { 
		$user = $row;
	}
	$stmt->close();
	return $user;
}

function add_user($user) {
	global $mysqli;
	$sql = "INSERT INTO majors_users (`first_name`, `last_name`, `email`, `role`, `default_college_id`, `default_department_id`, `created_at`, `updated_at`, `ouauth_id`, `phone`) 
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
	if (!($stmt = $mysqli->prepare($sql))) {
		throw new Exception("Prepare failed: " . $mysqli->error);
	}
	$stmt->bind_param("ssssiissss", $user['first_name'], $user['last_name'], $user['email'], $user[`role`], $user[`default_college_id`], $user[`default_department_id`], $user['created_at'], $user['updated_at'], $user['ouauth_id'], $user['phone']);
	if (!$stmt->execute()) {
		throw new Exception("Insert failed: " . $stmt->error);
	}
	$stmt->close();
	//get the user id from the database
	$user['id'] = $mysqli->insert_id;

	return $user;
}
 
function update_user($user){
	global $mysqli;
	if (empty($user['id'])){
		throw new Exception("User ID is required to update a user.");
	}
	$sql = "UPDATE majors_users 
			SET `first_name` = ?, `last_name` = ?, `email` = ?, `role` = ?, `default_college_id` = ?, `default_department_id` = ?, `updated_at` = ?, `ouauth_id` = ?, `phone` = ?
			WHERE `id` = ?";
	if (!($stmt = $mysqli->prepare($sql))) {
		throw new Exception("Prepare failed: " . $mysqli->error);
	}
	$stmt->bind_param("ssssiisssi", $user['first_name'], $user['last_name'], $user['email'], $user['role'], $user['default_college_id'], $user['default_department_id'], $user['updated_at'], $user['ouauth_id'], $user['phone'], $user['id']);
	if (!$stmt->execute()) {
		throw new Exception("Update failed: " . $stmt->error);
	}
	$stmt->close();
	return $user;
	
}



//for testing (will set with login.php later)
if (!isset($_SESSION['user_id'])){
	$_SESSION['user_id'] = "dabd4d8e-7307-4379-88d4-b3fa77a5a3d2";
	$_SESSION['login_logged'] = 1;
	$_SESSION['oauth2_user'] = array(
		'businessPhones' => array(
			'+1-316-978-3575'
		),
		'displayName' => 'Marlett, Mike',
		'givenName' => 'Mike',
		'jobTitle' => 'Manager Website Development',
		'mail' => 'mike.marlett@wichita.edu',
		'mobilePhone' => '',
		'officeLocation' => '',
		'preferredLanguage' => '',
		'surname' => 'Marlett',
		'userPrincipalName' => 'q262t958@wichita.edu',
		'id' => 'dabd4d8e-7307-4379-88d4-b3fa77a5a3d2'
	);
	$_SESSION['email'] = 'mike.marlett@wichita.edu';
	$_SESSION['role'] = "admin";
}
//end testing


//do security check
if (empty($_SESSION['user_id']) or empty($_SESSION['login_logged']) or empty($_SESSION['oauth2_user']) or empty($_SESSION['email'])){
	header("Location: /admin/login.php");
	exit();
}else{
	$update_user = array(
		'first_name' => $_SESSION['oauth2_user']['givenName'],
		'last_name' => $_SESSION['oauth2_user']['surname'],
		'email' => $_SESSION['oauth2_user']['mail'],
		'updated_at' => date('Y-m-d H:i:s'),
		'ouauth_id' => $_SESSION['oauth2_user']['id'],
		'phone' => $_SESSION['oauth2_user']['businessPhones'][0]
	);
	$user = get_user_by_ouauth_id($_SESSION['user_id']);
	if (empty($user)){
		//if this user hasn't logged in before, we won't have their OAUTH2 ID in the database
		//so we'll add it now
		$user = get_user_by_email($_SESSION['email']);
		print_r($user);
		if (empty($user)){
			//if this user doesn't exist in the database, we'll add them now
			$update_user['role'] = 'none';
			$update_user['default_college_id'] = 0;
			$update_user['default_department_id'] = 0;
			$update_user['created_at'] = date('Y-m-d H:i:s');
			$user = add_user($update_user);
		}
	}
	//update the user's information in the database to add the OAUTH2 ID and anything else that may have changed
	if (! empty($user['id'])){
		$user = array_merge($user, $update_user);

		$user = update_user($user);
	}
}

if (empty($user) or empty($user['role'])){
	header("Location: /admin/login.php");
	exit();
}
/*

    [oauth2_user] => Array
        (
            [@odata.context] => https://graph.microsoft.com/v1.0/$metadata#users/$entity
            [businessPhones] => Array
                (
                    [0] => +1-316-978-3575
                )

            [displayName] => Marlett, Mike
            [givenName] => Mike
            [jobTitle] => Manager Website Development
            [mail] => mike.marlett@wichita.edu
            [mobilePhone] => 
            [officeLocation] => 
            [preferredLanguage] => 
            [surname] => Marlett
            [userPrincipalName] => q262t958@wichita.edu
            [id] => dabd4d8e-7307-4379-88d4-b3fa77a5a3d2
        )

    [user_id] => dabd4d8e-7307-4379-88d4-b3fa77a5a3d2
    [email] => mike.marlett@wichita.edu
    [login_logged] => 1
    [LangSet] => english
    [hc_whoami] => b438d5d77580b808c30b43984e9a5763


*/


if (!isset($title)){
	$title = "Admin";
}
if (!isset($description)){
	$description = "Admin";
}
if (!isset($header_items)){
	$header_items = array();
}
if (!isset($footer_items)){
	$footer_items = array();
}

if (!isset($admin_buttons)){
	$admin_buttons = array(
		"logout" => array(
			"href" => "/admin/logout.php",
			"text" => "Logout"
		),
		"home" => array(
			"href" => "/admin/index.php",
			"text" => "Home"
		),
		"profile" => array(
			"href" => "/admin/profile.php",
			"text" => "Profile"
		)
	);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title><?php echo $title; ?></title>
	<meta name="Description" content="<?php echo $description; ?>">
<!-- OU Search Ignore Start Here -->
<?php 
include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/headcode.inc");

	if (isset ($header_items)){
		foreach ($header_items as $header_item){
			echo "\t".$header_item.PHP_EOL;
		}
	}else{
		echo "<-- where be da header_items? -->".PHP_EOL;
	}

?>
</head>
<body>
	<?php 
	/*
		<header class="admin-header">
		<nav class="utility-nav" role="navigation" aria-label="admin navigation">
			<div class="toplinks__buttons"><?php 
				foreach ($admin_buttons as $button){
					echo "<a href=\"".$button['href']."\" class=\"button button--subtle\">".$button['text']."</a>".PHP_EOL;
				}
			?></div>
		</nav>
	</header>
	*/
	
	include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/header.inc"); ?>
	<!-- OU Search Ignore End Here -->
	<main class="main main--slab">
		<?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/alert.php"); ?>
