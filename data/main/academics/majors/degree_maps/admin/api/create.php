<?php
$requestMethod = $_SERVER["REQUEST_METHOD"];
include('../class/rest.php');
$api = new Rest();
switch($requestMethod) {
	case 'POST':	
		$api->insertProgram($_POST);
		break;
	default:
	header("HTTP/1.0 405 Method Not Allowed");
	break;
}
