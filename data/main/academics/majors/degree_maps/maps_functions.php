<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/../config/functions.php');

if (! empty($_REQUEST['map_id'])){
	$degree_map_id = (int)$_REQUEST['map_id'];
	$_GET['degree_map_id'] = $degree_map_id;
}

function get_colleges($academic_year = ''){
	global $mysqli;
	if (empty ($academic_year)){
		$academic_year = getCurrentAcademicYear();
	}
    $query = "SELECT DISTINCT(`college`) FROM `degree_maps` WHERE `academic_year` = '".$academic_year."' ORDER BY `college` ASC;";
	$result = $mysqli->query($query);
    $return = null;
    while ($row = $result->fetch_assoc()){
    	if (! empty($row['college'])){
    		$return .= '                        <option value="'.urlencode($row['college']).'">'.$row['college'].'</option>'.PHP_EOL;
    	}
    }
	return $return;
}

function get_colleges_array($academic_year = ''){
	global $mysqli;
	if (empty ($academic_year)){
		$academic_year = get_year();
	}
	$stmt = $mysqli->prepare("SELECT DISTINCT(`college`) FROM `degree_maps` WHERE `academic_year` = ? ORDER BY `college` ASC");
    $stmt->bind_param("i", $academic_year);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    while ($row = $result->fetch_assoc()){
    	if (! empty($row['college'])){
    		$return[] = $row['college'];
    	}
    }
    return $return;
}

//get most recent accademic year in database
function get_year(){
	global $mysqli;
	$query = "SELECT DISTINCT(`academic_year`) FROM `degree_maps` WHERE 1 ORDER BY `academic_year` DESC LIMIT 1;";
	$result = $mysqli->query($query);
	$row = $result->fetch_assoc();
	return $row['academic_year'];
}
//don't use the database, 
function getCurrentAcademicYear() {
    // Get the numeric month (1–12) and the full current year.
    $month = (int) date('n');
    $year  = (int) date('Y');
    // If the month is August (8) or later, the academic year is next year.
    if ($month >= 8) {
        return $year + 1;
    }
    // Otherwise (months 1–7), the academic year is the current year.
    return $year;
}


function prepare_query_debug($query, $types, $params) {
    foreach ($params as $index => $param) {
        $query = preg_replace('/\?/', $param, $query, 1);
    }
    return $query;
}


//get all available years
function get_years(){
	global $mysqli;
	$query = "SELECT DISTINCT(`academic_year`) FROM `degree_maps` WHERE 1 ORDER BY `academic_year` DESC;";
	$result = $mysqli->query($query);
	$years = array();
	while ($row = $result->fetch_assoc()){
		$years[] = $row['academic_year'];
	}
	return $years;
}

function get_maps($order = '', $ids = ''){
	global $mysqli;
	global $debug;
	global $academic_year;
	global $college;
	
	if (  ( empty($academic_year) ) AND (! empty($_REQUEST['academic_year']) )  ){
		$academic_year = (int)$_REQUEST['academic_year'];
	}
	//if it wasn't passed and post didn't have it (or had zero), then get one
	if (empty ($academic_year)){
		$academic_year = get_year();
	}
	if (empty($order)){
		global $order;
	}
	if ($order == 'college'){
		$order = ' ORDER BY `college` ASC, `major` ASC, `degree_type` ASC ';
	}else{
		$order = ' ORDER BY `major` ASC, `degree_type` ASC ';
	}
	if ($college == 'all'){
		$college = null;
	}
    $query = "SELECT * FROM `degree_maps` WHERE `academic_year` = ? ";
	$bind_param = 'i';

    if (! empty($college)){
		$query .= ' AND `college` = ? ';
		$bind_param .= 's';
    }

    if (! empty($ids)){
    	$ids = implode(',', $ids);
		$query .= ' AND `id` IN (?) ';
		$bind_param .= 's';
    }

    $query .= $order.";";

	$stmt = $mysqli->prepare($query);
	if ($bind_param == 'i'){
		$stmt->bind_param($bind_param, $academic_year);
	 	 // $debug .= '<p>' .  prepare_query_debug($query, 'i', array($academic_year)) . '</p>'.PHP_EOL;
	}elseif ($bind_param == 'iss'){
		$stmt->bind_param($bind_param, $academic_year, $college, $ids);
	 	 // $debug .= '<p>' .  prepare_query_debug($query, 'iss', array($academic_year, $college, $ids)) . '</p>'.PHP_EOL;
	}else{// $bind_param == 'is'
		if (empty($college)){
			$stmt->bind_param($bind_param, $academic_year, $ids);
		 	 // $debug .= '<p>' .  prepare_query_debug($query, 'is', array($academic_year, $ids)) . '</p>'.PHP_EOL;
		}else {
			$stmt->bind_param($bind_param, $academic_year, $college);
		 	 // $debug .= '<p>' .  prepare_query_debug($query, 'is', array($academic_year, $college)) . '</p>'.PHP_EOL;
		}
	}
 	// $debug .= '<p>' .  prepare_query_debug($query, 'i', array($academic_year)) . '</p>'.PHP_EOL;
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

	$rows = $result->fetch_all(MYSQLI_ASSOC);
	 // $debug .= '<p>num_rows '.count($rows).'</p>'.PHP_EOL;


/*
    while ($row = $result->fetch_assoc()){
   		$return[] = $row;
    }
*/
    if (empty($rows)){
    	return null;
    }
	return $rows;
}

function maps_by_alpha($maps = array()){
	global $debug;
	 // $debug .= PHP_EOL.'<p>maps_by_alpha()</p>'.PHP_EOL;
	 // $debug .= PHP_EOL.'<p>maps count: '.count($maps).'</p>'.PHP_EOL;
	if (empty ($maps) OR !is_array($maps)){
		$maps = get_maps('alpha');
	}
	if (! is_array($maps)){
		return $maps;
	}
	$return = '<div class="alpha-list">'.PHP_EOL;
	$alpha = NULL;
	foreach ($maps as $map){
		$letter = strtoupper(substr($map['major'], 0, 1));
		if ($alpha != $letter){
			if (! empty ($alpha)){
				$return .= '	</ul>'.PHP_EOL; //close previous alpha if needed
			}
			$return .= '	<hr />'.PHP_EOL;
			$return .= '	<div class="alpha-list__items">'.PHP_EOL;
			$return .= '		<header><h2 class="heading4"><a id="'.$letter.'" name="'.$letter.'"></a>'.$letter.'</h2></header>'.PHP_EOL;
			$return .= '	</div>'.PHP_EOL;
			$return .= '	<ul>'.PHP_EOL;
			$alpha = $letter;
		}
		$return .= '		<li><a href="maps.php?degree_map_id='.$map['id'].'">'.$map['major'].'</a> — '.$map['degree_type'].'</li>'.PHP_EOL;
 	}
	$return .= '	</ul>'.PHP_EOL; //close final alpha list
	$return .= '</div>'.PHP_EOL; // class="alpha-list" (end)
	
	return $return;
	
}

function maps_by_college($maps = array()){
	if (empty ($maps) OR !is_array($maps)){
		$maps = get_maps('college');
	}
	global $debug;
	 // $debug .= 'maps_by_college()'.PHP_EOL;
	$return = '<div class="alpha-list">'.PHP_EOL;
	$college = NULL;
	foreach ($maps as $map){
		if ($college != $map['college']){
			if (! empty($college)){
				$return .= '	</ul>'.PHP_EOL;
			}
			$return .= '	<hr />'.PHP_EOL;
			$return .= '	<div class="alpha-list__items">'.PHP_EOL;
			$return .= '		<header><h2 class="heading4"><a id="'.urlencode(strtolower($map['college'])).'" name="'.urlencode(strtolower($map['college'])).'"></a>'.$map['college'].'</h2></header>'.PHP_EOL;
			$return .= '	</div>'.PHP_EOL;
			$return .= '	<ul>'.PHP_EOL;
			$college = $map['college'];
		}
		$return .= '		<li><a href="'.$_SERVER['PHP_SELF'].'?degree_map_id='.$map['id'].'">'.$map['major'].'</a> — '.$map['degree_type'].'</li>'.PHP_EOL;
	
	}
	$return .= '	</ul>'.PHP_EOL;
	$return .= '</div>'.PHP_EOL; // class="alpha-list" (end)
	
	return $return;
	
}

function get_map_by_id($degree_map_id){
	global $mysqli;
	$stmt = $mysqli->prepare("SELECT * FROM `degree_maps` WHERE `id` = ? LIMIT 1;");
    $stmt->bind_param("i", $degree_map_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $degree_map = null;
    $degree_map = $result->fetch_assoc();
    
	$degree_map['footnotes'] = get_degree_maps_footnotes($degree_map_id);
	$degree_map['hours'] = get_degree_maps_semester_hours($degree_map_id);
	$degree_map['courses'] = get_degree_maps_courses($degree_map_id);
    
    
	return $degree_map;

}

function get_degree_maps_courses($degree_map_id){
	global $mysqli;
	$query = "SELECT * FROM `degree_maps_courses` WHERE `degree_map_id` = ".(int)$degree_map_id." ORDER BY `year` ASC, `semester` ASC, `order` ASC;";
	$result = $mysqli->query($query);
	$courses = array();
	if (! empty($result)){
		while ($course = $result->fetch_assoc()){
			$courses[$course['year']][$course['semester']][$course['order']] = $course;
		}
	}
   	if ($mysqli->error){
   		return false;
   	}
	return $courses;
}

function get_degree_maps_footnotes($degree_map_id){
	global $mysqli;
	$query = "SELECT * FROM `degree_maps_footnotes` WHERE `degree_map_id` = ".(int)$degree_map_id." ORDER BY `order` ASC;";
	$result = $mysqli->query($query);
	$footnotes = array();
	while ($footnote = $result->fetch_assoc()){
		$footnotes[$footnote['id']] = $footnote;
	}
   	if ($mysqli->error){
   		return false;
   	}
	return $footnotes;
}
function get_footnote($footnote_id){
	global $mysqli;
	$footnote_id = (int)$footnote_id;
	$query = "SELECT * FROM `degree_maps_footnotes` WHERE `id` = ".$footnote_id." LIMIT 1;";
	$result = $mysqli->query($query);
	$footnote = $result->fetch_assoc();
   	if ($mysqli->error){
   		return false;
   	}
	return $footnote;
}

function get_degree_maps_semester_hours($degree_map_id){
	global $mysqli;
	$stmt = $mysqli->prepare("SELECT * FROM `degree_maps_semester_hours` WHERE `degree_map_id` = ? ORDER BY `year` ASC, `semester` ASC;");
    $stmt->bind_param("i", $degree_map_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
	$semester_hours = array();
	while ($hours = $result->fetch_assoc()){
		$semester_hours[$hours['year']][$hours['semester']] = $hours;
	}
   	if ($mysqli->error){
   		return false;
   	}
   	foreach ($semester_hours as $year => $hours){
   		$semester_hours[$year]['total_hours'] = get_degree_maps_year_hours($degree_map_id, $year);
   	}
   	
	return $semester_hours;
}

function get_degree_maps_year_hours($degree_map_id, $year) {
	global $mysqli;
    $stmt = $mysqli->prepare("SELECT `hours` FROM `degree_maps_year_hours` WHERE `degree_map_id` = ? AND `year` = ?");
    $stmt->bind_param("ii", $degree_map_id, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    if (empty($result)) {
		// echo "Query string: SELECT * FROM `degree_maps_year_hours` WHERE `degree_map_id` = ".$degree_map_id." ORDER BY `year` ASC';".PHP_EOL;
		return false;
    } else {
    	$year_hours = $result->fetch_assoc(); // Use fetch_assoc() for an associative array
		if (! empty($year_hours['hours'])){
    	return $year_hours['hours'];
		}else{
			return false;
			error_log('No hours found for year '.$year.' in degree map '.$degree_map_id.' '.print_r($year_hours, true));
		}
    }
}


function addOrdinalNumberSuffix($num) {
	if (!in_array(($num % 100),array(11,12,13))){
		switch ($num % 10) {
			// Handle 1st, 2nd, 3rd
			case 1:  return $num.'st';
			case 2:  return $num.'nd';
			case 3:  return $num.'rd';
		}
	}
	return $num.'th';
}

function display_degree_map($degree_map_id){
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
	
	if (! empty($degree_map)){
		$main_content .= PHP_EOL.'
<hr class="noprint hr-yellow-bar" />
<h2 class="noprint" style="text-align: center;">Degree Map</h2>
<hr class="noprint hr-yellow-bar" />
<div class="row">
  <div class="col-2"><img src="/_resources/images/logo-blacktype.svg" alt="Wichita State University" class="wsu-logo"></div>
  <div class="col-10">
			'.PHP_EOL;
		$full_year = ($degree_map['academic_year']-1).' - '.$degree_map['academic_year'];
		$main_content .= '	<h3 style="margin-top: 0; line-height: 1;">'.$degree_map['college'].'</h3>
		<h4>'.$degree_map['degree_type'].' in '.$degree_map['major'].' ('.$full_year.')</h4>';
		$main_content .= '	</div>
	 </div>
<img src="_images/yellow-line.svg" alt="" class="yellow-line" aria-hidden="true">		';

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
	if (! empty($degree_map['courses'])) {


		foreach ($degree_map['courses'] as $course_year => $courses){
			//$course_year is a digit, like 1-4
			$course_year_text = addOrdinalNumberSuffix($course_year).' year';
			if (! isset($table_row)){
			$main_content .= ''.PHP_EOL;
			
			}else{
			$main_content .= '
	<hr class="noprint hr-yellow-bar" />'.PHP_EOL;
			}
			$summer = null;
			foreach ($courses as $semester_no => $these_courses){
				if($semester_no == 3){
					$summer = ' summer';
				}
			}
			

			$table = '
<div class="table-responsive-wrap">
	<table class="table--zebra-stripe">';

	if (! empty($summer)){
			$table .= '	<col class="course_info summer"><col class="course_hours summer"><col class="course_info summer"><col class="course_hours summer"><col class="course_info summer"><col class="course_hours summer">';
	}else{
			$table .= '	<col class="course_info"><col class="course_hours"><col class="course_info"><col class="course_hours">';
	}
	$table .= '
	<caption>'.$course_year_text.'</caption>
	<thead>
		<tr>'.PHP_EOL;
			$thead = array(1=>NULL, 2=>NULL, 3=>NULL);
			$tbody = array();
			$tfooter = array(1=>NULL, 2=>NULL, 3=>NULL);
			$year_hours = 0;
			$new_course_row = array();
			if (!empty($courses) and is_array($courses)){
			foreach ($courses as $semester_no => $these_courses){
				$semester_text = '';
				if ($semester_no == 1){
					$semester_text = 'Fall';
				}elseif($semester_no == 2){
					$semester_text = 'Spring';
				}elseif($semester_no == 3){
					$semester_text = 'Summer';
				}
				$thead[$semester_no] = '		<th scope="col" class="course_info'.$summer.'">'.$semester_text.' Semester</th>
		<th scope="col" class="course_hours'.$summer.'"><span class="semester">'.$semester_text.' </span>Hours</th>'.PHP_EOL;
				$table_row = 0;
				$next_order = 0;
				if (!empty($these_courses) and is_array($these_courses)){
					foreach ($these_courses as $course){
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
						
						$course['course_info'] = preg_replace('/\A(?s:(.*?)(<sup.*?)?)\Z/i', '<span class="content">$1</span>$2', $course['course_info']);
						
						if (! empty($course['extra'])){
							$course['course_info'] .= '<span class="course-extra">'.htmlspecialchars($course['extra']).'</span>';
						}
						
						$tbody[$table_row][$semester_no] = '<td class="course_info'.$summer.'">'.$course['course_info'].'</td><td class="course_hours'.$summer.'">'.$course['hours'].'</td>'.PHP_EOL;
						$table_row++;
						$next_order = $course['order']+1;
			//$main_content .= '<pre>'.json_encode($course, JSON_PRETTY_PRINT).'</pre>';
					}
				}
				if (empty ($degree_map['hours'][$course_year][$semester_no]['hours'])){
					$degree_map['hours'][$course_year][$semester_no]['hours'] = 0;
				}

				if (empty( $degree_map['hours'][$course_year]['total_hours'] )){
					$year_hours = $year_hours + $degree_map['hours'][$course_year][$semester_no]['hours'];
				}else{
					$year_hours = $degree_map['hours'][$course_year]['total_hours'];
				}
				$tfooter[$semester_no] .= '				<th scope="row" class="course_info'.$summer.'">'.$semester_text.' Total Hours</th>
				<td class="course_hours'.$summer.'">'.
				$degree_map['hours'][$course_year][$semester_no]['hours']
				.'</td>'.PHP_EOL;
			}
			}
			if (! empty($thead[1])){
				$table .= $thead[1] . PHP_EOL;
			}
			if (! empty($thead[2])){
				$table .= $thead[2] . PHP_EOL;
			}
			if (! empty($thead[3])){
				$table .= $thead[3] . PHP_EOL;
			}
			$table .='		</tr>'.PHP_EOL.'	</thead>'.PHP_EOL;
			
			$table .= PHP_EOL.'	<tbody>';
			$blank_cells = '<td class="course_info'.$summer.' blankcell"></td><td class="blankcell"></td>' . PHP_EOL;
			foreach ($tbody as $table_row){
				$table .= '		<tr>'. PHP_EOL;
				if (! empty($table_row[1])){
					$table .= $table_row[1] . PHP_EOL;
				}else{
					$table .= $blank_cells;
				}
				if (! empty($table_row[2])){
					$table .= $table_row[2] . PHP_EOL;
				}else{
					$table .= $blank_cells;
				}
				if (! empty($thead[3])){
					if (! empty($table_row[3])){
						$table .= $table_row[3] . PHP_EOL;
					}else{
						$table .= $blank_cells;
					}
				}
				$table .= '		</tr>'. PHP_EOL;

			}
			$table .= PHP_EOL.'	</tbody>
			<tfoot>
			<tr>'. PHP_EOL;
			if (! empty($tfooter[1])){
				$table .= $tfooter[1] . PHP_EOL;
			}
			if (! empty($tfooter[2])){
				$table .= $tfooter[2] . PHP_EOL;
			}
			if (! empty($tfooter[3])){
				$table .= $tfooter[3] . PHP_EOL;
			}
			$table .= PHP_EOL.'</tr>
			</tfoot>'.PHP_EOL.'</table>'.PHP_EOL.'</div>';
			
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
		}

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
		<li><span class="sge sge-070">070</span> Institutionally Designated</li>
	</ul>
</div>';
		
	//$main_content .= '<pre>'.json_encode($degree_map, JSON_PRETTY_PRINT).'</pre>';
	}
	return $main_content;
}

function search_degree_maps($text='', $field=''){
	global $mysqli;
	global $debug;
	global $academic_year;
	global $order;
	global $college;
  	$query = null;

	 // $debug .= '<p>Made it to search_degree_maps</p>';
	// $debug .= '<p>Text: '.$text.' field: '.$field.' college: '.$college.'</p>';
	$fields = array('major', 'college', 'degree_type', 'department', 'note');
  	//echo $query;
  	if (! empty($text)){
 		$text = "%{$text}%";
  	}
  	
	if ($order == 'college'){
		$sql_order = ' ORDER BY `college` ASC, `major` ASC, `degree_type` ASC';
	}else{
		$sql_order = ' ORDER BY `major` ASC, `degree_type` ASC';
	}

	//searching all fields for text; college order and academic year come from globals 
	if ($field == 'all' AND $text != ''){
  		$query = 'SELECT * FROM `degree_maps` WHERE `major` LIKE ? OR `college` LIKE ? OR `degree_type` LIKE ? OR `department` LIKE ? OR `note` LIKE ? AND `academic_year` = ?'.$sql_order;
		$stmt = $mysqli->prepare($query);
		$stmt->bind_param("sssssi", $text, $text, $text, $text, $text, $text, $academic_year);
     	 // $debug .= '<p>' .  prepare_query_debug($query, 'sssssi', array($text, $text, $text, $text, $text, $text, $academic_year)) . '</p>'.PHP_EOL;


	//searching major for text; college is restricted to one (global) college; and academic year comes from global
  	}elseif(($field == 'college' and (! empty($college) and $college == 'all') and (! empty($text)) ) OR (empty($field) && !empty($text))){
  		
  		$query = 'SELECT * FROM `degree_maps` WHERE `major` LIKE ? AND `academic_year` = ? '.$sql_order;
		$stmt = $mysqli->prepare($query);
		$stmt->bind_param("si", $text, $academic_year);
		// */
     	 // $debug .= '<p>' .  prepare_query_debug($query, 'si', array($text, $academic_year)) . '</p>'.PHP_EOL;

	//searching major for text; college is restricted to one (global) college; and academic year comes from global
   	}elseif($field == 'both' and (! empty($college)) and (! empty($text)) ){
  		$query = 'SELECT * FROM `degree_maps` WHERE `college` = ? AND `major` LIKE ?  AND `academic_year` = ? '.$sql_order;
		$stmt = $mysqli->prepare($query);
		$stmt->bind_param("ssi", $college, $text, $academic_year);
     	 // $debug .= '<p>' .  prepare_query_debug($query, 'ssi', array($college, $text, $academic_year)) . '</p>'.PHP_EOL;

	//searching major for text; college is restricted to one (global) college; and academic year comes from global
   	}elseif($field == 'college' and (! empty($college)) and (empty($text)) ){
  		$query = 'SELECT * FROM `degree_maps` WHERE `college` = ? AND `academic_year` = ? '.$sql_order;
		$stmt = $mysqli->prepare($query);
		$stmt->bind_param("si", $college, $academic_year);
     	 // $debug .= '<p>' .  prepare_query_debug($query, 'si', array($college, $academic_year)) . '</p>'.PHP_EOL;
  	}else{
  		if (! in_array($field, $fields)){
  			return false;
  		}else{
  			$query = 'SELECT * FROM `degree_maps` WHERE `'.$field.'` LIKE ? AND `academic_year` = ? '.$sql_order;
			$stmt = $mysqli->prepare($query);
			$stmt->bind_param("si", $text, $academic_year);
     		 // $debug .= '<p>' .  prepare_query_debug($query, 'si', array($text, $academic_year)) . '</p>'.PHP_EOL;
  		}
  	}
	if (! empty($stmt)){ //if debugging and need to not build stmt
		$stmt->execute();
		$result = $stmt->get_result();
		$rows = $result->fetch_all(MYSQLI_ASSOC);
		$stmt->close();
	}

    if (empty($rows)) {
		return NULL;
    } else {
    	return $rows;
    }
}

function display_search_list($maps, $order){
	global $mysqli;
	global $academic_year;
	global $debug;
	 // $debug .= PHP_EOL.'display_search_list()'.PHP_EOL;
	 // $debug .= PHP_EOL.'maps count: '.count($maps).PHP_EOL;
	 // $debug .= PHP_EOL.'display_search_list ids: '.count($maps).PHP_EOL;
	if ($order == 'college'){
		$results = maps_by_college($maps);
	}else{
		$results = maps_by_alpha($maps);
	}
	return $results;
}


$vars = array('degree_map_id', 'search', 'order', 'alpha', 'cat', 'dept', 'college', 'online_learning', 'online_only', 'graduate', 'academic_year', 'selected_year');
foreach ($vars as $var){
	if (! empty($_REQUEST[$var])){ //if it is search, we need to sanatize the text
		if ($var == 'search'){
			$$var = htmlspecialchars(strip_tags(trim(urldecode($_REQUEST[$var]))));
		}elseif ($var == 'order'){
			if ($_REQUEST[$var] == 'college'){
				$order = 'college';
			}else{
				$order = 'alpha';
			}
		}else{
			$$var = (int)$_REQUEST[$var]; //set the variable to the intiger value of the GET
		}
	}else{
		$$var = NULL;
	}
}
if (! empty($selected_year)){
	$academic_year = (int)$selected_year;
}
if (empty($academic_year)){
	$academic_year = get_year();
}

function get_map_title($degree_map_id){
	global $mysqli;
	$stmt = $mysqli->prepare("SELECT `major`, `degree_type`, `academic_year` FROM `degree_maps` WHERE `id` = ? LIMIT 1;");
    $stmt->bind_param("i", $degree_map_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    $degree_map = null;
    $degree_map = $result->fetch_assoc();
    if (empty($degree_map)){
    	return 'Degree Map';
    }
    $title = $degree_map['degree_type']. ' in ' . $degree_map['major'] . ' — ' . $degree_map['academic_year'];
	return $title;
}

