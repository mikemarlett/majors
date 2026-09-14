<?php
/* /////////////
TO DELETE A Degree Map

https://www-test.wichita.edu/academics/majors/degree_maps/admin/maps.php?degree_map_id=922&delete=1&secret=squirrel

//////////  */ 


require_once($_SERVER['DOCUMENT_ROOT'].'/../config/functions.php');
require_once dirname(__FILE__) . '/../maps_functions.php';

//for use in multiple functions
$yearOptions = array('1' => 'First', '2' => 'Second', '3' => 'Third', '4' => 'Fourth');
$semesterOptions = array('1' => 'Fall', '2' => 'Spring', '3' => 'Summer');
$sgeOptions = array(
	'' => 'None',
	'010' => 'English (010)',
	'020' => 'Communications (020)',
	'030' => 'Math/Statistics (030)',
	'040' => 'Natural and Physical Science (040)',
	'050' => 'Social and Behavioral Science (050)',
	'060' => 'Arts and Humanities (060)',
	'070' => 'Institutionally Designated (070)'
);

/****** Edit Degree map ********
// 
//	@map_id init id of map to edit
//	returns form for editing map
//
*********************************/

function edit_degree_map($degree_map_id){
	$form = $degree_map = false;
	//sanatize id
	$degree_map_id = (int)$degree_map_id;
	if ($degree_map_id > 0){ //guess we can't have a map 0
		$degree_map = get_map_by_id($degree_map_id);
	}
	if ($degree_map){
		$form = build_map_form($degree_map);
	}
	return $form;
}
// if we need to create a new map or pad a partial map

function build_empty_degree_map($degree_map = []){
	if (!is_array($degree_map)) {
		$degree_map = array();
	}
	if (isset($degree_map['courses']) && is_array($degree_map['courses'])) {
		$courses = $degree_map['courses']; //presereve the courses if there are any
	}else{
		$courses = array();
	}
	if (isset($degree_map['hours']) && is_array($degree_map['hours'])) {
		$hours = $degree_map['hours']; //presereve the hours if there are any
	}else{
		$hours = array();
	}
	$academic_year = 1 + getCurrentAcademicYear();
	$degree_map_template = array(
		'id' => null,
		'program_id' => null,
		'major' => '(Draft) New Major',
		'college' => '',
		'degree_type' => '',
		'department' => null,
		'note' => null,
		'academic_year'	=> $academic_year,
		'hours_to_graduate'	=> '120',
		'approved' => 0,
		'footnotes' => [],
		'courses' => build_empty_courses($courses), //pad the courses array so that we can loop through it with four years and three semesters each
		'hours' => build_empty_hours($hours) //pad the hours array so that we can loop through it with four years and three semesters each
	);

	$degree_map = array_merge($degree_map_template, $degree_map);
	return $degree_map;
}

function build_empty_courses($courses = []){
	if (!is_array($courses)) {
		$courses = array();
	}
	for ($year = 1; $year <= 4; $year++) {
		if (!isset($courses[$year]) || !is_array($courses[$year])) {
			$courses[$year] = array();
		}
		for ($semester = 1; $semester <= 3; $semester++) {
			if (!isset($courses[$year][$semester]) || !is_array($courses[$year][$semester])) {
				$courses[$year][$semester] = array();
			}
		}
	}
	return $courses;
}
function build_empty_hours($hours){
	if (!is_array($hours)) {
		$hours = array();
	}
	for ($year = 1; $year <= 4; $year++) {
		if (!isset($hours[$year]) || !is_array($hours[$year])) {
			$hours[$year] = array();
		}
		for ($semester = 1; $semester <= 3; $semester++) {
			if (!isset($hours[$year][$semester])) {
				$hours[$year][$semester] = ($semester == 3) ? array('hours' => null) : array('hours' => '15'); // No value set; assign default.
			} elseif (!is_array($hours[$year][$semester])) {
				$hours[$year][$semester] = array('hours' => $hours[$year][$semester]); // A value exists but isn’t in the expected array format; wrap it.
			}
		}
		if (!isset($hours[$year]['total_hours']) ) {
			$hours[$year]['total_hours'] = '30';
		}
	}
	return $hours;
}


function delete_degree_map($degree_map_id){
	$degree_map_id = (int)$degree_map_id;
	if ($degree_map_id > 0){
		global $mysqli;
		delete_courses($degree_map_id);
		delete_footnotes($degree_map_id);
		delete_semester_hours($degree_map_id);
		delete_year_hours($degree_map_id);
		$sql = "DELETE FROM degree_maps WHERE id = ?";
		if ($stmt = $mysqli->prepare($sql)) {
			$stmt->bind_param('i', $degree_map_id);
			if ($stmt->execute()) {
				$stmt->close();
				return true;
			}
		}
	}
	return false;
}

function delete_courses($degree_map_id){
	$degree_map_id = (int)$degree_map_id;
	if ($degree_map_id > 0){
		global $mysqli;
		$sql = "DELETE FROM degree_maps_courses WHERE degree_map_id = ?";
		if ($stmt = $mysqli->prepare($sql)) {
			$stmt->bind_param('i', $degree_map_id);
			if ($stmt->execute()) {
				$stmt->close();
				return true;
			}
		}
	}
	return false;
}

function delete_course($course_id){
	$course_id = (int)$course_id;
	if ($course_id > 0){
		global $mysqli;
		$course = get_course($course_id);
		if (empty($course)){
			return false; //nothing to delete
		}
		$degree_map_id = $course['degree_map_id'];
		$year = $course['year'];
		$semester = $course['semester'];
		$sql = "DELETE FROM degree_maps_courses WHERE id = ?";
		if ($stmt = $mysqli->prepare($sql)) {
			$stmt->bind_param('i', $course_id);
			if ($stmt->execute()) {
				$stmt->close();
				reorder_semester_courses($degree_map_id, $year, $semester);
				return true;
			}
		}
	}
	return false;
}


function delete_footnotes($degree_map_id){
	$degree_map_id = (int)$degree_map_id;
	if ($degree_map_id > 0){
		global $mysqli;
		$sql = "DELETE FROM degree_maps_footnotes WHERE degree_map_id = ?";
		if ($stmt = $mysqli->prepare($sql)) {
			$stmt->bind_param('i', $degree_map_id);
			if ($stmt->execute()) {
				$stmt->close();
				return true;
			}
		}
	}
	return false;
}

function delete_footnote($footnote_id, $degree_map_id, $return=array()){
	$degree_map_id = (int)$degree_map_id;
	$footnote_id = (int)$footnote_id;
	if (($degree_map_id > 0) && ($footnote_id > 0)){
		global $mysqli;
		
		$mysqli->begin_transaction();
		try {
		    // Prepare delete query
		    $deleteQuery = "DELETE FROM `degree_maps_footnotes` WHERE `id` = ?";
		    $deleteStmt = $mysqli->prepare($deleteQuery);
		    if (!$deleteStmt) {
		        throw new Exception("Statement preparation failed: " . $mysqli->error);
		    }
	
		    // Bind footnote_id
		    $deleteStmt->bind_param('i', $footnote_id);
		    if (!$deleteStmt->execute()) {
		        throw new Exception("Execution failed: " . $deleteStmt->error);
		    }
	
		    $affectedRows = $mysqli->affected_rows;
		    $deleteStmt->close();
	
		    // Validate deletion
		    if ($affectedRows !== 1) {
		        throw new Exception("Expected 1 row affected, but got $affectedRows.");
		    }
	
		    // Commit changes
		    $mysqli->commit();
	
		} catch (Exception $error) {
		    error_log("Transaction error: " . $error->getMessage());
		    $mysqli->rollback();
		    if (!empty($return)) {
		        $return['success'] = false;
		        $return['message'] .= $error->getMessage();
		        return $return;
		    }
		    return false;
		}

		reorder_footnotes($degree_map_id);

		if (!empty ($return)){
			$return['success'] = true;
			$return['message'] = 'Deleting Degree Footnote '.$footnote_id.' was successful';
			return $return;
		}
		return true;
	}
	if (!empty ($return)){
		$return['success'] = false;
		$return['message'] .= 'degree_map_id or footnote_id is missing';
		return $return;
	}
	return false;
}

function delete_semester_hours($degree_map_id){
	$degree_map_id = (int)$degree_map_id;
	if ($degree_map_id > 0){
		global $mysqli;
		$sql = "DELETE FROM degree_maps_semester_hours WHERE degree_map_id = ?";
		if ($stmt = $mysqli->prepare($sql)) {
			$stmt->bind_param('i', $degree_map_id);
			if ($stmt->execute()) {
				$stmt->close();
				return true;
			}
		}
	}
	return false;
}

function delete_year_hours($degree_map_id){
	$degree_map_id = (int)$degree_map_id;
	if ($degree_map_id > 0){
		global $mysqli;
		$sql = "DELETE FROM degree_maps_year_hours WHERE degree_map_id = ?";
		if ($stmt = $mysqli->prepare($sql)) {
			$stmt->bind_param('i', $degree_map_id);
			if ($stmt->execute()) {
				$stmt->close();
				return true;
			}
		}
	}
	return false;
}

function reorder_semester_courses($degree_map_id, $year, $semester){
    global $mysqli;
    $degree_map_id = (int)$degree_map_id;
    $year = (int)$year;
    $semester = (int)$semester;

    if ($degree_map_id > 0) {
        // Fix order by reassigning numbers sequentially
        $query = "UPDATE degree_maps_courses AS d
                  JOIN (SELECT id, @rownum := @rownum + 1 AS new_order
                        FROM degree_maps_courses, (SELECT @rownum := 0) r
                        WHERE degree_map_id = ? AND year = ? AND semester = ?
                        ORDER BY `order` ASC) AS t
                  ON d.id = t.id
                  SET d.`order` = t.new_order";

        if ($stmt = $mysqli->prepare($query)) {
            $stmt->bind_param('iii', $degree_map_id, $year, $semester);
            if ($stmt->execute()) {
                $stmt->close();
                return true;
            }
            $stmt->close();
        }
    }
    return false;
}

function reorder_footnotes($degree_map_id){
    global $mysqli;
    $degree_map_id = (int)$degree_map_id;

    if ($degree_map_id > 0) {
        // Fix order by reassigning numbers sequentially
        $query = "UPDATE degree_maps_footnotes AS d
                  JOIN (SELECT id, @rownum := @rownum + 1 AS new_order
                        FROM degree_maps_footnotes, (SELECT @rownum := 0) r
                        WHERE degree_map_id = ?
                        ORDER BY `order` ASC) AS t
                  ON d.id = t.id
                  SET d.`order` = t.new_order";

        if ($stmt = $mysqli->prepare($query)) {
            $stmt->bind_param('i', $degree_map_id);
            if ($stmt->execute()) {
                $stmt->close();
                return true;
            }
            $stmt->close();
        }
    }
    return false;
}



//build the form for editing a degree map

function display_edit_degree_map($degree_map_id){
	$main_content = null;
	if (! is_array($degree_map_id) and is_numeric($degree_map_id)){
		$degree_map = get_map_by_id($degree_map_id);
	}elseif(! empty($degree_map_id['id'])){
		$degree_map = $degree_map_id;
		$degree_map_id = $degree_map['id'];
		if (empty($degree_map['hours'])){
			$degree_map['hours'] = get_degree_maps_semester_hours($degree_map_id);
		}
		if (empty($degree_map['courses'])){
			$degree_map['courses'] = get_degree_maps_courses($degree_map_id);
		}
		if (empty($degree_map['footnotes'])){
			$degree_map['footnotes'] = get_degree_maps_footnotes($degree_map_id);
		}
		// var_dump($degree_map);
	}
	
	if (! empty($degree_map) ){
		$main_content .= PHP_EOL.'
<hr class="noprint hr-yellow-bar" />
<input type="hidden" name="degree_map_id" id="degree_map_id" value="'.$degree_map['id'].'">
<h2 class="noprint" style="text-align: center;">Degree Map</h2>
<hr class="noprint hr-yellow-bar" />
<div class="row">
  <div class="col-2"><img src="/_resources/images/logo-blacktype.svg" alt="Wichita State University" class="wsu-logo"></div>
  <div class="col-10">
			'.PHP_EOL;
		$full_year = ($degree_map['academic_year']-1).' - '.$degree_map['academic_year'];
		$main_content .= '	<h3 style="margin-top: 0; line-height: 1;">'.$degree_map['college'].'</h3>
		<h4>'.$degree_map['degree_type'].' in '.$degree_map['major'].' ('.$full_year.')</h4>';
			$main_content .= '<p><a class="button" id="edit_map_details" role="button" href="#" style="margin-bottom: 0; margin-top: 1em;" data-map-id="'.$degree_map['id'].'">Edit Map Details &nbsp; <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6" style="width: 18px;" role="img">
  <path d="M21.731 2.269a2.625 2.625 0 0 0-3.712 0l-1.157 1.157 3.712 3.712 1.157-1.157a2.625 2.625 0 0 0 0-3.712ZM19.513 8.199l-3.712-3.712-8.4 8.4a5.25 5.25 0 0 0-1.32 2.214l-.8 2.685a.75.75 0 0 0 .933.933l2.685-.8a5.25 5.25 0 0 0 2.214-1.32l8.4-8.4Z" />
  <path d="M5.25 5.25a3 3 0 0 0-3 3v10.5a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3V13.5a.75.75 0 0 0-1.5 0v5.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V8.25a1.5 1.5 0 0 1 1.5-1.5h5.25a.75.75 0 0 0 0-1.5H5.25Z" /><title id="map--edit_'.$degree_map['id'].'">Edit '.$degree_map['major'].'</title>
</svg></a>

<a class="button" id="edit_map_hours" role="button" href="#" style="margin-bottom: 0; margin-top: 1em;" data-map-id="'.$degree_map['id'].'">Edit Map Hours &nbsp; <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6" style="width: 18px;" role="img">
  <path d="M21.731 2.269a2.625 2.625 0 0 0-3.712 0l-1.157 1.157 3.712 3.712 1.157-1.157a2.625 2.625 0 0 0 0-3.712ZM19.513 8.199l-3.712-3.712-8.4 8.4a5.25 5.25 0 0 0-1.32 2.214l-.8 2.685a.75.75 0 0 0 .933.933l2.685-.8a5.25 5.25 0 0 0 2.214-1.32l8.4-8.4Z" />
  <path d="M5.25 5.25a3 3 0 0 0-3 3v10.5a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3V13.5a.75.75 0 0 0-1.5 0v5.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V8.25a1.5 1.5 0 0 1 1.5-1.5h5.25a.75.75 0 0 0 0-1.5H5.25Z" /><title id="map--edit_'.$degree_map['id'].'">Edit hours</title>
</svg></a>

<a class="button" id="edit_map_footnotes" role="button" href="#" style="margin-bottom: 0; margin-top: 1em;" data-map-id="'.$degree_map['id'].'">Edit Footnotes &nbsp; <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6" style="width: 18px;" role="img">
  <path d="M21.731 2.269a2.625 2.625 0 0 0-3.712 0l-1.157 1.157 3.712 3.712 1.157-1.157a2.625 2.625 0 0 0 0-3.712ZM19.513 8.199l-3.712-3.712-8.4 8.4a5.25 5.25 0 0 0-1.32 2.214l-.8 2.685a.75.75 0 0 0 .933.933l2.685-.8a5.25 5.25 0 0 0 2.214-1.32l8.4-8.4Z" />
  <path d="M5.25 5.25a3 3 0 0 0-3 3v10.5a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3V13.5a.75.75 0 0 0-1.5 0v5.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V8.25a1.5 1.5 0 0 1 1.5-1.5h5.25a.75.75 0 0 0 0-1.5H5.25Z" /><title id="map--edit_'.$degree_map['id'].'">Edit footnotes</title>
</svg></a>

</p>';
		$main_content .= '	</div>
	 </div>';

if (! empty($degree_map['note'])){
	$main_content .= '
<div class="alert-bar ">
    <div class="alert-bar__wrapper">
            <div class="alert-bar__icon" style="margin-left:1rem;">
             <svg role="img" class="icon" aria-labelledby="design--info_63e3f07a36b50"><title id="design--info_63e3f07a36b50">Alert</title><use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--info"></use></svg>
             <span class="show-for-sr">Alert</span>
        </div>
        <div class="alert-bar__message">
	        <div class="headline-group "><span class="head">Note</span></div>
            <p>'.htmlentities($degree_map['note']).'</p>
        </div>
    </div>
</div>	
';

}
		$sge_warn = calculate_sge_hours($degree_map);

		if (! empty($sge_warn)){
			$main_content .= '
			<div class="alert-bar alert-bar--emergency">
				<div class="alert-bar__wrapper">
						<div class="alert-bar__icon" style="margin-left:1rem;">
						 <svg role="img" class="icon" aria-labelledby="sge_warn"><title id="sge_warn">Alert</title><use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--exclamation-triangle"></use></svg>
						 <span class="show-for-sr">Alert</span>
					</div>
					<div class="alert-bar__message">
						<div class="headline-group "><span class="head">Warning!</span></div>
						'.$sge_warn.'
					</div>
				</div>
			</div>	
			';
		}

		if (empty($degree_map['courses'])){
			$degree_map['courses'] = build_empty_courses();
		}else{
			$degree_map['courses'] = build_empty_courses($degree_map['courses']);
		}

		$course_year = 1;
		while($course_year <= 4){
			$courses = $degree_map['courses'][$course_year];
			
			//$course_year is a digit,  1-4
			$course_year_text = addOrdinalNumberSuffix($course_year).' year';
			if (! isset($table_row)){
				$main_content .= ''.PHP_EOL;
			}else{
			$main_content .= '
	<hr class="noprint hr-yellow-bar" />'.PHP_EOL;
			}
			$summer = ' summer';
			if (empty($courses[3])){
				$courses[3] = array();
			}

			$table = '
<div class="table-responsive-wrap">
	<table class="table--zebra-stripe">
  <colgroup>
    <col class="course_info">
    <col class="course_info">
    <col class="course_info">
  </colgroup>
  <caption>'.$course_year_text.'</caption>
  <thead>
    <tr>
      <th scope="col" class="course_info">Fall Semester</th>
      <th scope="col" class="course_info">Spring Semester</th>
      <th scope="col" class="course_info">Summer Semester</th>
    </tr>
  </thead>
  <tbody>
    <tr>
	'.PHP_EOL;
	$semesters = [1 => 'fall', 2 => 'spring', 3 => 'summer'];
	$year_hours = 0;
	foreach ($semesters as $semester_no => $semester_name)
	  {
 
		if (! isset($degree_map['hours'][$course_year][$semester_no]['hours'])){
			$degree_map['hours'][$course_year][$semester_no]['hours'] = 0;
		}


		if (empty( $degree_map['hours'][$course_year]['total_hours'] )){
			$year_hours = $year_hours + $degree_map['hours'][$course_year][$semester_no]['hours'];
		}else{
			$year_hours = $degree_map['hours'][$course_year]['total_hours'];
		}

		$table .= '<td style="padding: 0; vertical-align: top;">
          <ul class="semester-list" id="'.$course_year.'-'.$semester_name.'-semester" data-semester="'.$semester_no.'" data-year="'.$course_year.'">';
            if (!empty($courses[$semester_no])):
              foreach ($courses[$semester_no] as $course){
				if (! empty($course['footnote_ids'])){
					$course['footnote_ids'] = json_decode($course['footnote_ids'], true);
					foreach ($course['footnote_ids'] as $footnote_id){
						$footnote = get_footnote($footnote_id);
						if (!empty($footnote)){
							$sup = $footnote['order'];
							$original_footnote = '<sup>'.$sup.'</sup>';
							$footnote_text = '&nbsp;<sup><a class="footnote footnote-link" aria-label="footnote '.$sup.'" data-tippy-content="'.htmlentities($footnote['note']).'" href="#footnote_'.$footnote_id.'">'.$sup.'</a></sup>';
							if (strpos($course['course_info'], $original_footnote) !== FALSE){
								$course['course_info'] = str_replace($original_footnote, $footnote_text, $course['course_info']);
							}else{
								$course['course_info'] .= $footnote_text;
							}
						}
					}
				}
				if (! empty($course['sge'])){
					$course['course_info'] .= '&nbsp;<span class="sge sge-'.$course['sge'].'">'.$course['sge'].'</span>';
				}

				$course['course_info'] = '<div class="content">'.$course['course_info'].' <span class="hours">(' . $course['hours'] . ' hrs)</span>';
				
				if (!empty($course['extra'])) {
					$course['course_info'] .= '<div class="course-extra">'.htmlspecialchars($course['extra']).'</div>';
				}
				
				$course['course_info'] .= '</div>';

				
                $table .= '<li class="course-item" data-course-id="'.$course['id'].'">
                  <span class="drag-handle cursor-move">
				  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
  <path fill-rule="evenodd" d="M6.97 2.47a.75.75 0 0 1 1.06 0l4.5 4.5a.75.75 0 0 1-1.06 1.06L8.25 4.81V16.5a.75.75 0 0 1-1.5 0V4.81L3.53 8.03a.75.75 0 0 1-1.06-1.06l4.5-4.5Zm9.53 4.28a.75.75 0 0 1 .75.75v11.69l3.22-3.22a.75.75 0 1 1 1.06 1.06l-4.5 4.5a.75.75 0 0 1-1.06 0l-4.5-4.5a.75.75 0 1 1 1.06-1.06l3.22 3.22V7.5a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd" />
</svg>

                  </span>
                  '.$course['course_info'] . '
                  <a href="#" class="edit_course button" data-course-id="'.$course['id'].'">
				  	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6" style="width: 24px;" role="img" aria-labelledby="course-edit_'.$course['id'].'">
  <path d="M21.731 2.269a2.625 2.625 0 0 0-3.712 0l-1.157 1.157 3.712 3.712 1.157-1.157a2.625 2.625 0 0 0 0-3.712ZM19.513 8.199l-3.712-3.712-8.4 8.4a5.25 5.25 0 0 0-1.32 2.214l-.8 2.685a.75.75 0 0 0 .933.933l2.685-.8a5.25 5.25 0 0 0 2.214-1.32l8.4-8.4Z"></path>
  <path d="M5.25 5.25a3 3 0 0 0-3 3v10.5a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3V13.5a.75.75 0 0 0-1.5 0v5.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V8.25a1.5 1.5 0 0 1 1.5-1.5h5.25a.75.75 0 0 0 0-1.5H5.25Z"></path><title id="course-edit_'.$course['id'].'">Edit Course '.$course['id'].'</title>
</svg>


                  </a>
                </li>'.PHP_EOL;

			  }
            endif;
			// add a new course 
			$table .= '<li class="course-item">
			<a href="#" class="new_course button" data-semester="'.$semester_no.'" data-year="'.$course_year.'">
				  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6" style="width: 24px;" role="img" aria-labelledby="new_course_'.$course_year.'_'.$semester_no.'">
  <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 9a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25V15a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V9Z" clip-rule="evenodd" /><title id="new_course_'.$course_year.'_'.$semester_no.'">Add course to this semester</title>
</svg></a> <div class="content">Add course to this semester</div></li>'.PHP_EOL;

				  $table .= '</ul>
        </td>';
	  }
	  $table .= '</tr>
  </tbody>
  <tfoot>
    <tr>';
      foreach ($semesters as $semester_no => $semester_name):
        $table .= '<th>'.ucfirst($semester_name).' Total Hours: ';
		$table .= isset($degree_map['hours'][$course_year][$semester_no]['hours']) ? $degree_map['hours'][$course_year][$semester_no]['hours'] : 0;
        $table .= '</th>';
      endforeach;
    $table .= '</tr>
  </tfoot>
</table>
</div>
';

			$main_content .= $table;
			
			//even though we've calculated year hours, use manual if we have them
			if (! empty($degree_map['hours'][$course_year][0]['hours'])){
				$year_hours = $degree_map['hours'][$course_year][0]['hours'];
			}
			$main_content .= '		<p>Total hours for '.$course_year_text.': <strong>'.$year_hours.'</strong></p>
';
			if (empty($summer)){
				// $main_content .= '			<p>Summer courses are optional</p>'.PHP_EOL;
			}
			$course_year++;

		}
		if (! empty($degree_map['hours_to_graduate'])){
		$main_content .= '
		<hr>
		<p class="heading6">Hours needed to complete the degree: '.$degree_map['hours_to_graduate'].'</p>
';
		}
		
		if (! empty($degree_map['footnotes'])){
		$main_content .= '
		<hr />
		<h3 class="heading5">Footnotes</h3>

';
		foreach ($degree_map['footnotes'] as $key => $footnote){
			if ( $footnote['order'] === '0' ){
				$main_content .= '<div><strong>Note: </strong>'.$footnote['note'].'</div>';
				unset($degree_map['footnotes'][$key]);
			}
		}
		$main_content .= '
		<ol>'.PHP_EOL;
			foreach ($degree_map['footnotes'] as $footnote){
			$main_content .= '			<li><a name="footnote_'.$footnote['id'].'" id="footnote_'.$footnote['id'].'"></a>'.$footnote['note'].'</li>'.PHP_EOL;
			}
		$main_content .= '		</ol>'.PHP_EOL;
		}
		
		/// Systemwide General Education (SGE) Key 
		$main_content .= '<div>
	<h3 class="heading5">Systemwide General Education (SGE) Key</h3>
	<ul>
		<li><span class="sge sge-010">010</span> English</li>
		<li><span class="sge sge-020">020</span> Communications</li>
		<li><span class="sge sge-030">030</span> Math/Statistics</li>
		<li><span class="sge sge-040">040</span> Natural and Physical Science</li>
		<li><span class="sge sge-050">050</span> Social and Behavioral Science</li>
		<li><span class="sge sge-060">060</span> Arts and Humanities</li>
		<li><span class="sge sge-070">070</span> Institutionally Designated/Diversity</li>
	</ul>
</div>';
		
	//$main_content .= '<pre>'.json_encode($degree_map, JSON_PRETTY_PRINT).'</pre>';
	}
	return $main_content;
}




























/****** Edit Degree map ********
// 
//	@degree_map (optional) array of degree map to be edited
//	returns form for editing or creating map
//
*********************************/


function build_map_form($degree_map = ''){

	$empty_degree_map = build_empty_degree_map();

	if(! empty($degree_map) && ! is_array($degree_map)){
		$degree_map = get_map_by_id(intval($degree_map));
		$degree_map = array_merge($empty_degree_map, $degree_map);
	}

	if (empty($degree_map)){ //create enough of a degree_map skeleton that we can call things without stuff breaking
		$degree_map = $empty_degree_map;
	}

	//if the degree map is not empty, we need to pad the courses array so that we can loop through it with four years and three semesters each
	// If the courses key doesn't exist at all, create an empty array
	if (!isset($degree_map['courses']) || !is_array($degree_map['courses'])) {
		$degree_map['courses'] = build_empty_courses(); //pad the courses array so that we can loop through it with four years and three semesters each
	}else{
		$degree_map['courses'] = build_empty_courses($degree_map['courses']);
	}
	if (!isset($degree_map['hours']) || !is_array($degree_map['hours'])) {
		$degree_map['hours'] = build_empty_hours();
	}else{
		$degree_map['hours'] = build_empty_hours($degree_map['hours']); //pad the hours array so that we can loop through it with four years and three semesters each
	}

	$form = '
<div id="editMapModal" title="Edit Degree Map Details">
	<form method="POST" id="degree_map_form" name="degree_map_form" novalidate>
		<div class="row">
			<div class="col-9">
				<input type="hidden" name="degree_map_id" value="'.$degree_map['id'].'">
				<input type="hidden" name="academic_year_hidden" value="'.$degree_map['academic_year'].'">
				<label class="control-label" for="major">Degree Name<span class="required">*</span></label>
				<input type="text" name="major" id="major" value="'.$degree_map['major'].'" class="form-control" required>
				<span class="help-block">The name of the degree</span>
			</div>
			<div class="col-3">
				<label class="control-label" for="degree_type">Degree Type<span class="required">*</span></label>
				<input type="text" name="degree_type" id="degree_type" placeholder="" class="form-control"  value="'.$degree_map['degree_type'].'" required>
				<span class="help-block">e.g.: "BFA", "BS", "BA"</span>
			</div>
		</div>
		<div class="row">
			<div class="col-12">
				<label class="control-label" for="note">Note</label>
				<textarea class="form-control" name="note" id="note" placeholder="">'.$degree_map['note'].'</textarea>
				<span class="help-block">This is a publicly visible note about the whole degree, as opposed to just a single course or group of courses.</span>
			</div>
		</div>
		<div class="row">
			<div class="col-6">
				<label class="control-label" for="college">College<span class="required">*</span></label>
				<select class="form-control" name="college" id="college" required>';
				$form .= get_all_colleges_options($degree_map['college']);
				$form .= '
				</select>
			</div>
			<div class="col-6">
				<label class="control-label" for="department">Department</label>
				<select class="form-control" name="department" id="department">';
				$form .= get_department_options($degree_map['department'], $degree_map['college']);
				$form .= '
				</select>
				<span class="help-block">This is not displayed, but is used internally for groups and permissions and can be used for other things.</span>
			</div>
		</div>
		<div class="row">
			<div class="col-3">
				<label class="control-label" for="academic_year">Academic Year</label>
				<select class="form-control" name="academic_year" id="academic_year" disabled>';
				$form .= get_academic_years_options($degree_map['academic_year']);
				$form .= '
				</select>
			</div>
			<div id="program" class="col-9">
				<label class="control-label" for="program_id">Program</label>
				<select class="form-control" name="program_id" id="program_id">';
				$form .= get_program_options($degree_map['program_id'], $degree_map);
				$form .= '
				</select>
				<span class="help-block">Align this degree map with a program marketing page from Strat Comm</span>
			</div>
		</div>
		<div class="modal-buttons">
			<div class="row">
				<div class="col-6">
			<button type="button" id="SaveMapBtn" class="ui-button-primary button button-large"><svg xmlns="http://www.w3.org/2000/svg" viewbox="0 0 24 24" fill="currentColor" class="size-6" style="height: 1.5rem; width: 1.5rem;"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm.53 5.47a.75.75 0 0 0-1.06 0l-3 3a.75.75 0 1 0 1.06 1.06l1.72-1.72v5.69a.75.75 0 0 0 1.5 0v-5.69l1.72 1.72a.75.75 0 1 0 1.06-1.06l-3-3Z" clip-rule="evenodd" /></svg>Save Changes</button>
				</div>
				<div class="col-6">
			<button type="button" id="cancelMapBtn" class="button cancelBtn" data-modal="editMapModal" ><svg xmlns="http://www.w3.org/2000/svg" viewbox="0 0 24 24" fill="currentColor" class="size-6" style="height: 1.5rem; width: 1.5rem;"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-1.72 6.97a.75.75 0 1 0-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 1 0 1.06 1.06L12 13.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L13.06 12l1.72-1.72a.75.75 0 1 0-1.06-1.06L12 10.94l-1.72-1.72Z" clip-rule="evenodd" /></svg>Cancel</button>
			';
			/*
	if (isAdmin){
		$form .= '
			<button type="button" id="deleteMapBtn" class="ui-button-danger button"><svg xmlns="http://www.w3.org/2000/svg" viewbox="0 0 24 24" fill="currentColor" class="size-6" style="height: 1.5rem; width: 1.5rem;"><path fill-rule="evenodd" d="M16.5 4.478v.227a48.816 48.816 0 0 1 3.878.512.75.75 0 1 1-.256 1.478l-.209-.035-1.005 13.07a3 3 0 0 1-2.991 2.77H8.084a3 3 0 0 1-2.991-2.77L4.087 6.66l-.209.035a.75.75 0 0 1-.256-1.478A48.567 48.567 0 0 1 7.5 4.705v-.227c0-1.564 1.213-2.9 2.816-2.951a52.662 52.662 0 0 1 3.369 0c1.603.051 2.815 1.387 2.815 2.951Zm-6.136-1.452a51.196 51.196 0 0 1 3.273 0C14.39 3.05 15 3.684 15 4.478v.113a49.488 49.488 0 0 0-6 0v-.113c0-.794.609-1.428 1.364-1.452Zm-.355 5.945a.75.75 0 1 0-1.5.058l.347 9a.75.75 0 1 0 1.499-.058l-.346-9Zm5.48.058a.75.75 0 1 0-1.498-.058l-.347 9a.75.75 0 0 0 1.5.058l.345-9Z" clip-rule="evenodd" /></svg>Delete</button>'.PHP_EOL;
	}
			*/
	$form .= '
			</div>
		</div>
	</form>
</div>';

	return $form;
}

function edit_map_footnotes($degree_map){
	$empty_degree_map = build_empty_degree_map();

	if(! empty($degree_map) && ! is_array($degree_map)){
		$degree_map = get_map_by_id(intval($degree_map));
		$degree_map = array_merge($empty_degree_map, $degree_map);
	}

	if (empty($degree_map)){ //create enough of a degree_map skeleton that we can call things without stuff breaking
		$degree_map = $empty_degree_map;
	}
	$form = '
<div id="editFootnotesModal" title="Edit Degree Footnotes">
	<form method="POST" id="degree_footnotes_form" name="degree_footnotes_form">
		<input type="hidden" name="degree_map_id" value="'.$degree_map['id'].'">
		<ul id="footnotesContainer" class="footnotes-list ui-sortable">'.PHP_EOL;
		$i = 0;
		if (!empty($degree_map['footnotes'])){
				$orderOptions = [];
				for ($i = 1; $i <= count($degree_map['footnotes'] ); $i++) {
					$orderOptions[$i] = (string)$i;
				}
				foreach ($degree_map['footnotes'] as $footnote) {
					$selectHtml = build_select(
						'footnotes['.$footnote['id'].'][order]' ,             // name attribute
						$orderOptions,                // array of options
						$footnote['order'],           // which one is selected?
						[
							'id' => 'footnote_order_' . $footnote['id'],
							'class' => 'form-control'
						]
					);
				$form .= '
				<li class="footnote-container" data-footnote-id="'.$footnote['id'].'" data-order="'.$footnote['order'].'">
					<span class="drag-handle cursor-move ui-sortable-handle">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6"><path fill-rule="evenodd" d="M6.97 2.47a.75.75 0 0 1 1.06 0l4.5 4.5a.75.75 0 0 1-1.06 1.06L8.25 4.81V16.5a.75.75 0 0 1-1.5 0V4.81L3.53 8.03a.75.75 0 0 1-1.06-1.06l4.5-4.5Zm9.53 4.28a.75.75 0 0 1 .75.75v11.69l3.22-3.22a.75.75 0 1 1 1.06 1.06l-4.5 4.5a.75.75 0 0 1-1.06 0l-4.5-4.5a.75.75 0 1 1 1.06-1.06l3.22 3.22V7.5a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd"></path></svg></span>
					<div class="content">
						<input type="hidden" name="footnotes['.$footnote['id'].'][id]" value="'.$footnote['id'].'">
						<label class="control-label sr-only" for="footnote_'.$footnote['id'].'_note">Footnote</label>
						<textarea class="footnote-note" name="footnotes['.$footnote['id'].'][note]" id="footnote_'.$footnote['id'].'_note">'.$footnote['note'].'</textarea>
					</div>
					<a role="button" title="Remove Footnote '.$footnote['order'].'" class="remove-footnote-btn button" data-footnote-id="'.$footnote['id'].'">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6" style="width: 24px;" role="img" ><path fill-rule="evenodd" d="M16.5 4.478v.227a48.816 48.816 0 0 1 3.878.512.75.75 0 1 1-.256 1.478l-.209-.035-1.005 13.07a3 3 0 0 1-2.991 2.77H8.084a3 3 0 0 1-2.991-2.77L4.087 6.66l-.209.035a.75.75 0 0 1-.256-1.478A48.567 48.567 0 0 1 7.5 4.705v-.227c0-1.564 1.213-2.9 2.816-2.951a52.662 52.662 0 0 1 3.369 0c1.603.051 2.815 1.387 2.815 2.951Zm-6.136-1.452a51.196 51.196 0 0 1 3.273 0C14.39 3.05 15 3.684 15 4.478v.113a49.488 49.488 0 0 0-6 0v-.113c0-.794.609-1.428 1.364-1.452Zm-.355 5.945a.75.75 0 1 0-1.5.058l.347 9a.75.75 0 1 0 1.499-.058l-.346-9Zm5.48.058a.75.75 0 1 0-1.498-.058l-.347 9a.75.75 0 0 0 1.5.058l.345-9Z" clip-rule="evenodd" /></svg>
					</a>
				</li>
				';
			}
		}
		$form .= '
	</ul>
	<div class="new-footnote-button-area" data-degree-map-id="'.$degree_map['id'].'" data-order="'.$i++.'">
		<a href="#" id="addFootnoteBtn" class="button new_footnote"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6" style="width: 24px;" role="img" aria-labelledby="new_footnote"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 9a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25V15a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V9Z" clip-rule="evenodd"></path><title id="new_footnote">Add New Footnote</title></svg></a>
		<div class="content">Add New Footnote</div>
	</div>

	';

	$form .= '
		<div class="modal-buttons">
			<div class="row">
				<div class="col-6">
			<button type="button" id="SaveFootnotesBtn" class="ui-button-primary button button-large"><svg xmlns="http://www.w3.org/2000/svg" viewbox="0 0 24 24" fill="currentColor" class="size-6" style="height: 1.5rem; width: 1.5rem;"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm.53 5.47a.75.75 0 0 0-1.06 0l-3 3a.75.75 0 1 0 1.06 1.06l1.72-1.72v5.69a.75.75 0 0 0 1.5 0v-5.69l1.72 1.72a.75.75 0 1 0 1.06-1.06l-3-3Z" clip-rule="evenodd" /></svg>Save Footnotes</button>
				</div>
				<div class="col-6">
				</div>
			</div>
		</div>
	</form>
</div>';

	return $form;

}

function map_hours_form($degree_map = null){
	$empty_degree_map = build_empty_degree_map();

	if(! empty($degree_map) && ! is_array($degree_map)){
		$degree_map = get_map_by_id(intval($degree_map));
		$degree_map = array_merge($empty_degree_map, $degree_map);
	}

	if (empty($degree_map['hours_to_graduate'])){
		$degree_map['hours_to_graduate'] = 120;
	}

	if (!isset($degree_map['courses']) || !is_array($degree_map['courses'])) {
		$degree_map['courses'] = build_empty_courses(); //pad the courses array so that we can loop through it with four years and three semesters each
	}else{
		$degree_map['courses'] = build_empty_courses($degree_map['courses']);
	}

	if (!isset($degree_map['hours']) || !is_array($degree_map['hours'])) {
		$degree_map['hours'] = build_empty_hours();
	}else{
		$degree_map['hours'] = build_empty_hours($degree_map['hours']); //pad the hours array so that we can loop through it with four years and three semesters each
	}


	$form = '
<div id="editHoursModal" title="Edit Degree Map Hours">
	<form method="POST" id="map_hours_form" name="map_hours_form"> 
			<fieldset>
			<input type="hidden" name="degree_map_id" value="'.$degree_map['id'].'">
			<legend id="course_hours" class="none">Course Hours</legend>
			<table aria-labelledby="course_hours" class="course_hours_table">
				<thead>
					<tr><th scope="column">Semester</th><th scope="column">First Year</th><th scope="column">Second Year</th><th scope="column">Third Year</th><th scope="column">Fourth Year</th></tr>
				</thead>
				<tbody>
					<tr><th scope="row">Fall</th>
						<td>
							<label class="sr-only" for="degree_map[hours][1][1]">Fall First Year Hours</label>
							<input type="text" name="degree_map[hours][1][1]" id="degree_map[hours][1][1]" value="'.$degree_map['hours'][1][1]['hours'].'">
							'.get_hours_span($degree_map, 1, 1).'
						</td>
						<td>
							<label class="sr-only" for="degree_map[hours][2][1]">Fall Second Year Hours</label>
							<input type="text" name="degree_map[hours][2][1]" id="degree_map[hours][2][1]" value="'.$degree_map['hours'][2][1]['hours'].'">
							'.get_hours_span($degree_map, 2, 1).'
						</td>
						<td>
							<label class="sr-only" for="degree_map[hours][3][1]">Fall Third Year Hours</label>
							<input type="text" name="degree_map[hours][3][1]" id="degree_map[hours][3][1]" value="'.$degree_map['hours'][3][1]['hours'].'">
							'.get_hours_span($degree_map, 3, 1).'
						</td>
						<td>
							<label class="sr-only" for="degree_map[hours][4][1]">Fall Fourth Year Hours</label>
							<input type="text" name="degree_map[hours][4][1]" id="degree_map[hours][4][1]" value="'.$degree_map['hours'][4][1]['hours'].'">
							'.get_hours_span($degree_map, 4, 1).'
						</td>
					</tr>
					<tr><th scope="row">Spring</th>
						<td>
							<label class="sr-only" for="degree_map[hours][1][2]">Spring First Year Hours</label>
							<input type="text" name="degree_map[hours][1][2]" id="degree_map[hours][1][2]" value="'.$degree_map['hours'][1][2]['hours'].'">
							'.get_hours_span($degree_map, 1, 2).'
						</td>
						<td>
							<label class="sr-only" for="degree_map[hours][2][2]">Spring Second Year Hours</label>
							<input type="text" name="degree_map[hours][2][2]" id="degree_map[hours][2][2]" value="'.$degree_map['hours'][2][2]['hours'].'">
							'.get_hours_span($degree_map, 2, 2).'
						</td>
						<td>
							<label class="sr-only" for="degree_map[hours][3][2]">Spring Third Year Hours</label>
							<input type="text" name="degree_map[hours][3][2]" id="degree_map[hours][3][2]" value="'.$degree_map['hours'][3][2]['hours'].'">
							'.get_hours_span($degree_map, 3, 2).'
						</td>
						<td>
							<label class="sr-only" for="degree_map[hours][4][2]">Spring Fourth Year Hours</label>
							<input type="text" name="degree_map[hours][4][2]" id="degree_map[hours][4][2]" value="'.$degree_map['hours'][4][2]['hours'].'">
							'.get_hours_span($degree_map, 4, 2).'
						</td>
					</tr>
					<tr><th scope="row">Summer</th>
						<td>
							<label class="sr-only" for="degree_map[hours][1][3]">Summer First Year Hours</label>
							<input type="text" name="degree_map[hours][1][3]" id="degree_map[hours][1][3]" value="'.$degree_map['hours'][1][3]['hours'].'">
							'.get_hours_span($degree_map, 1, 3).'
						</td>
						<td>
							<label class="sr-only" for="degree_map[hours][2][3]">Summer Second Year Hours</label>
							<input type="text" name="degree_map[hours][2][3]" id="degree_map[hours][2][3]" value="'.$degree_map['hours'][2][3]['hours'].'">
							'.get_hours_span($degree_map, 2, 3).'
						</td>
						<td>
							<label class="sr-only" for="degree_map[hours][3][3]">Summer Third Year Hours</label>
							<input type="text" name="degree_map[hours][3][3]" id="degree_map[hours][3][3]" value="'.$degree_map['hours'][3][3]['hours'].'">
							'.get_hours_span($degree_map, 3, 3).'
						</td>
						<td>
							<label class="sr-only" for="degree_map[hours][4][3]">Summer Fourth Year Hours</label>
							<input type="text" name="degree_map[hours][4][3]" id="degree_map[hours][4][3]" value="'.$degree_map['hours'][4][3]['hours'].'">
							'.get_hours_span($degree_map, 4, 3).'
						</td>
					</tr>
					<tr><th scope="row">Total</th>
						<td>
							<label class="sr-only" for="degree_map[hours][1][total_hours]">Total First Year Hours</label>
							<input type="text" name="degree_map[hours][1][total_hours]" id="degree_map[hours][1][total_hours]" value="'.$degree_map['hours'][1]['total_hours'].'">
							'.get_hours_span($degree_map, 1).'
						</td>
						<td>
							<label class="sr-only" for="degree_map[hours][2][total_hours]">Total Second Year Hours</label>
							<input type="text" name="degree_map[hours][2][total_hours]" id="degree_map[hours][2][total_hours]" value="'.$degree_map['hours'][2]['total_hours'].'">
							'.get_hours_span($degree_map, 2).'
						</td>
						<td>
							<label class="sr-only" for="degree_map[hours][3][total_hours]">Total Third Year Hours</label>
							<input type="text" name="degree_map[hours][3][total_hours]" id="degree_map[hours][3][total_hours]" value="'.$degree_map['hours'][3]['total_hours'].'">
							'.get_hours_span($degree_map, 3).'
						</td>
						<td>
							<label class="sr-only" for="degree_map[hours][4][total_hours]">Total Fourth Year Hours</label>
							<input type="text" name="degree_map[hours][4][total_hours]" id="degree_map[hours][4][total_hours]" value="'.$degree_map['hours'][4]['total_hours'].'">
							'.get_hours_span($degree_map, 4).'
						</td>
					</tr>
				</tbody>
			</table>
				<label for="hours_to_graduate" style="display: inline;">Total Hours to Graduate:</label>
				<input type="text" name="hours_to_graduate" id="hours_to_graduate"  style="display: inline; width: 60px;" value="'.$degree_map['hours_to_graduate'].'">
		</fieldset>
		<div class="modal-buttons">
			<div class="row">
				<div class="col-6">
			<button type="button" id="SaveHoursBtn" class="ui-button-primary button button-large"><svg xmlns="http://www.w3.org/2000/svg" viewbox="0 0 24 24" fill="currentColor" class="size-6" style="height: 1.5rem; width: 1.5rem;"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm.53 5.47a.75.75 0 0 0-1.06 0l-3 3a.75.75 0 1 0 1.06 1.06l1.72-1.72v5.69a.75.75 0 0 0 1.5 0v-5.69l1.72 1.72a.75.75 0 1 0 1.06-1.06l-3-3Z" clip-rule="evenodd" /></svg>Save Changes</button>
				</div>
				<div class="col-6">
			<button type="button" id="CancelHoursBtn" class="button cancelBtn" data-modal="editHoursModal" ><svg xmlns="http://www.w3.org/2000/svg" viewbox="0 0 24 24" fill="currentColor" class="size-6" style="height: 1.5rem; width: 1.5rem;"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-1.72 6.97a.75.75 0 1 0-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 1 0 1.06 1.06L12 13.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L13.06 12l1.72-1.72a.75.75 0 1 0-1.06-1.06L12 10.94l-1.72-1.72Z" clip-rule="evenodd" /></svg>Cancel</button>
			</div>
		</div>
	</form>
</div>
';
	return $form;
}

function calculate_hours($courses) {
    $minTotal = 0;
    $maxTotal = 0;
    
    foreach ($courses as $course) {
        $hoursStr = trim($course['hours']);
        // If the hours are just a number:
        if (is_numeric($hoursStr)) {
            $minTotal += (float)$hoursStr;
            $maxTotal += (float)$hoursStr;
        }
        // If the hours are a range like "2-4"
        elseif (preg_match('/^(\d+)\s*-\s*(\d+)$/', $hoursStr, $matches)) {
            $minTotal += (float)$matches[1];
            $maxTotal += (float)$matches[2];
        }
        // Otherwise, ignore or handle custom values
    }
    
    return ['min' => $minTotal, 'max' => $maxTotal];
}
/* for calculating total hours for a semester or year */
function get_course_hours($degree_map, $year, $semester) {
	$courses = $degree_map['courses'][$year][$semester];
	$hours = calculate_hours($courses);
	if ($hours['min'] == $hours['max']) {
		return $hours['min'];
	}else{
		return $hours['min'] . '-' . $hours['max'];
	}
}
/* for calculating total hours for a year */
function get_course_year_hours($degree_map, $year) {
	$hours = ['min' => 0, 'max' => 0];
	for ($semester = 1; $semester <= 3; $semester++) {
		$courses = $degree_map['courses'][$year][$semester];
		$semesterHours = calculate_hours($courses);
		$hours['min'] += $semesterHours['min'];
		$hours['max'] += $semesterHours['max'];
	}
	if ($hours['min'] == $hours['max']) {
		return $hours['min'];
	}else{
		return $hours['min'] . '-' . $hours['max'];
	}
}

/**
 * Parse the course hours field into a minimum (floor) and maximum (ceiling) value.
 * If the field is numeric (e.g. "3"), both floor and ceiling are the same.
 * If it’s a range (e.g. "3-4"), then floor is 3 and ceiling is 4.
 */
function parse_course_hours_range($hours) {
    if (is_numeric($hours)) {
        $num = floatval($hours);
        return ['floor' => $num, 'ceiling' => $num];
    }elseif(strpos($hours, '-') !== false) {
        $parts = explode('-', $hours);
        if (count($parts) === 2 && is_numeric(trim($parts[0])) && is_numeric(trim($parts[1]))) {
            return [
                'floor' => floatval(trim($parts[0])),
                'ceiling' => floatval(trim($parts[1]))
            ];
        }
    }
    // Fallback: if no valid number can be parsed, return 0 for both.
    return ['floor' => 0, 'ceiling' => 0];
}


function get_hours_span($degree_map, $year, $semester=null){
	$warning = null;
	$calc_hours = null;
	$manual_hours = null;
	if ($semester){
		$calc_hours = get_course_hours($degree_map, $year, $semester);
		$manual_hours = $degree_map['hours'][$year][$semester]['hours'];
	}else{
		$calc_hours = get_course_year_hours($degree_map, $year);
		$manual_hours = $degree_map['hours'][$year]['total_hours'];
	}
	if ($calc_hours == 0 && $manual_hours == 0 ) return '';
	if ($calc_hours != $manual_hours) $warning = ' warning';
	return '<span class="calculated_hours'.$warning.'">'.$calc_hours.'</span>';
}


/**
 * Loop through all courses in the degree map and sum up, per SGE category, 
 * the floor and ceiling hours. Then check whether the calculated range 
 * meets the required hours.
 *
 * Returns true if a warning should be issued.
 */
function calculate_sge_hours($degree_map) {

	global $sgeOptions;
	// Define SGE categories with minimum and maximum required hours.
	$sge_categories = array(
		'010' => ['min_required' => 6, 'max_required' => 6, 'floor_hours' => 0, 'ceiling_hours' => 0, 'count' => 0], 
		'020' => ['min_required' => 3, 'max_required' => 3, 'floor_hours' => 0, 'ceiling_hours' => 0, 'count' => 0], 
		'030' => ['min_required' => 3, 'max_required' => 3, 'floor_hours' => 0, 'ceiling_hours' => 0, 'count' => 0],
		'040' => ['min_required' => 4, 'max_required' => 5, 'floor_hours' => 0, 'ceiling_hours' => 0, 'count' => 0],
		'050' => ['min_required' => 6, 'max_required' => 6, 'floor_hours' => 0, 'ceiling_hours' => 0, 'count' => 0],
		'060' => ['min_required' => 6, 'max_required' => 6, 'floor_hours' => 0, 'ceiling_hours' => 0, 'count' => 0],
		'070' => ['min_required' => 6, 'max_required' => 6, 'floor_hours' => 0, 'ceiling_hours' => 0, 'count' => 0],
	);
    
    // Loop over each course.
    foreach ($degree_map['courses'] as $year => $semester) {
        foreach ($semester as $semester_no => $courses) {
            foreach ($courses as $course) {
                if (!empty($course['sge'])) {
                    $sge = $course['sge'];
                    $hoursRange = parse_course_hours_range($course['hours']);
                    if (isset($sge_categories[$sge])) {
                    	$sge_categories[$sge]['count'] += 1;
                        $sge_categories[$sge]['floor_hours'] += $hoursRange['floor'];
                        $sge_categories[$sge]['ceiling_hours'] += $hoursRange['ceiling'];
                    }
                }
            }
        }
    }
    
    // Check each SGE category to see if the calculated hours fall outside the required range.
    $sge_warning = '';
    foreach ($sge_categories as $sge => $category) {
        // For example, if even the maximum (ceiling) hours don't reach the minimum required,
        // or if even the minimum (floor) hours exceed the maximum allowed, flag a warning.
        if ($category['ceiling_hours'] < $category['min_required'] ||
            $category['floor_hours'] > $category['max_required']) {
				//we've got a warning!
				if (empty($sge_warning)){
					$sge_warning = '<ul>';
				}

				if ($category['ceiling_hours'] != $category['floor_hours']){
					$sge_hours = $category['floor_hours'].'-'.$category['ceiling_hours'];
				}else{
					$sge_hours = $category['floor_hours'];
				}
				if ($category['min_required'] == $category['max_required']){
					$sge_range = ' '.$category['min_required'].' hours. ';
				}else{
					$sge_range = ' range of  '.$category['min_required'].'-'.$category['max_required'].' hours. ';
				}
				if ($category['floor_hours'] > $category['max_required'] && $category['count'] == 1 ){
					continue;
				}else{
					$sge_warning .= '<li>'.$sgeOptions[$sge].' has '.$sge_hours.' hours and is not within the required '.$sge_range.'</li>';
				}
				
		}
    }
	
	if (!empty($sge_warning) &&  ($sge_warning != '<ul>')){
		$sge_warning .= '</ul><p>If you see a warning that says you are doing too many hours in a category, that is fine, <br />it is just intended to help you spot accidentally miscategorizing a course.</p>';
	}else{
		$sge_warning = null;
	}
	return $sge_warning;
}

function build_course_footnote_select2($course, $degree_map_id){

	$footnotes = get_degree_maps_footnotes($degree_map_id);
    $course_id = $course['id'];
	if (! empty($course['footnote_ids'])){
		$course_footnotes = json_decode($course['footnote_ids'], true);
	}else{
        $course_footnotes = [];
    }

    $allFootnotes = [];
    foreach ($footnotes as $footnote) {
        $allFootnotes[] = [
            'id' => $footnote['id'],
            'order' => $footnote['order'],
            'note' => htmlspecialchars($footnote['note'], ENT_QUOTES, 'UTF-8') // Escape special characters
        ];
    }

    // Start the HTML output safely
    $html = '<label for="footnotes">Footnotes</label>';
    $html .= '<select id="footnotes" name="footnotes[]" multiple class="select2">';

    foreach ($allFootnotes as $footnote) {
        $selected = in_array($footnote['id'], $course_footnotes) ? ' selected' : '';
        $html .= '<option value="' . $footnote['id'] . '"' . $selected . '>';
        $html .= $footnote['order'] . '. ' . substr($footnote['note'], 0, 40);
        $html .= '</option>';
    }

    $html .= '</select>'; // Ensure select is properly closed

    return $html;
}

/**
 * Build a multi-select of footnotes.
 *
 * @param string $name              Name attribute for the <select>.
 * @param array  $allFootnotes      Array of all footnotes from degree_map['footnotes'].
 * @param array  $course_footnotes Array of already-selected footnote IDs.
 * @param array  $attributes        Additional HTML attributes (id, class, etc.).
 * @return string
 */
function build_multi_select_footnotes($name, array $allFootnotes, array $course_footnotes, array $attributes = [])
{
    // Start building <select multiple>
    $html = '<select name="'.htmlspecialchars($name).'[]" multiple';
    foreach ($attributes as $attrName => $attrValue) {
        $html .= ' ' . ($attrName) . '="' . ($attrValue) . '"';
    }
    $html .= '>';
    //allFootnotes is an array of arrays with id, order, note
	//course_footnotes is an array of footnote ids
    // Create each <option>
    foreach ($allFootnotes as $footnote) {
        // footnote['id'] is presumably the real unique ID
        // Use footnote['order'] or footnote['note'] for the visible label
        $value = (int)$footnote['id'];
        $label = ''.$footnote['order'].') '.substr($footnote['note'], 0, 40); // or anything else like $footnote['note']

        $html .= '<option value="'.($value).'" data-order="'.($footnote['order']).'"';
        if (in_array($value, $course_footnotes)) {
            $html .= ' selected';
        }
        $html .= '>'.htmlspecialchars($label).'</option>';
    }

    $html .= '</select>';    

    return $html;
}

function get_course($course_id){
	global $mysqli;
	$sql = "SELECT * FROM `degree_maps_courses` WHERE `id` = ?";
	$stmt = $mysqli->prepare($sql);
	$stmt->bind_param("i", $course_id);
	$stmt->execute();
	$result = $stmt->get_result();
	$course = $result->fetch_assoc();
	$stmt->close();
	return $course;
}

function clone_degree_map($degree_map_id){
	global $timestamp;
	global $mysqli;
	if (! empty($_REQUEST['cloneMap']) && ($_REQUEST['cloneMap'] == 'Clone') && ! empty($_REQUEST['degree_map_id'])){ 
		$origMapId = (int)$_REQUEST['degree_map_id'];
		$currentAcademicYear = getCurrentAcademicYear();
		$newAcademicYear = $currentAcademicYear + 1;
		
		$mysqli->begin_transaction();
		try {
			// 1. Clone the degree map header
			$sqlHeader = "
			INSERT INTO `degree_maps` ( `program_id`, `major`, `college`, `degree_type`, `department`, `note`, `academic_year`, `hours_to_graduate`)
			  SELECT `program_id`, `major`, `college`, `degree_type`, `department`, `note`, ?, `hours_to_graduate`
			FROM `degree_maps`
			WHERE `id` = ?";
			
			if (!($stmtHeader = $mysqli->prepare($sqlHeader))) {
				throw new Exception("Prepare header failed: " . $mysqli->error);
			}
			$stmtHeader->bind_param("ii", $newAcademicYear, $origMapId); // adjust types as needed
			if (!$stmtHeader->execute()) {
				throw new Exception("Execute header failed: " . $stmtHeader->error);
			}
			// Get new degree map id
			$newMapId = $mysqli->insert_id;
			$stmtHeader->close();


			$footnoteMapping = []; // oldID => newID

			// Get all footnotes for the original degree map.
			$sql = "SELECT * FROM `degree_maps_footnotes` WHERE `degree_map_id` = ?";
			if ($stmt = $mysqli->prepare($sql)) {
				$stmt->bind_param('i', $origMapId);
				$stmt->execute();
				$result = $stmt->get_result();
				while ($footnote = $result->fetch_assoc()) {
					$oldId = $footnote['id'];
			
					// Insert the cloned footnote for the new degree map.
					$insertSql = "INSERT INTO `degree_maps_footnotes` (`degree_map_id`, `order`, `note`, `timestamp`) VALUES (?, ?, ?, NOW())";
					if ($insertStmt = $mysqli->prepare($insertSql)) {
						// Use the same values except for the degree_map_id which is new.
						$insertStmt->bind_param('iis', $newMapId, $footnote['order'], $footnote['note']);
						$insertStmt->execute();
						$newId = $mysqli->insert_id;
						$footnoteMapping[$oldId] = $newId;
						$insertStmt->close();
					} else {
						throw new Exception("Failed to prepare insert for footnote: " . $mysqli->error);
					}
				}
				$stmt->close();
			} else {
				throw new Exception("Failed to prepare select for footnotes: " . $mysqli->error);
			}


			// Get all courses for the original degree map.
			$sql = "SELECT * FROM `degree_maps_courses` WHERE `degree_map_id` = ?";
			if ($stmt = $mysqli->prepare($sql)) {
				$stmt->bind_param('i', $origMapId);
				$stmt->execute();
				$result = $stmt->get_result();
				while ($course = $result->fetch_assoc()) {
					// Assume footnotes are stored as JSON.
					if (empty($course['footnote_ids'])) {
						$footnotes_json = '[]';
					} else {
						$footnotes_json = $course['footnote_ids'];
					}
					$footnotesArray = json_decode($footnotes_json, true);
					if (is_array($footnotesArray)) {
						// Replace old footnote IDs with new ones.
						foreach ($footnotesArray as &$fid) {
							if (isset($footnoteMapping[$fid])) {
								$fid = $footnoteMapping[$fid];
							}
						}
						$newFootnotesJson = json_encode($footnotesArray);
					} else {
						$newFootnotesJson = $footnotes_json; // leave as-is if not an array
					}

					// Insert the new course record.
					$insertSql = "INSERT INTO `degree_maps_courses` 
						(`degree_map_id`, `course_info`, `hours`, `footnote_ids`, `sge`, `extra`, `order`, `semester`, `year`, `timestamp`)
						VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
					if ($insertStmt = $mysqli->prepare($insertSql)) {
						$insertStmt->bind_param(
							'issssiiii',
							$newMapId,
							$course['course_info'],
							$course['hours'],
							$newFootnotesJson,
							$course['sge'],
							$course['extra'],
							$course['order'],
							$course['semester'],
							$course['year']
						);
						$insertStmt->execute();
						$insertStmt->close();
					} else {
						throw new Exception("Failed to prepare insert for course: " . $mysqli->error);
					}
				}
				$stmt->close();
			} else {
				throw new Exception("Failed to prepare select for courses: " . $mysqli->error);
			}

			// Clone semester hours
			$sqlSemesterHours = "INSERT INTO `degree_maps_semester_hours` (`degree_map_id`, `year`, `semester`, `hours`)
			SELECT ?, `year`, `semester`, `hours`
			FROM `degree_maps_semester_hours`
			WHERE `degree_map_id` = ?";

			if (!($stmtSem = $mysqli->prepare($sqlSemesterHours))) {
				throw new Exception("Prepare for semester hours clone failed: " . $mysqli->error);
			}
			$stmtSem->bind_param("ii", $newMapId, $origMapId);
			if (!$stmtSem->execute()) {
				throw new Exception("Execute for semester hours clone failed: " . $stmtSem->error);
			}
			$stmtSem->close();

			// Clone year hours
			$sqlYearHours = "INSERT INTO `degree_maps_year_hours` (`degree_map_id`, `year`, `hours`)
			SELECT ?, `year`, `hours`
			FROM `degree_maps_year_hours`
			WHERE `degree_map_id` = ?";

			if (!($stmtYear = $mysqli->prepare($sqlYearHours))) {
				throw new Exception("Prepare for year hours clone failed: " . $mysqli->error);
			}
			$stmtYear->bind_param("ii", $newMapId, $origMapId);
			if (!$stmtYear->execute()) {
				throw new Exception("Execute for year hours clone failed: " . $stmtYear->error);
			}
			$stmtYear->close();

			$mysqli->commit();

		} catch (Exception $ex) {
			$mysqli->rollback();
			error_log($ex->getMessage());

		}
	}
	return $newMapId;
}


function get_blank_course($id = 'new', $degree_map = null, $order = null, $semester = null, $year = null){
	if ($id == 'new'){
		$id = 'new_'.rand(1000, 9999);
	}

	if (! empty($degree_map) && ((int)$degree_map > 0)){
		$degree_map = null;
	}

	if (empty($degree_map)){
		$degree_map = build_empty_degree_map();
	}else{
		$degree_map = get_map_by_id((int)$degree_map);
	}

	return array(
		'id' => $id,
		'degree_map_id' => $degree_map['id'],
		'course_info' => NULL,
		'hours' => NULL,
		'year' => $year,
		'semester' => $semester,
		'order' => $order,
		'footnote_ids' => NULL,
		'sge' => NULL,
		'extra' => NULL,
		'scbcrse_subj_code' => NULL,
		'scbcrse_crse_numb' => NULL,
		'timestamp' => NULL
	);

  
}

function build_single_course_form($course, $degree_map, $year, $semester_no){
	global $yearOptions, $semesterOptions, $sgeOptions;
	if (isset($degree_map['courses'][$year][$semester_no])){
		$course_count = count($degree_map['courses'][$year][$semester_no]);
	}else{
		$course_count = 0;
	}

	if (empty($course['course_info'])){
		$headline = 'New Course';
		$course_count++; //make space for the latest course
	}else{
		$headline = htmlspecialchars($course['course_info']);
	}

	if (empty($course['order']) || $course['order'] == '0'){
		$course['order'] = $course_count; //if we don't have an order for it, put it at the end
		$headline .= ' ('.$course['order'].')';
	}

	$courseOrderOptions = [];
	for ($i = 1; $i <= $course_count; $i++) {
		$courseOrderOptions[$i] = (string)$i;
	}

	$course_footnotes = [];	
	if (! empty($course['footnote_ids'])){
		$course_footnotes = json_decode($course['footnote_ids'], true);
	}


	$courseForm = '
<div id="editCourseModal" title="Edit '.$headline.'">
	<form id="editCourseForm">
		<input type="hidden" name="course_id" id="course_id" value="'.$course['id'].'"> 
		<input type="hidden" name="degree_map_id" id="degree_map_id_course" value="'.$degree_map['id'].'">
		<label for="course_info">Course Info</label> 
		<input type="text" name="course_info" id="course_info" class="form-control" value="'.$course['course_info'].'">
		<span class="help-block">Start typing for autocomplete: use titles or course numbers like "ENGL 101." Hours will autofill. Both can be changed manually.</span>
		<input type="hidden" name="scbcrse_crse_numb" id="scbcrse_crse_numb" value="'.$course['scbcrse_crse_numb'].'">
		<input type="hidden" name="scbcrse_subj_code" id="scbcrse_subj_code" value="'.$course['scbcrse_subj_code'].'">
		<div class="form-group">
			<div class="row">
				<div class="col-2">
					<label for="hours">Credit Hours</label> 
					<input type="text" name="hours" id="hours" class="form-control" value="'.$course['hours'].'">
				</div>
				<div class="col-6">
        '.build_course_footnote_select2($course, $degree_map['id']).'
				</div>
				<div class="col-4">
					<label for="sge">SGE Code</label>'.PHP_EOL;
	$courseForm .= build_select(
				'sge',
				$sgeOptions,
				$course['sge'],
				[
					'id' => 'sge',
					'class' => 'form-control'
				]
			);
	$courseForm .= '
				</div>
			</div>
		</div>
		<div class="form-group">
			<div id="advanced">
				<h3>Advanced</h3>
				<div>
					<div class="row">
						<div class="col-4">
							<label for="year">Year</label>'.PHP_EOL;
							$courseForm .= build_select(
								'year',
								$yearOptions,
								$course['year'],
								[
									'id' => 'year',
									'class' => 'form-control'
								]
							);
		$courseForm .= '
						</div>
						<div class="col-5">
							<label for="semester">Semester</label>'.PHP_EOL;
							$courseForm .= build_select(
								'semester',
								$semesterOptions,
								$course['semester'],
								[
									'id' => 'semester',
									'class' => 'form-control'
								]
							);
		$courseForm .= ' 
						</div>
						<div class="col-3">
							<label for="order">Order</label>'
							.build_select(
								'order',
								$courseOrderOptions,
								$course['order'],
								[
									'id' => 'order',
									'class' => 'form-control'
								]
							);
		$courseForm .= '
						</div>
					</div>
					<div class="row">
						<div class="col-12">
							<label for="extra">Extra Note</label>
							<textarea name="extra" id="extra" placeholder="" rows="3">'.$course['extra'].'</textarea>
							<span class="help-block">This is just extra information for one course, not footnotes</span>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="modal-buttons">
			<div class="row">
				<div class="col-6">
			<button type="button" id="saveCourseBtn" class="ui-button-primary button button-large"><svg xmlns="http://www.w3.org/2000/svg" viewbox="0 0 24 24" fill="currentColor" class="size-6" style="height: 1.5rem; width: 1.5rem;"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm.53 5.47a.75.75 0 0 0-1.06 0l-3 3a.75.75 0 1 0 1.06 1.06l1.72-1.72v5.69a.75.75 0 0 0 1.5 0v-5.69l1.72 1.72a.75.75 0 1 0 1.06-1.06l-3-3Z" clip-rule="evenodd" /></svg>Save Changes</button>
				</div>
				<div class="col-6">
			<button type="button" id="cancelCourseBtn" class="button cancelBtn"  data-modal="editCourseModal"><svg xmlns="http://www.w3.org/2000/svg" viewbox="0 0 24 24" fill="currentColor" class="size-6" style="height: 1.5rem; width: 1.5rem;"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-1.72 6.97a.75.75 0 1 0-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 1 0 1.06 1.06L12 13.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L13.06 12l1.72-1.72a.75.75 0 1 0-1.06-1.06L12 10.94l-1.72-1.72Z" clip-rule="evenodd" /></svg>Cancel</button>
			';
	if ($course['id'] != 'new'){
		$courseForm .= '
			<button type="button" id="deleteCourseBtn" class="ui-button-danger button"><svg xmlns="http://www.w3.org/2000/svg" viewbox="0 0 24 24" fill="currentColor" class="size-6" style="height: 1.5rem; width: 1.5rem;"><path fill-rule="evenodd" d="M16.5 4.478v.227a48.816 48.816 0 0 1 3.878.512.75.75 0 1 1-.256 1.478l-.209-.035-1.005 13.07a3 3 0 0 1-2.991 2.77H8.084a3 3 0 0 1-2.991-2.77L4.087 6.66l-.209.035a.75.75 0 0 1-.256-1.478A48.567 48.567 0 0 1 7.5 4.705v-.227c0-1.564 1.213-2.9 2.816-2.951a52.662 52.662 0 0 1 3.369 0c1.603.051 2.815 1.387 2.815 2.951Zm-6.136-1.452a51.196 51.196 0 0 1 3.273 0C14.39 3.05 15 3.684 15 4.478v.113a49.488 49.488 0 0 0-6 0v-.113c0-.794.609-1.428 1.364-1.452Zm-.355 5.945a.75.75 0 1 0-1.5.058l.347 9a.75.75 0 1 0 1.499-.058l-.346-9Zm5.48.058a.75.75 0 1 0-1.498-.058l-.347 9a.75.75 0 0 0 1.5.058l.345-9Z" clip-rule="evenodd" /></svg>Delete</button>'.PHP_EOL;
	}
	$courseForm .= '
			</div>
		</div>
			</div>
		</div>
	</form>
</div>
';
	return $courseForm;
}


/**
 * Given a name, an array of options, and a selected value, generate a <select>.
 *
 * @param string $name         The name attribute for the <select>.
 * @param array  $options      Key/value pairs for <option> elements.
 * @param mixed  $selected     The currently selected key.
 * @param array  $attributes   Any additional attributes for the <select>.
 * @return string              The complete <select> HTML.
 */
function build_select($name, array $options, $selected = null, array $attributes = [], $chooseOne = false) {
    // Start the <select> element
    $html = '<select name="'.$name.'"'; //names are programatically built, so lets not htmlspecialchar them
    // Add extra attributes (id, class, etc.)
    foreach ($attributes as $attrName => $attrValue) {
        $html .= ' ' . ($attrName) . '="' . ($attrValue) . '"';
    }
    $html .= '>';

    $html .= build_options($options, $selected, $chooseOne);

    $html .= '</select>';
    return $html;
}

function build_options(array $options, $selected = null, $chooseOne = false, $use_keys = true) {
	$html = '';
	if ($chooseOne) {
		if (empty ($selected)){
			$html .= '	<option disabled selected>Choose one</option>';
		}else{
			$html .= '	<option disabled>Choose one</option>';
		}
	}
	if ($use_keys){ //if the array is associative, use the keys as the values
		foreach ($options as $value => $label) {
			$html .= '<option value="' . htmlspecialchars($value) . '"';
			if ((string)$value === (string)$selected) {
				$html .= ' selected';
			}
			$html .= '>' . htmlspecialchars($label) . '</option>';
		}
	}else{
		foreach ($options as $value) {
			$html .= '<option value="' . htmlspecialchars($value) . '"';
			if ((string)$value === (string)$selected) {
				$html .= ' selected';
			}
			$html .= '>' . htmlspecialchars($value) . '</option>';
		}
	}
	return $html;
}


function get_academic_years_options($selected = ''){
	$next_year = getCurrentAcademicYear() + 1;
    $years = get_academic_years_array();
	if (! in_array($next_year, $years)){
		array_unshift($years, $next_year); //add next year to the beginning of the array
	}
	$options = [];
	foreach ($years as $year){
		$options[$year] = ($year - 1).' - '.$year;
	}
	$return = build_options($options, $selected, true);
	return $return;
}

function get_academic_years_array(){
	global $mysqli;
    $query = "SELECT DISTINCT(`academic_year`) FROM `degree_maps` WHERE 1 ORDER BY `academic_year` DESC;";
	$result = $mysqli->query($query);
    while ($row = $result->fetch_assoc()){
   		$return[] = $row['academic_year'];
    }
    return $return;
}



function get_all_colleges_options($selected = '', $use_id = false){
    $colleges = get_all_colleges_array();
	if (empty ($selected)){
		$return = '	<option disabled selected>Choose one</option>';
	}else{
		$return = '	<option disabled>Choose one</option>';
	}
    if (! empty($colleges)){
    	foreach ($colleges as $college){
			$this_selected = false;
			if ($use_id){
				if ($college['id'] == $selected){
					$this_selected = ' selected';
				}
				$return .= '	<option value="'.$college['id'].'"'.$this_selected.'>'.$college['name'].'</option>'.PHP_EOL;
			}else{
				if ($college['name'] == $selected){
					$this_selected = ' selected';
				}
				$return .= '	<option value="'.$college['name'].'"'.$this_selected.'>'.$college['name'].'</option>'.PHP_EOL;
			}
		}
	}
	return $return;
}

function get_program_options($selected = '', $degree_map = null){
    $programs = get_programs_array($degree_map);
	if (empty ($programs)){
		$programs = get_programs_array();
	}
    $return = '	<option value="">None selected</option>';
    if (! empty($programs)){
    	foreach ($programs as $program){
			$this_selected = false;
			if ($program['id'] == $selected){
				$this_selected = ' selected';
			}
    		$return .= '	<option value="'.$program['id'].'"'.$this_selected.'>'.$program['name'].'</option>'.PHP_EOL;
    	}
    }
	return $return;
}

function get_department_options($selected_department = '', $college = null, $use_id = false){
	//selected department is a string or id; college is a string or id
	//if department is a string, get the id but return a string as the value
	//if department is an id, return the id as the value
	$department_id = null;
	
	if (! empty($selected_department) && (is_numeric($selected_department))){
		$department_id = $selected_department;

	}elseif (! empty($selected_department) && (is_string($selected_department))){
		$department_id = get_department_id($selected_department);
	}

	$college_id = null;
	if (! empty($college) && (is_numeric($college))){
		$college_id = $college;
	}elseif (! empty($college) && (is_string($college))){
		$college_id = get_college_id($college);
	}
	$departments = get_departments_array($college_id);

	$return = '	<option value="">None selected</option>';
    if (! empty($departments)){
    	foreach ($departments as $department){
			$this_selected = false;
			if ($use_id){
				if ($department['id'] == $selected_department){
					$this_selected = ' selected';
				}
    			$return .= '	<option value="'.$department['id'].'"'.$this_selected.'>'.$department['department'].'</option>'.PHP_EOL;
			}else{
				if ($department['department'] == $selected_department){
					$this_selected = ' selected';
				}
    			$return .= '	<option value="'.$department['department'].'"'.$this_selected.'>'.$department['department'].'</option>'.PHP_EOL;
			}
    	}
    }
	return $return;
}

function get_college_id($college_name){
	global $mysqli;
	$query = "SELECT `id` FROM `majors_colleges` WHERE `name` = ?;";
	if ($stmt = $mysqli->prepare($query)) {
		$stmt->bind_param('s', $college_name);
		$stmt->execute();
		$stmt->bind_result($id);
		$stmt->fetch();
		$stmt->close();
	}
	return $id;
}

function get_department_id($department_name){
	global $mysqli;
	$query = "SELECT `id` FROM `majors_departments` WHERE `department` = ?;";
	if ($stmt = $mysqli->prepare($query)) {
		$stmt->bind_param('s', $department_name);
		$stmt->execute();
		$stmt->bind_result($id);
		$stmt->fetch();
		$stmt->close();
	}
	return $id;
}

function get_departments_array($college_id = null){
	global $mysqli;
	if (empty($college_id)){
		$query = "SELECT * FROM `majors_departments` WHERE 1 ORDER BY `department` ASC;";
		$result = $mysqli->query($query);
		while ($row = $result->fetch_assoc()){
			$id = $row['id'];
			$return[$id] = $row;
		}
	}else{
		$query = "SELECT * FROM `majors_departments` WHERE `college_id` = ? ORDER BY `department` ASC;";
		if ($stmt = $mysqli->prepare($query)) {
			$stmt->bind_param('s', $college_id);
			$stmt->execute();
			$result = $stmt->get_result();
			while ($row = $result->fetch_assoc()){
				$id = $row['id'];
				$return[$id] = $row;
			}
			$stmt->close();
		}
	}
	return $return;
}

function get_all_colleges_array(){
	global $mysqli;
    $query = "SELECT * FROM `majors_colleges` WHERE 1 ;"; //ORDER BY `name` ASC
	$result = $mysqli->query($query);
    while ($row = $result->fetch_assoc()){
		$id = $row['id'];
   		$return[$id] = $row;
    }
    return $return;
}

function get_programs_array($degree_map = null){
	global $mysqli;
	$where = 'WHERE 1 ';
	if (! empty($degree_map) && ((int)$degree_map['id'] > 0)){
		$where .= 'AND `college` = ? AND `department` = ? AND `program_type` = ? ';
	}
    $query = "SELECT `id`, concat(`academic_program`, ' - ', `program_type`) as `name` FROM `majors_academic_programs` ".$where." AND `graduate` IS NULL ORDER BY `name` ASC;"; //
	$return = [];
	if ($stmt = $mysqli->prepare($query)) {
		if (! empty($degree_map) && ((int)$degree_map['id'] > 0)){
			$stmt->bind_param('sss', $degree_map['college'], $degree_map['department'], $degree_map['degree_type']);
		}
		$stmt->execute();
		$result = $stmt->get_result();
		while ($row = $result->fetch_assoc()){
			$return[] = $row;
		}
		$stmt->close();
	}
	return $return;
}

function get_user($id){
	global $mysqli;
	$id = (int)$id;
	$query = "SELECT * FROM `majors_users` WHERE `id` = ?;";
	if ($stmt = $mysqli->prepare($query)) {
		$stmt->bind_param('i', $id);
		$stmt->execute();
		$result = $stmt->get_result();
		$user = $result->fetch_assoc();
		$stmt->close();
	}
	return $user;
}

function update_degree_map_course_order($course_id, $year, $semester, $order) {
    global $mysqli;
    $stmt = $mysqli->prepare("UPDATE `degree_maps_courses` SET `year` = ?, `semester` = ?, `order` = ? WHERE `id` = ?");
    $stmt->bind_param('iiii', $year, $semester, $order, $course_id);
    return $stmt->execute();
}
