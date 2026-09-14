<?php

require_once($_SERVER['DOCUMENT_ROOT'].'/../config/functions.php');

function getAllPrograms() {
    global $mysqli;
    $query = "SELECT * FROM `academic_programs`";
    $result = mysqli_query($mysqli, $query);
    $programs = mysqli_fetch_all($result, MYSQLI_ASSOC);
    return $programs;
}

function getProgramById($id) {
    global $mysqli;
    $id = (int) $id;
    $query = "SELECT * FROM `academic_programs` WHERE `id`=$id";
    $result = mysqli_query($mysqli, $query);
    $program = mysqli_fetch_assoc($result);
    return $program;
}


function readProgram($id) {
  global $mysqli;
  // Prepare the SQL statement
  $stmt = $mysqli->prepare("SELECT * FROM `academic_programs` WHERE `id` = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $result = $stmt->get_result();
  // Check if a program was found with the given ID
  if ($result->num_rows === 0) {
    // Return a 404 Not Found error if no program was found
    http_response_code(404);
    echo json_encode(array("error" => "Program not found"));
    exit;
  }
  // Fetch the program data as an associative array
  $program = $result->fetch_assoc();
  // Close the database connection
  $stmt->close();
  $mysqli->close();
  // Return the program data as a JSON response
  header("Content-Type: application/json");
  echo json_encode($program);
}


function createProgram($program) {
    global $mysqli;
    $stmt = $mysqli->prepare("INSERT INTO academic_programs (academic_program, program_type, department, college, online_learning, graduate, note, program_simple_type, academic_program_catelog) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiiiiis", $program['academic_program'], $program['program_type'], $program['department'], $program['college'], $program['online_learning'], $program['graduate'], $program['note'], $program['program_simple_type'], $program['academic_program_catelog']);
    $stmt->execute();
    return true;
}

function updateProgram($program) {
    global $mysqli;
    $stmt = $mysqli->prepare("UPDATE academic_programs SET academic_program = ?, program_type = ?, department = ?, college = ?, online_learning = ?, graduate = ?, note = ?, program_simple_type = ?, academic_program_catelog = ? WHERE id = ?");
    $stmt->bind_param("ssiiiiissi", $program['academic_program'], $program['program_type'], $program['department'], $program['college'], $program['online_learning'], $program['graduate'], $program['note'], $program['program_simple_type'], $program['academic_program_catelog'], $program['id']);
    $stmt->execute();
    return true;
}

function deleteProgram($id) {
    global $mysqli;
	$stmt = $mysqli->prepare("DELETE FROM `academic_programs` WHERE `id` = ?");
	$stmt->bind_param("i", $id);
	$stmt->execute();
	$result = $stmt->get_result();
    mysqli_query($mysqli, $query);
    return true;
}

function getAllDepartments() {
    global $mysqli;
    $query = "SELECT * FROM departments";
    $result = mysqli_query($mysqli, $query);
    $departments = mysqli_fetch_all($result, MYSQLI_ASSOC);
    return $departments;
}

function getDepartmentById($id) {
    $id = (int) $id;
    global $mysqli;
    $query = "SELECT * FROM departments WHERE id=$id";
    $result = mysqli_query($mysqli, $query);
    $department = mysqli_fetch_assoc($result);
    return $department;
}

function getAllColleges() {
    global $mysqli;
    $query = "SELECT * FROM colleges";
    $result = mysqli_query($mysqli, $query);
    $colleges = mysqli_fetch_all($result, MYSQLI_ASSOC);
    return $colleges;
}

function getCollegeById($id) {
    $id = (int) $id;
    global $mysqli;
    $query = "SELECT * FROM colleges WHERE id=$id";
    $result = mysqli_query($mysqli, $query);
    $college = mysqli_fetch_assoc($result);
    return $college;
}

