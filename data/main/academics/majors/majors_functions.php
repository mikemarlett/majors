<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/../config/functions.php');

function get_colleges(){
	global $mysqli;
    $query = "SELECT DISTINCT(`college`) FROM `majors_academic_programs` WHERE 1 ORDER BY `college` ASC;";
	$result = $mysqli->query($query);
    $return = array();
    while ($row = $result->fetch_assoc()){
    	if (! empty($row['college'])){
    		$url = urlencode($row['college']);
    		$return[$url] = $row['college'].PHP_EOL;
    	}
    }
	return $return;
}

//get most recent accademic year
function get_year(){
	global $mysqli;
	$query = "SELECT DISTINCT(`academic_year`) FROM `degree_maps` WHERE 1 ORDER BY `academic_year` DESC LIMIT 1;";
	$result = $mysqli->query($query);
	$row = $result->fetch_assoc();
	return $row['academic_year'];
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

function prepare_query_debug($query, $types, $params) {
    foreach ($params as $index => $param) {
        $query = preg_replace('/\?/', "'".$param."'", $query, 1);
    }
    return $query;
}

function get_colleges_array(){
	global $mysqli;
	$stmt = $mysqli->prepare("SELECT `name` FROM `majors_colleges` ORDER BY `name` ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    while ($row = $result->fetch_assoc()){
    	if (! empty($row['name'])){
    		$return[] = $row['name'];
    	}
    }
    return $return;
}

function get_departments_array($college = null){
	global $mysqli;
	/*
	$in_college = null;
	if (! empty($college)){
		$college_id = get_college_id($college);
		if ($college_id !== null){
			$in_college = 'WHERE `college_id` = '.$college_id;
		}
	}
	*/

	// $stmt = $mysqli->prepare("SELECT `department` FROM `majors_departments` ".$in_college." ORDER BY `department` ASC");
	$stmt = $mysqli->prepare("SELECT DISTINCT `department`, `college` 
FROM `majors_academic_programs` 
WHERE 1 ORDER BY `college` ASC, `department` ASC"); 
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    while ($row = $result->fetch_assoc()){
    	if (! empty($row['department'])){
    		$return[] = $row['department'];
    	}
    }
    return $return;
}

function get_college_id($college){
	global $mysqli;
	$stmt = $mysqli->prepare("SELECT `id` FROM `majors_colleges` WHERE `name` = ? LIMIT 1");
 	$stmt->bind_param('s', $college);
 	$stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    $id = null;
    while ($row = $result->fetch_assoc()){
    	if (! empty($row['id'])){
    		$id = $row['id'];
    	}
    }
    return $id;
}


// static retrieval of all majors with a copy of everything that has a sort title — should only be called on first load of main index
function get_majors(){
	global $mysqli;
	global $debug;

  	$rows = null;
  	$query = "SELECT sorted_programs.program AS program, m.* FROM majors_academic_programs m JOIN 
  	( SELECT academic_program AS program, id FROM majors_academic_programs
    UNION ALL SELECT sort_title AS program, id FROM majors_academic_programs WHERE sort_title IS NOT NULL AND sort_title != '' )
    sorted_programs ON m.id = sorted_programs.id ORDER BY sorted_programs.program;";
	$stmt = $mysqli->prepare($query);

	$stmt->execute();
	$result = $stmt->get_result();
	$rows = $result->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	//replace "academic_program" and remove "program" so that the results are the same as search_majors results
	foreach ($rows as $key => $row){
		$row['academic_program'] = $row['program'];
		unset($row['program']);
		$rows[$key] = $row;
	}

	//$debug .= '<p>We are at the end of search_majors.</p>';
    
    return $rows;
}
function get_search_headline(){
	global $filters;
	global $search;
 	$headline = '';
 	if (! empty ($filters['undergrad'])){
		$headline .= 'Undergradate Degrees ';
	}elseif (! empty ($filters['graduate'])){
		$headline .= 'Graduate Degrees ';
	}elseif (! empty ($filters['online'])){
		$headline .= 'Online Degrees ';
	}elseif (! empty ($filters['certificates'])){
		$headline .= 'Certificates ';
	}elseif (! empty ($filters['badges'])){
		$headline .= 'Badges ';
	}else{
		$headline .= 'All Degrees ';
	}
	if (! empty ($filters['college'])){
		$headline .= 'in '.urldecode($filters['college']);
	}
	if (! empty ($filters['department'])){
		$headline .= ' from '.urldecode($filters['department']);
	}
	
	if (! empty ($search)){
		$headline = '"'.htmlspecialchars($search).'" in '.$headline;
	}
	return $headline;
}

function get_search_header(){
	global $filters;
	global $search;
 	$headline = get_search_headline();
  	$query = '?';
  	foreach ($filters as $filter_name => $filter){
		if (! empty($filter) and $filter_name != 'action'){
			if (($query != '?')){
				$query .= '&amp;';
			}
			$query .= $filter_name.'='.urlencode($filter);
		}
  	}
	
	if (! empty ($search)){
		if (($query != '?')){
			$query .= '&amp;';
		}
		$query .= 'search='.urlencode(htmlspecialchars($search));
	}
	
	if (($query == '?order=alpha') OR ($query == '?')){
		$second_button = ''; //we aren't really searching or filtering for anything
	}else{
		$second_button = '<a href="/academics/majors/index.php'.$query.'" role="button" class="button ">Link to These Results<svg role="img" class="icon button__trailing-icon" aria-hidden="true"><use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--arrow-right" /></svg></a>';
	}

  	
  	$header = '
<header class="section-header collection__header">
	<h2>'.$headline.'</h2>
	<div class="button-collection ">
		<a href="/academics/majors/index.php" role="button" class="button ">All Degree Programs<svg role="img" class="icon button__trailing-icon" aria-hidden="true"><use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--arrow-right" /></svg></a> '.$second_button.'
	</div>
</header>';
	
	return $header;
}

function search_majors($text=''){
	global $mysqli;
	global $debug;
	global $search;
  	$query = null;
  	$rows = null;

	if (empty($text) and !empty($search)){
		$text = $search;
	}

	global $filters;
	/*
	'ids' => $ids,
	'order' => $order,
	'college' => $college, //already checked against official list
	'department' => $dept, //unused in general
	'undergrad' => ($filter == 'undergrad') ? 1 : null,
	'graduate' => ($filter == 'grad') ? 1 : null,
	'online' => ($filter == 'online') ? 1 : null,
	'minors' => ($filter == 'minors') ? 1 : null,
	'certificates' => ($filter == 'certificates') ? 1 : null,
	'badges' => ($filter == 'badges') ? 1 : null,
	'action' => $action,
	*/
	// $debug .= '<p>Made it to search_degree_maps</p>';
	// $debug .= '<p>Text: '.$text.' field: '.$field.' college: '.$college.'</p>';
	// $fields = array('academic_program', 'college', 'program_type', 'department', 'note');
  	//echo $query;
 
  	
	$bind_param = '';
	$bind_param_array = array();
  	if (! empty($text)){
 		$text = "%{$text}%";
		$query = 'SELECT m1.* FROM `majors_academic_programs` m1 
			LEFT JOIN `majors_programs_content` m2  ON m1.id = m2.academic_program_id
		WHERE (m1.`academic_program` LIKE ? OR m1.`department` LIKE ? OR m1.`note` LIKE ? OR m2.`meta_keywords` LIKE ? ) ';
		$bind_param = 'ssss';
		$bind_param_array = array( $text, $text, $text, $text );
 	}else{
 		$query = 'SELECT m1.* FROM `majors_academic_programs` m1 WHERE 1 ';
 	}
  	
	if ($filters['order'] == 'college'){
		$sql_order = ' ORDER BY  m1.`college` ASC, m1.`academic_program` ASC, m1.`program_type` ASC';
	}else{
		$sql_order = ' ORDER BY m1.`academic_program` ASC, m1.`program_type` ASC';
	}

	if (! empty($filters['college']) and $filters['college'] != 'all'){
		$query .= ' AND ( m1.`college` = ? ) ';
		$bind_param .= 's';
		$bind_param_array[] = $filters['college'];
	}
	if (! empty($filters['department'])){
		$query .= ' AND ( m1.`department` = ? ) ';
		$bind_param .= 's';
		$bind_param_array[] = $filters['department'];
	}
	if (! empty($filters['undergrad'])){
		$query .= ' AND ( m1.`graduate` != 1 OR m1.`graduate` IS NULL ) ';
	}
	if (! empty($filters['graduate'])){
		$query .= ' AND ( m1.`graduate` = 1 ) ';
	}
	if (! empty($filters['online'])){
		$query .= ' AND ( m1.`online_learning` = 1 ) ';
	}
	if (! empty($filters['minors'])){
		$query .= ' AND ( m1.`minor` = 1 ) ';
	}
	if (! empty($filters['certificates'])){
		$query .= ' AND ( m1.`certificate` = 1 ) ';
	}
	if (! empty($filters['badges'])){
		$query .= ' AND ( m1.`badge` = 1 ) ';
	}
	
	$query .= $sql_order;
	
	//$debug .= '<p>' . prepare_query_debug($query, $bind_param, $bind_param_array) . '</p>'.PHP_EOL;;
	
	$bind_param_count = count($bind_param_array);
 
	$stmt = $mysqli->prepare($query);
	
	if ($bind_param_count == 1){
		$stmt->bind_param($bind_param, $bind_param_array[0]);
	}elseif ($bind_param_count == 2){
		$stmt->bind_param($bind_param, $bind_param_array[0], $bind_param_array[1]);
	}elseif ($bind_param_count == 3){
		$stmt->bind_param($bind_param, $bind_param_array[0], $bind_param_array[1], $bind_param_array[2]);
	}elseif ($bind_param_count == 4){
		$stmt->bind_param($bind_param, $bind_param_array[0], $bind_param_array[1], $bind_param_array[2], $bind_param_array[3]);
	}elseif ($bind_param_count == 5){
		$stmt->bind_param($bind_param, $bind_param_array[0], $bind_param_array[1], $bind_param_array[2], $bind_param_array[3], $bind_param_array[4]);
	}elseif ($bind_param_count == 6){
		$stmt->bind_param($bind_param, $bind_param_array[0], $bind_param_array[1], $bind_param_array[2], $bind_param_array[3], $bind_param_array[4], $bind_param_array[5]);
	}elseif ($bind_param_count == 7){
		$stmt->bind_param($bind_param, $bind_param_array[0], $bind_param_array[1], $bind_param_array[2], $bind_param_array[3], $bind_param_array[4], $bind_param_array[5], $bind_param_array[6]);
	}

	$stmt->execute();
	$result = $stmt->get_result();
	$rows = $result->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	//$debug .= '<p>We are at the end of search_majors.</p>';
    
    return $rows;
}


function majors_by_alpha($majors = array()){
	global $debug;
	//$debug .= '<p><strong>majors_by_alpha()</strong></p>';
	if (empty ($majors) OR ! is_array($majors)){
		$majors = get_majors();
	}
	$header = get_search_header();
	$header .= '
	<div class="alpha-filters ">
		<div class="alpha-filters__list">';

	$html = '<div class="alpha-list">';
	$alpha = NULL;
	foreach ($majors as $major){
		$letter = strtoupper(substr($major['academic_program'], 0, 1));
		if ($alpha != $letter){
			$header .= PHP_EOL.'			<a href="#'.$letter.'" role="button" class="button ">'.$letter.'</a>';
			if (! empty ($alpha)){
				$html .= '	</ul>'.PHP_EOL; //close previous alpha if needed
			}
			$html .= '	<div class="alpha-list__items"><a id="'.$letter.'" name="'.$letter.'"></a>'.PHP_EOL;
			$html .= '		<header class="section-header"><h3 class="heading4">'.$letter.'</h3></header>'.PHP_EOL;
			$html .= '	</div>'.PHP_EOL;
			$html .= '	<ul>'.PHP_EOL;
			$alpha = $letter;
		}
		
		$html .= '		<li><a href="index.php?id='.$major['id'].'">'.$major['academic_program'].'</a> — '.$major['program_type'].'</li>'.PHP_EOL;
 	}
	$html .= '	</ul>'.PHP_EOL; //close final alpha list
	$html .= '</div>'; // class="alpha-list" (end)
	$header .= 		'
			<hr />
		</div>
	</div>
';
	$html = $header.$html;

	return $html;
}

function majors_by_college($majors = array()){
	global $debug;
	if (empty ($majors) OR ! is_array($majors)){
		$majors = get_majors();
	}
	$header = get_search_header();
	$header .= '
		<div class="button-collection">';

	$html = '<div class="alpha-list">'.PHP_EOL;
	$college = NULL;
	foreach ($majors as $major){
		if ($college != $major['college']){
			$header .= PHP_EOL.'			<a href="#'.urlencode(strtolower($major['college'])).'" role="button" class="button ">'.$major['college'].'<svg class="icon button__trailing-icon" title="'.$major['college'].'"><use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--arrow-right"></use></svg></a>';
			if (! empty($college)){
				$html .= '	</ul>'.PHP_EOL;
			}
			$html .= '	<div class="alpha-list__items"><a id="'.urlencode(strtolower($major['college'])).'" name="'.urlencode(strtolower($major['college'])).'"></a>'.PHP_EOL;
			$html .= '		<header><h3 class="section-header">'.$major['college'].'</h3></header>'.PHP_EOL;
			$html .= '	</div>'.PHP_EOL;
			$html .= '	<ul>'.PHP_EOL;
			$college = $major['college'];
		}
		$html .= '		<li><a href="'.$_SERVER['PHP_SELF'].'?id='.$major['id'].'">'.$major['academic_program'].'</a> — '.$major['program_type'].'</li>'.PHP_EOL;
	}
	$html .= '	</ul>'.PHP_EOL;
	$html .= '</div>'.PHP_EOL; // class="alpha-list" (end)
	$header .= 		'
			<hr />
		</div>
';
	$html = $header.$html;
	return $html;
}

function get_degree_maps($id, $academic_year=NULL){
	global $mysqli;
	$degree_maps = null;
	$stmt = $mysqli->prepare("SELECT `id`, `major`, `degree_type`, `academic_year` FROM `degree_maps` WHERE `program_id` = ? ORDER BY `major` ASC, `degree_type` ASC, `academic_year` DESC");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    while ($row = $result->fetch_assoc()){
    	$degree_maps[] = $row;
    }


	return $degree_maps;
}

function get_major_by_id($id){
	global $mysqli;
	$stmt = $mysqli->prepare("SELECT * FROM `majors_academic_programs` WHERE `id` = ? LIMIT 1;");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $major = null;
    $major = $result->fetch_assoc();
	$major['content'] = get_major_content($id);
	$major['similar_programs'] = get_majors_similar_programs($id);
	// var_dump($major);
    
	return $major;
}

function get_majors_suggestions($name){
	global $mysqli;
	$stmt = $mysqli->prepare("SELECT `academic_program` FROM `majors_academic_programs` WHERE `academic_program` LIKE %?% ORDER BY `academic_program` ASC;");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $majors = null;
    while ($row = $result->fetch_assoc()){
    	$majors[] = $row['academic_program'];
    }
    
	return $majors;
}

function get_major_content($id){
	global $mysqli;
	$stmt = $mysqli->prepare("SELECT * FROM `majors_programs_content` WHERE `academic_program_id` = ? LIMIT 1;");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $content = null;
    $content = $result->fetch_assoc();

	return $content;
}

function get_title($major){
	$title = null;
	if (! empty($major['academic_program']) and ! empty($major['program_simple_type']) ){
		$title = $major['academic_program'].', '.$major['program_simple_type'];
	}
	return $title;
}

function get_majors_similar_programs($id){
	global $mysqli;
	$stmt = $mysqli->prepare("SELECT `similar_academic_program_id` FROM `majors_similar_programs` WHERE `main_academic_program_id` = ? LIMIT 6;");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $similar_programs = array();
    while ($row = $result->fetch_assoc()){
    	$similar_programs[] = $row['similar_academic_program_id'];
    }
	// var_dump($similar_programs);
    if (count($similar_programs) < 6){
    	$limit = 6 - count($similar_programs);
    	$notin = null;
    	foreach ($similar_programs as $similar_program){
    		$notin .= " AND (`main_academic_program_id` != '".$similar_program."') ";
    	}
    	//NOT IN isn't supported in older MySQL (as is on local environments)
    	// INSTEAD, look for similar_academic_program_id(s) that do not have the main_academic_program_id that is equal to one we already have;
    	
		$stmt = $mysqli->prepare("SELECT `main_academic_program_id` FROM `majors_similar_programs` WHERE `similar_academic_program_id` = ? ".$notin." LIMIT ".$limit." ;");
		$stmt->bind_param("i", $id );
		$stmt->execute();
		$result = $stmt->get_result();
		$stmt->close();
		while ($row = $result->fetch_assoc()){
			$similar_programs[] = $row['main_academic_program_id']; 
		}
	}
	
	if (! empty($similar_programs) and is_array($similar_programs)){
		$temp_similar_programs = null;
		foreach ($similar_programs as $similar_program){
			$stmt = $mysqli->prepare("
SELECT `t1`.`id`, `t1`.`academic_program`, `t1`.`program_type`, `t1`.`program_simple_type`, `t2`.`main_image_url`
	FROM `majors_academic_programs` t1
	LEFT JOIN `majors_programs_content` t2 ON `t1`.`id` = `t2`.`academic_program_id`
WHERE `t1`.`id` = ?;
");
			$stmt->bind_param("i", $similar_program);
			$stmt->execute();
			$result = $stmt->get_result();
			$stmt->close();
			while ($row = $result->fetch_assoc()){
				$temp_similar_programs[] = $row;
			}
		}
		if (! empty($temp_similar_programs)){
			$similar_programs = $temp_similar_programs;
		}
	}
	return $similar_programs; 
}

function display_major($major_id){
	$main_content = NULL;
	if (! is_array($major_id) and is_numeric($major_id)){
		$major = get_major_by_id($major_id);
	}elseif(! empty($major_id['id'])){
		$major = $major_id;
		$major_id = $major['id'];
	}
	
	if (empty ($major['content'])){
		$major['content'] = get_major_content($major_id);
	}
	if (empty ($major['similar_programs'])){
		$major['similar_programs'] = get_majors_similar_programs($major_id);
	}
	if (! empty($major) ){
	//if this is a certificate, we need it like
	// [main]
	// [inside the program]
	// [[curriculum] [admission]]
	
	// otherwise we neeed
	// [main]
	// [[wildcard] [admission]]
	// [inside the program]
	// [[curriculum] [carreers]]
	
		
		$main_content .= PHP_EOL.'
<header class="page-header">
	<div class="page-header__bar">
		<div class="page-header__page-title">
			<h1 class="headline-group"><span class="head">Details: '.get_title($major).'</span></h1>
		</div>
		<div class="section-nav">
			<div class="section-nav__toggle">
				<div class="section-nav__toggle-wrapper">
					<button class="toggleSectionNav primary-toggle">Section Menu<svg class="icon" title="Open Section Links"><use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--menu"></use></svg></button>
				</div>
			</div>
			<nav>
				<ul>';
					$main_content .= get_section_nav($major);
					$main_content .= '
				</ul>
			</nav>
		</div>
	</div>
</header>
<section class="section-wrap section-wrap--shade-light &nbsp;">
	<div class="program-card">
		<div class="program-card__body">
			<h2 class="headline-group">
				<span class="superhead">'.$major['program_simple_type'].'</span>
				<span class="head">'.$major['academic_program'].'</span>
			</h2>';
			if (! empty($major['content']['program_links'])){
				$program_links = json_decode($major['content']['program_links'], true);
			//[{"link_text":"All Programs","href":"\/academics\/majors\/index.php"},{"link_text":"College of Engineering","href":"\/academics\/engineering\/index.php"},{"link_text":"Aerospace Engineering","href":"\/academics\/engineering\/aerospace\/"}]
				
			$main_content .= '
			<div class="link-collection">
				<ul>';
				foreach ($program_links as $program_link){
					$main_content .= '
					<li><a href="'.$program_link['href'].'">'.$program_link['link_text'].'</a></li>';
				}
			$main_content .= '
				</ul>
			</div>';
			}
			$main_content .= '
			<div class="program-card__detail">
				'.$major['content']['description'].'
			</div>';
		////LEARN HOW///
			if (! empty($major['content']['learn_how'])){
			$main_content .= '
			<div class="program-card__cta">
				<h3 class="heading4">'.$major['content']['learn_how'].'</h3>
				<div class="button-collection landing-panel__buttons button-collection--accent-first">';
				if (! empty($major['content']['learn_how_links'])){
					$learn_how_links = json_decode($major['content']['learn_how_links'], true);
					foreach ($learn_how_links as $learn_how_link){
						$main_content .= '
						<a href="'.$learn_how_link['href'].'" role="button" class="button">'.$learn_how_link['link_text'].'<svg class="icon button__trailing-icon" title="'.$learn_how_link['link_text'].'"><use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--arrow-right"></use></svg></a> ';
					}
					
				}
			$main_content .= '
				</div>
			</div>';
			}
			$main_content .= '
		</div>';
		////MAIN IMAGE///
		if (! empty($major['content']['main_image_url'])){
			$main_content .= '
		<div class="program-card__image">
			<div class="captioned-media captioned-media--right">
				<figure>
					<div class="figure-wrapper">
						<img src="'.$major['content']['main_image_url'].'" alt="'.$major['content']['main_image_alt'].'">
						';
			if (! empty($major['content']['main_image_credit'])){
			$main_content .= '
			<cite class=" cite--photo-credit"><svg role="img" class="icon" aria-hidden="true"><use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg.svg#design--camera"></use></svg> '.$major['content']['main_image_credit'].'</cite>';
			
			$main_content .= '
					</div>
					<figcaption><p>'.$major['content']['main_image_caption'].'</p></figcaption>
				</figure>
			</div>
		</div>';
		}
			$main_content .= '
	</div>
</section>';
		}
		
		
	if (! str_contains($major['program_type'], 'Certificate')){
		////Wildcard (Applied Learning) and Admission ///

		if (! empty ($major['content']['wildcard_headline']) OR ! empty ($major['content']['admissions_headline'])){
	
$main_content .= '
<section class="teaser-collection section-wrap collection--two-columns">
	<div class="collection__items">
		<div class="teaser collection__item ">
			<div class="teaser__body">
				<div class="teaser__headline">
					<h3 class="headline-group ">
						<span class="head">'.$major['content']['wildcard_headline'].'</span>
					</h3>
				</div>
				<div class="teaser__editorial">'.$major['content']['wildcard_text'].'</div>
				<div class="teaser__links">
					<a class=" link--rich" href="'.$major['content']['wildcard_link_url'].'"> 
					<span>'.$major['content']['wildcard_link_text'].'</span>
					</a>
				</div>
			</div>
		</div>
		<div class="teaser collection__item ">
			<div class="teaser__body">
				<div class="teaser__headline">
					<h3 class="headline-group ">
						<span class="head">'.$major['content']['admissions_headline'].'</span>
					</h3>
				</div>
				<div class="teaser__editorial">'.$major['content']['admissions_text'].'</div>
				<div class="teaser__links">
					<a class=" link--rich" href="'.$major['content']['admissions_link_url'].'"> 
					<span>'.$major['content']['admissions_link_text'].'</span>
					</a>
				</div>
			</div>
		</div>
	</div>
</section>';
		}


	}else{
	// Curriculum & admissions 
		if (! empty ($major['content']['curriculum_text']) OR ! empty ($major['content']['admissions_headline'])){
	
$main_content .= '
<section class="teaser-collection section-wrap collection--two-columns">
	<div class="collection__items">
		<div class="teaser collection__item ">
			<div class="teaser__body">
				<div class="teaser__headline">
					<h3 class="headline-group ">
						<span class="head">Curriculum</span>
					</h3>
				</div>
				<div class="teaser__editorial">'.$major['content']['curriculum_text'].'</div>
				<div class="teaser__links">
					<a class=" link--rich" href="'.$major['content']['curriculum_link_url'].'"><span>'.$major['content']['curriculum_link_text'].'</span></a>
				</div>';
				$degree_maps = get_degree_maps($major_id);
				if (! empty($degree_maps)){
					$headline = 'Degree Map';
					if (count($degree_maps) > 1){
						$headline = 'Degree Maps';
					}
		$main_content .= '
				<div class="teaser__headline">
					<h3 class="headline-group ">
						<span class="head">'.$headline.'</span>
					</h3>
				</div>
				<div class="teaser__links">';
				foreach ($degree_maps as $degree_map){
					$display_year = ($degree_map['academic_year']-2000);
					$start_year = ($display_year-1);
					$main_content .= '
					<a class=" link--rich" href="/academics/majors/degree_maps/maps.php?map_id='.$degree_map['id'].'" style="margin-bottom: 0;"><span>'.$degree_map['degree_type'].' in '.$degree_map['major'].' (’'.$start_year.'-’'.$display_year.')</span></a>';
				}
		$main_content .= '
				</div>';
				}
$main_content .= '
			</div>
		</div>
		<div class="teaser collection__item ">
			<div class="teaser__body">
				<div class="teaser__headline">
					<h3 class="headline-group ">
						<span class="head">'.$major['content']['admissions_headline'].'</span>
					</h3>
				</div>
				<div class="teaser__editorial">'.$major['content']['admissions_text'].'</div>
				<div class="teaser__links">
					<a class=" link--rich" href="'.$major['content']['admissions_link_url'].'"> 
					<span>'.$major['content']['admissions_link_text'].'</span>
					</a>
				</div>
			</div>
		</div>
	</div>
</section>';
		}
	
	
	}
		

//INSIDE THE PROGRAM
	if (! empty ($major['content']['inside_the_program_headline'])){

$main_content .= '
<section class="section-wrap section-wrap--wheat">
	<header class="section-header section-header--no-border">
		<h2>Inside the Program</h2>
	</header>
	<div class="teaser teaser--columned-intro">
		<div class="teaser__image">
			<a><img src="'.$major['content']['inside_the_program_image_url'].'" alt="'.$major['content']['inside_the_program_image_alt'].'"></a>
		</div>
		<div class="teaser__body">
			<div class="teaser__headline">
				<h3 class="headline-group ">
					<span class="head">'.$major['content']['inside_the_program_headline'].'</span>
				</h3>
			</div>
			<div class="teaser__editorial">
				'.$major['content']['inside_the_program_text'].'
			</div>';
		if (! empty ($major['content']['inside_the_program_link_url'])){
			$main_content .= '
			<div class="teaser__links"><a class=" link--rich" href="'.$major['content']['inside_the_program_link_url'].'"><span>'.$major['content']['inside_the_program_link_text'].'</span></a></div>';
		}
$main_content .= '
		</div>
	</div>
</section>';
	}


	if (! str_contains($major['program_type'], 'Certificate')){
		////Wildcard (Applied Learning) and Admission ///


//CURRICULUM & CAREERS
		if (! empty ($major['content']['curriculum_text']) OR ! empty ($major['content']['careers_headline'])){

$main_content .= '
<section class="teaser-collection section-wrap collection--two-columns section-wrap--nipple-down">
	<div class="collection__items">
		<div class="teaser collection__item ">
			<div class="teaser__body">
				<div class="teaser__headline">
					<h3 class="headline-group ">
						<span class="head">Curriculum</span>
					</h3>
				</div>
				<div class="teaser__editorial">'.$major['content']['curriculum_text'].'</div>
				<div class="teaser__links">
					<a class=" link--rich" href="'.$major['content']['curriculum_link_url'].'"><span>'.$major['content']['curriculum_link_text'].'</span></a>
				</div>';
				$degree_maps = get_degree_maps($major_id);
				if (! empty($degree_maps)){
					$headline = 'Degree Map';
					if (count($degree_maps) > 1){
						$headline = 'Degree Maps';
					}
		$main_content .= '
				<div class="teaser__headline">
					<h3 class="headline-group ">
						<span class="head">'.$headline.'</span>
					</h3>
				</div>
				<div class="teaser__links">';
				foreach ($degree_maps as $degree_map){
					$display_year = ($degree_map['academic_year']-2000);
					$start_year = ($display_year-1);
					$main_content .= '
					<a class=" link--rich" href="/academics/majors/degree_maps/maps.php?map_id='.$degree_map['id'].'" style="margin-bottom: 0;"><span>'.$degree_map['degree_type'].' in '.$degree_map['major'].' (’'.$start_year.'-’'.$display_year.')</span></a>';
				}
		$main_content .= '
				</div>';
				}
$main_content .= '
			</div>
		</div>
		<div class="teaser collection__item ">
			<div class="teaser__body">
				<div class="teaser__headline">
					<h3 class="headline-group ">
						<span class="head">'.$major['content']['careers_headline'].'</span>
					</h3>
				</div>
				<div class="teaser__editorial">'.$major['content']['careers_text'].'</div>
				<div class="teaser__links">
					<a class=" link--rich" href="'.$major['content']['careers_link_url'].'"> 
					<span>'.$major['content']['careers_link_text'].'</span>
					</a>
				</div>
			</div>
		</div>
	</div>
</section>';

	}
	}
	
//Similar Programs
	
	if (! empty($major['similar_programs'])){
	
		if (! empty($major['content']['main_image_url'])){
			$image_url = $major['content']['main_image_url'];
		}else{
			$image_url = '/_resources/images/wichita.jpg';
		}
				
				
	
$main_content .= '
<section class="teaser-collection section-wrap section-wrap--shade-dark section-wrap--image-background-texturize collection--two-columns collection--two-columns-early-break">
	<header class="section-header section-header--centered section-header--no-border collection__header">
		<h2>Similar Programs</h2>
	</header>
	<div class="collection__items">';

	foreach ($major['similar_programs'] as $similar_program){
		
		$main_content .= '
		<a href="/academics/majors/index.php?id='.$similar_program['id'].'" class="teaser collection__item teaser--card-wide teaser--card">
		<div class="teaser__image">
			<img src="'.$similar_program['main_image_url'].'" alt="" width="1000" height="1000">
		</div>
		<div class="teaser__body">
			<div class="teaser__headline">
				<div class="headline-group">
					<span class="head">'.$similar_program['academic_program'].' ('.$similar_program['program_simple_type'].')</span>
				</div>
			</div>
		</div>
		</a>';
	
	}

	
$main_content .= '
	</div>
	<div class="section-wrap__image">
		<td><img src="'.$image_url.'" alt="" width="1000" height="1000"></td>
	</div>
</section>
'.PHP_EOL;	}
	

	}
	return $main_content;
}

function get_section_nav($major = null){
	// global $mysqli;
	//hardcode for now but get fancy later
	$main_content = '
					<li><a href="/academics/majors/index.php">All Programs</a></li>
					<li><a href="/academics/majors/index.php?order=college">Programs By College</a></li>
					<li><a href="/academics/majors/index.php?filter=online">Online Degrees</a></li>
					<li><a href="/academics/majors/index.php?filter=undergrad">Undergraduate Degrees</a></li>
					<li><a href="/academics/majors/index.php?filter=graduate">Graduate Degrees</a></li>
					<li><a href="/academics/majors/index.php?filter=certificates">Certificates</a></li>
					<li><a href="/academics/majors/index.php?filter=badges">Badges</a></li>
					<li><a href="/academics/majors/degree_maps/index.php">Degree Maps Intro</a></li>
					<li><a href="/academics/majors/degree_maps/maps.php">All Degree Maps</a></li>';
	if (!empty($major['college'])){
		$main_content .= '
					<li><a href="/academics/majors/index.php?college='.urlencode($major['college']).'">Degrees from '.$major['college'].'</a></li>';
	}
	if (!empty($major['department'])){
		$main_content .= '
					<li><a href="/academics/majors/index.php?department='.urlencode($major['department']).'">Degrees from '.$major['department'].'</a></li>';
	}
	return $main_content;
}

function get_filters_section(){
	global $filters;
	global $order;
	global $college;
	global $search;
	
	$filter_section = null;	

	$filters_menu = array(
		'all' => 'All Programs',
		'undergrad' => 'Undergraduate Degrees',
		'graduate' => 'Graduate Degrees',
		'online' => 'Online',
		'certificates' => 'Certificates',
		'badges' => 'Badges',
	);
	$orders_menu = array(
		'alpha' => 'Alphabetical',
		'college' => 'By College',
	);
	$colleges_menu = array(
		'all' => 'All Colleges',
	);
	$college_menu = array_merge($colleges_menu, get_colleges());
	
	
	$filter_section .= '
	<section class="section-wrap section-wrap--shade-light section-wrap--short">
		<div class="search-filters">
        	<form class="search-filters__search" id="degreeSearchForm">
				<label class="show-for-sr" for="searchDegrees">Search</label> 
				<input id="searchDegrees" class="" type="text" placeholder="Search Degrees"';

	if (! empty ($search)){ 
		$filter_section .= 'value="'.htmlspecialchars($search).'"';
	}
	$filter_section .= '>
				<datalist id="degreesList">
					<!-- Options will be populated by JavaScript -->
				</datalist>
				<button type="submit" value="Search"><svg role="img" class="icon" aria-labelledby="design--search_63e3f07a1c603">
				<title id="design--search_63e3f07a1c603">Search</title>
				<use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--search" /></svg>
				<span class="show-for-sr">Search</span>
				</button>
			</form>
			<form class="search-filters__select">
				<label class="show-for-sr" for="selectDegreeType">Select Degree Type</label> 
				<select id="selectDegreeType">
					<optgroup label="Degree Program Type">'.PHP_EOL;
	foreach ($filters_menu as $filter_option => $filter_label){
		$selected = null;
		if (! empty($_GET[$filter_option]) AND $_GET[$filter_option] == 1){
			$selected = ' selected';
		}
		$filter_section .= '					<option value="'.$filter_option.'"'.$selected.'>'.$filter_label.'</option>'.PHP_EOL;
	}
	$filter_section .= '					</optgroup>
				</select>
			</form>
		</div>
		<div class="search-filters ">
			<form class="search-filters__select">
				<label class="show-for-sr" for="selectCollege">College</label> 
				<select id="selectCollege">
					<optgroup label="In College">'.PHP_EOL;
	foreach ($college_menu as $college_option => $college_label){
		$selected = null;
		if (! empty($_GET['college']) AND urlencode($_GET['college']) == $college_option){
			$selected = ' selected';
		}
		$filter_section .= '					<option value="'.$college_option.'"'.$selected.'>'.$college_label.'</option>'.PHP_EOL;
	}
	$filter_section .= '					</optgroup>
				</select>
			</form>
			<form class="search-filters__select">
				<label class="show-for-sr" for="selectOrder">Order</label> 
				<select id="selectOrder">
					<optgroup label="View Order">'.PHP_EOL;
	foreach ($orders_menu as $order_option => $order_label){
		$selected = null;
		if ($order == $order_option){
			$selected = ' selected';
		}
		$filter_section .= '<option value="'.$order_option.'"'.$selected.'>'.$order_label.'</option>'.PHP_EOL;
	}
$filter_section .= '					</optgroup>
				</select>
			</form>
		</div>
	</section>
';
	return $filter_section;
}

$vars = array('id', 'ids', 'search', 'order', 'alpha', 'department', 'college', 'online', 'online_only', 'graduate', 'academic_year', 'selected_year', 'action', 'filter');
foreach ($vars as $var){
	if (! empty($_POST[$var])){
		$_GET[$var] = $_POST[$var];
	}
	if (! empty($_GET[$var])){ //if it is search, we need to sanatize the text
		if ($var == 'order'){
			if ($_GET[$var] == 'college'){
				$order = 'college';
			}else{
				$order = 'alpha';
			}
		}else{
			$$var = htmlspecialchars(strip_tags(trim(urldecode($_GET[$var]))));
		}
	}else{
		$$var = NULL;
	}
}
if (empty($action)){
	$action = 'home';
}

if (! empty($_POST['id'])){
	$id = preg_replace('/\D/', '', $_POST['id']);
}
if (! empty($_GET['id']) AND empty($id)){
	$id = preg_replace('/\D/', '', $_GET['id']);
}
if (! empty($_POST['ids'])){
	$ids = preg_replace('/[^0-9,]/', '', $_POST['ids']); //nothing but numbers and commas
}
if (! empty($_GET['ids']) AND empty($ids)){
	$ids = preg_replace('/[^0-9,]/', '', $_GET['ids']); //nothing but numbers and commas
}
global $filters;

if (empty($filter)){
	$filter_opts = array('undergrad', 'graduate', 'online', 'minors', 'certificates', 'badges', 'department');
	foreach($filter_opts as $opt){
		if (! empty($$opt)) $filter = $opt;
	}
}

$colleges = get_colleges_array();
if (! in_array($college, $colleges)){
	$college = NULL;
}

if (! empty($department)){
	$departments = get_departments_array();
	if (! in_array($department, $departments)){
		$department = NULL;
	}
}

$filters = array(
	'ids' => $ids,
	'order' => $order,
	'college' => $college, //already checked against official list
	'department' => $department, //unused in general
	'undergrad' => ($filter == 'undergrad') ? 1 : null,
	'graduate' => ($filter == 'graduate') ? 1 : null,
	'online' => ($filter == 'online') ? 1 : null,
	'minors' => ($filter == 'minors') ? 1 : null,
	'certificates' => ($filter == 'certificates') ? 1 : null,
	'badges' => ($filter == 'badges') ? 1 : null,
	'action' => $action,
);


