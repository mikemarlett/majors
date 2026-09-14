<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/academics/majors/majors_functions.php');

/****** Edit Major ********
// 
//	@id init id of major to edit
//	returns form for major
//
*********************************/

function edit_majors_page($id){
	//sanatize id
	$id = (int)$id;
	$majors_page = get_major_by_id($id);
	$form = null;
	if ($majors_page){
		
		$form = 'Need to build this still.';

	}
	return $form;
}

if (! function_exists('apstyle_date')){
	function apstyle_date($date = '', $format='l, F j', $fix_year = true){
		try {
			$date = new DateTimeImmutable($date);
		} catch (Exception $e) {
			echo $e->getMessage();
			exit(1);
		}
		$now = new DateTimeImmutable();
		
		if ($fix_year){
			$interval = $date->diff($now);
			$days = $interval->format("%a");
			//if it's more than 180 days, we will need to fix the year
			//if the format doesn't have the year, we will hackily add it 
			if ( ($days > 180) AND ! ( strripos($format, ', Y') ) ){
				$format .= ', Y';
			}
			//if it is less than 180 days and the format does have it, we will remove the year
			if ( ($days < 180) AND ( strripos($format, ', Y') ) ){
				$format = str_replace(',? ?Y', '', $format);
			}
		}
		$styled_date = $date->format($format);
		
		//grep replacements to make it all ap style
		$bads = array(
		'/(?:(Aug)ust|(Sept)ember|(Oct)ober|(Nov)ember|(Dec)ember|(Jan)uary|(Feb)ruary) (\d\d?)/',
		'/((?:^| )\d+(?::\d\d)?) (a|p)m/i',
		'/  +/');
		//the \8 is preceeded by a nonbreaking space
		$goods = array(
		'$1$2$3$4$5$6$7. $8',
		'$1 $2.m.',
		' ');
		$styled_date = preg_replace($bads, $goods, $styled_date);
		return $styled_date;
	}

}

if (! function_exists('apstyle_time')){
/**
	 * Output AP Style formated time from a string
	 * @version 1.0.0
	 * @param string $time
	 * @return string
**/

	function apstyle_time($the_time)
	{
		$paterns[0]='/12:00 am/i';
		$paterns[1]='/12:00 pm/i';
		$paterns[2]='/11:55 pm/i';
		$paterns[3]='/am/i';
		$paterns[4]='/pm/i';
		$paterns[5]='/(\d\d?):00/i';
		$replacements[0] = "Midnight";
		$replacements[1] = "Noon";
		$replacements[2] = "Midnight";
		$replacements[3] = "a.m.";
		$replacements[4] = "p.m.";
		$replacements[5] = '$1';
		return preg_replace($paterns,$replacements, $the_time);
	}
}

function get_all_colleges_options($selected = ''){
    $colleges = get_all_colleges_array();
    $return = '	<option disabled>Choose one</option>';
    if (! empty($colleges)){
    	foreach ($colleges as $college){
			$this_selected = false;
			if ($college['name'] == $selected){
				$this_selected = ' selected';
			}
    		$return .= '	<option value="'.$college['id'].'"'.$this_selected.'>'.$college['name'].'</option>'.PHP_EOL;
    	}
    }
	return $return;
}

function get_all_colleges_array(){
	global $mysqli;
    $query = "SELECT * FROM `majors_colleges` WHERE 1 ;"; //ORDER BY `name` ASC
	$result = $mysqli->query($query);
    while ($row = $result->fetch_assoc()){
   		$return[] = $row;
    }
    return $return;
}


function get_admin_table(){
	global $filters;
	$majors = get_majors($filters);
	$rows = '';
	$table = '
<div id="programs_table" class="table-responsive-wrap">
<table class="table--zebra-stripe" id="program_pages_info">
	<caption>Academic Programs</caption>
	<thead>
		<tr>
			<th>Academic Program</th>
			<th>College</th>
			<th>Department</th>
			<th>Degree</th>
			<th>Graduate</th>
			<th>Online</th>
			<th>Note</th>
			<th>Last Update</th>
			<th>Similar</th>
		</tr>
	</thead>
	<tbody>';
		 // var_dump($majors);
	foreach ($majors as $this_major){
		// '.var_export($major, true).'
		// var_dump($this_major);
		$major = get_major_by_id($this_major['id']);
		$table .= '
		<tr>
			<td><a href="?id='.$major['id'].'">'.$major['academic_program'].'</a></td>
			<td>'.$major['college'].'</td>
			<td>'.$major['department'].'</td>
			<td>'.$major['program_type'].'</td>
			<td>';
			if (! empty($major['graduate'])){
				$table .=  'Graduate';
			}else{
				$table .= 'Undergrad';
			}
			$table .= '</td>
			<td>';
			if (! empty($major['online_only'])){
				$table .= 'Only';
			}elseif(! empty($major['online_learning'])){
				$table .= 'Available';
			}
			$table .= '</td>
			<td>';
			if (! empty($major['note'])){
				$table .=  $major['note'];
			}
			$table .= '</td>
			<td><span style="white-space: nowrap;">'.apstyle_date($major['timestamp'], "g:i a, l, F j").'</span></td>
			<td>'.count($major['similar_programs']).'</td>
		</tr>
		';
	}
	$table .= '
	</tbody>
</table>
</div>'.PHP_EOL;

	return $table;

}


function get_blank_major(){
	global $timestamp;
	$majors_page = array(
		'id' => NULL,
		'academic_program' => NULL,
		'sort_title' => NULL,
		'sort_order' => NULL,
		'program_type' => NULL,
		'department' => NULL,
		'college' => NULL,
		'online_learning' => NULL,
		'online_only' => NULL,
		'graduate' => NULL,
		'note' => NULL,
		'program_simple_type' => NULL,
		'academic_program_catelog' => NULL,
		'academic_year' => NULL,
		'timestamp' => $timestamp,
	);
	$majors_page['conent'] = array(
		'id' => NULL,
		'academic_program_id' => NULL,
		'academic_program_title' => NULL,
		'description' => NULL,
		'learn_how' => NULL,
		'learn_how_links' => NULL,
		'main_image_url' => NULL,
		'main_image_caption' => NULL,
		'main_image_credit' => NULL,
		'main_image_alt' => NULL,
		'curriculum_text' => NULL,
		'curriculum_link_text' => NULL,
		'curriculum_link_url' => NULL,
		'admissions_headline' => NULL,
		'admissions_text' => NULL,
		'admissions_link_text' => NULL,
		'admissions_link_url' => NULL,
		'inside_the_program_headline' => NULL,
		'inside_the_program_text' => NULL,
		'inside_the_program_link_text' => NULL,
		'inside_the_program_link_url' => NULL,
		'inside_the_program_image_url' => NULL,
		'inside_the_program_image_alt' => NULL,
		'wildcard_headline' => NULL,
		'wildcard_text' => NULL,
		'wildcard_link_text' => NULL,
		'wildcard_link_url' => NULL,
		'careers_headline' => NULL,
		'careers_text' => NULL,
		'careers_link_text' => NULL,
		'careers_link_url' => NULL,
		'academic_year' => NULL,
		'basename' => NULL,
		'program_links' => NULL,
		'similar_programs' => NULL,
		'meta_description' => NULL,
		'meta_keywords' => NULL,
		'timestamp' => $timestamp,
	);
	$majors_page['similar_programs'] = array();
	return $majors_page;
}


// CREATE

function new_majors_page(){

}

function new_major($major = null){
	global $timestamp;
	$new_major = array(
		'id' => NULL,
		'academic_program' => NULL,
		'sort_title' => NULL,
		'sort_order' => NULL,
		'program_type' => NULL,
		'department' => NULL,
		'college' => NULL,
		'online_learning' => NULL,
		'online_only' => NULL,
		'graduate' => NULL,
		'note' => NULL,
		'program_simple_type' => NULL,
		'academic_program_catelog' => NULL,
		'academic_year' => NULL,
		'timestamp' => $timestamp,
	);
	if (empty($major) OR (! is_array($major))  ){
		$major = $new_major;
	}else{
		//take whatever is in the major array and combine it to make a full major
		$major = array_merge($new_major, $major);
	}
	global $mysqli;
    $query = "SELECT * FROM `majors_colleges` WHERE 1 ;"; //ORDER BY `name` ASC
	$result = $mysqli->query($query);
    while ($row = $result->fetch_assoc()){
   		$return[] = $row;
    }

	return $major;
}

function new_college(){

}

function new_department(){

}



// retrieve



// update
function update_majors_page(){

}

function update_major(){

}

function update_college(){

}

function update_department(){

}



// delete

function delete_majors_page(){

}

function delete_major(){

}

function delete_college(){

}

function delete_department(){

}

function display_degree_form($major=null, $options = array()){
	$form = null;
	if (! is_array($major) AND is_int($major)){
		$major_id = $major;
		$major = get_major_by_id($major_id);
	}
	if (empty ($major) ){
		$major = get_blank_major();
	}
	/*
	if (! is_array($options)){
		$options = null;
	}
	
	$defaults = array(
		'id' => null,
		'order' => 'alpha',
		'college' => null,
		'department' => null,
		'online' => null,
		'minor' => null,
		'certificates' => null,
		'badges' => null,
	);

	$options = array_merge($defaults, $options);
	*/
/*
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `academic_program` varchar(255) NOT NULL DEFAULT '',
  `sort_title` varchar(255) NOT NULL DEFAULT '',
  `sort_order` tinyint(1) DEFAULT NULL,
  `program_type` varchar(255) NOT NULL DEFAULT '',
  `department` varchar(255) DEFAULT '',
  `college` varchar(255) DEFAULT NULL,
  `online_learning` tinyint(1) DEFAULT NULL,
  `online_only` tinyint(4) DEFAULT NULL,
  `graduate` tinyint(1) DEFAULT NULL,
  `note` text,
  `program_simple_type` varchar(255) DEFAULT NULL,
  `academic_program_catelog` varchar(255) DEFAULT '',
  `academic_year` year(4) DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

*/
	$form .= '
<form id="degree-info" >';
	if (! empty($major['id'])){
		$form .= '<input type="hidden" value="'.$major['id'].'">'.PHP_EOL;
	}
	$colleges = get_colleges();
	$form .= '
	<label class="show-for-sr" for="academic_program">Title</label> 
	<input id="academic_program" class="" type="text" placeholder="Degree Title"';
	if (! empty($major['academic_program'])){
		$form .= ' value="'.htmlspecialchars($major['academic_program']).'"'.PHP_EOL;
	}
	$form .='>
	<label class="show-for-sr" for="program_type">Program Type</label> 
	<input id="program_type" class="" type="text" placeholder="Program Type"';
	if (! empty($major['program_type'])){
		$form .= ' value="'.htmlspecialchars($major['program_type']).'"'.PHP_EOL;
	}
	$form .='>
	
	<title id="design--search_63e3f07a1c603">Search</title>
	<use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol#design--search"></use></svg> 
	<span class="show-for-sr">Search</span>
	</button> <label class="show-for-sr" for="select">Select Degree Type</label> 
	<select id="filter">
		<optgroup label="Degree Program Type"> 
		<option value="all">All Programs</option>
		<option value="undergrad">Undergrad Majors &amp; Minors</option>
		<option value="grad">Graduate Degrees</option>
		<option value="online">Online</option>
		<option value="certificates">Certificates</option>
		<option value="badges">Badges</option>
		</optgroup> 
	</select>
	<button type="submit" class="" value="Submit">
</form>';
	return $form;
}

function get_info($id){
	global $action;
	$major_info = get_major_by_id($id);
	$info = '<ul class="page_info">'.PHP_EOL;
	$info .= '<li>'.$major_info['academic_program'].' — '.$major_info['program_type'].' ('.$major_info['college'].', '.$major_info['department'].')</li>'.PHP_EOL;
	$info .= '<li><strong>Last updated:</strong> '.apstyle_date($major_info['timestamp'], "F j, Y, g:i a").'</li>'.PHP_EOL;
	if (! empty($major_info['note'])){
		$info .= '<li><strong>Note:</strong> '.$major_info['note'].'</li>'.PHP_EOL;
	}
	if (! empty($major_info['academic_program'])){
		$info .= '<li><strong>Title:</strong> '.$major_info['academic_program'].'</li>'.PHP_EOL;
	}
	$info .= '</ul>'.PHP_EOL;

	return $info;
}


function display_editable_major($major_id=null){
	$main_content = NULL;
	if (! is_array($major_id) and is_numeric($major_id)){
		$major = get_major_by_id($major_id);
	}elseif(! empty($major_id['id'])){
		$major = $major_id;
		$major_id = $major['id'];
	}else{
		$major = get_blank_major();
		$major_id = null;
	
	}
	//$mysqli->insert_id;
	if (empty ($major['content']) AND (! empty($major_id))){
		$major['content'] = get_major_content($major_id);
	}
	if (empty ($major['similar_programs']) AND (! empty($major_id))){
		$major['similar_programs'] = get_majors_similar_programs($major_id);
	}
	if (! empty($major) ){
		
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
			$modal = '';
			$main_content .= '
			<div class="link-collection">
				<ul>';
				$i = 0;
				foreach ($program_links as $program_link){
					$main_content .= '
					<li><a href="#edit_links_'.$i.'" rel="modal:open">'.$program_link['link_text'].'</a></li>
					';
					$modal .= '
<div id="edit_links_'.$i.'" class="modal">
	<form action="action_page.php" id="program_links_'.$i.'">
		<label for="program_link_link_text_'.$i.'">Link Text</label> 
		<input type="text" placeholder="Link Text" name="program_link_link_text_'.$i.'" required value="'.$program_link['link_text'].'">
		<label for="program_link_href_'.$i.'">URL</label> 
		<input type="text" placeholder="https://..." name="program_link_href_'.$i.'" required value="'.$program_link['href'].'">
		<div>
			<button type="button" class="cancelbtn">Cancel</button>
			<button type="submit" class="signupbtn">Save</button> 
		</div>
	</form>
</div>
';
					$i++;
				}
			$main_content .= '
				</ul>
			</div>
			';
			}
			$main_content .= '
			<div class="program-card__detail" id="description" contenteditable="true">
				'.$major['content']['description'].'
			</div>';
		////LEARN HOW///
			$main_content .= '
			<div class="program-card__cta">
				<h3 class="heading4" id="learn_how" contenteditable="true">'.$major['content']['learn_how'].'</h3>
				<div class="button-collection landing-panel__buttons button-collection--accent-first">';
				if (! empty($major['content']['learn_how_links'])){
					$learn_how_links = json_decode($major['content']['learn_how_links'], true);
					$i = 0;
					foreach ($learn_how_links as $learn_how_link){
						$main_content .= '
						<a href="#edit_learn_how_links_'.$i.'" rel="modal:open" role="button" class="button">'.$learn_how_link['link_text'].'<svg class="icon button__trailing-icon" title="'.$learn_how_link['link_text'].'"><use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--arrow-right"></use></svg></a> ';
					
					$modal .= '
<div id="edit_learn_how_links_'.$i.'" class="modal">
	<form action="action_page.php" id="learn_how_link_'.$i.'">
		<label for="learn_how_link_link_text_'.$i.'">Link Text</label> 
		<input type="text" placeholder="Link Text" name="learn_how_link_link_text_'.$i.'" required value="'.$learn_how_link['link_text'].'">
		<label for="learn_how_link_href_'.$i.'">URL</label> 
		<input type="text" placeholder="https://..." name="learn_how_link_href_'.$i.'" required value="'.$learn_how_link['href'].'">
		<div>
			<button type="button" class="cancelbtn">Cancel</button>
			<button type="submit" class="signupbtn">Save</button> 
		</div>
	</form>
</div>
';
					$i++;
					
					}
					
				}
			$main_content .= '
				</div>
			</div>';
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
		////Wildcard (Applied Learning) and Admission ///

	if (! empty ($major['content']['wildcard_headline']) OR ! empty ($major['content']['admissions_headline'])){
	
$main_content .= '
<section class="teaser-collection section-wrap collection--two-columns">
	<div class="collection__items">
		<div class="teaser collection__item ">
			<div class="teaser__body">
				<div class="teaser__headline">
					<h3 class="headline-group ">
						<span class="head" id="wildcard_headline" contenteditable="true">'.$major['content']['wildcard_headline'].'</span>
					</h3>
				</div>
				<div class="teaser__editorial" id="wildcard_text"  contenteditable="true">'.$major['content']['wildcard_text'].'</div>
				<div class="teaser__links">
					<a class=" link--rich" href="#wildcard_link_form" rel="modal:open" ><span>'.$major['content']['wildcard_link_text'].'</span></a>
';
					$modal .= '
<div id="wildcard_link_form" class="modal">
	<form action="action_page.php" id="wildcard_link">
		<label for="wildcard_link_text">Link Text</label> 
		<input type="text" placeholder="Link Text" name="wildcard_link_text" required value="'.$major['content']['wildcard_link_text'].'">
		<label for="wildcard_link_href">URL</label> 
		<input type="text" placeholder="https://..." name="wildcard_link_href" required value="'.$major['content']['wildcard_link_url'].'">
		<div>
			<button type="button" class="cancelbtn">Cancel</button>
			<button type="submit" class="signupbtn">Save</button> 
		</div>
	</form>
</div>
';


$main_content .= '
				</div>
			</div>
		</div>
		<div class="teaser collection__item ">
			<div class="teaser__body">
				<div class="teaser__headline">
					<h3 class="headline-group ">
						<span class="head" id="admissions_headline"  contenteditable="true">'.$major['content']['admissions_headline'].'</span>
					</h3>
				</div>
				<div class="teaser__editorial" id="admissions_text"  contenteditable="true">'.$major['content']['admissions_text'].'</div>
				<div class="teaser__links">
					<a class=" link--rich" href="#admissions_link_form" rel="modal:open"><span>'.$major['content']['admissions_link_text'].'</span></a>';
					$modal .= '
<div id="admissions_link_form" class="modal">
	<form action="action_page.php" id="admissions_link">
		<label for="admissions_link_text">Link Text</label> 
		<input type="text" placeholder="Link Text" name="admissions_link_text" required value="'.$major['content']['admissions_link_text'].'">
		<label for="admissions_link_url">URL</label> 
		<input type="text" placeholder="https://..." name="admissions_link_url" required value="'.$major['content']['admissions_link_url'].'">
		<div>
			<button type="button" class="cancelbtn">Cancel</button>
			<button type="submit" class="signupbtn">Save</button> 
		</div>
	</form>
</div>
';
$main_content .= '
				</div>
			</div>
		</div>
	</div>
</section>';
	}

//INSIDE THE PROGRAM
	if (! empty ($major['content']['inside_the_program_headline'])){

$main_content .= '
<section class="section-wrap section-wrap--dots">
	<header class="section-header section-header--no-border">
		<h2>Inside the Program</h2>
	</header>
	<div class="teaser teaser--columned-intro">
		<div class="teaser__image">
			<a><img src="'.$major['content']['inside_the_program_image_url'].'" alt="'.$major['content']['inside_the_program_image_alt'].'"></a>
		</div>
		<div class="teaser__body">
			<div class="teaser__headline">
				<h3 class="headline-group "><span class="head" id="inside_the_program_headline" contenteditable="true">'.$major['content']['inside_the_program_headline'].'</span></h3>
			</div>
			<div class="teaser__editorial" id="inside_the_program_text" contenteditable="true">'.$major['content']['inside_the_program_text'].'</div>
			<div class="teaser__links"><a class=" link--rich"  href="#inside_the_program_link_form" rel="modal:open"><span>'.$major['content']['inside_the_program_link_text'].'</span></a>';
					$modal .= '
<div id="inside_the_program_link_form" class="modal">
	<form action="action_page.php" id="inside_the_program_link">
		<label for="inside_the_program_link_text">Link Text</label> 
		<input type="text" placeholder="Link Text" name="inside_the_program_link_text" required value="'.$major['content']['inside_the_program_link_text'].'">
		<label for="inside_the_program_link_url">URL</label> 
		<input type="text" placeholder="https://..." name="inside_the_program_link_url" required value="'.$major['content']['inside_the_program_link_url'].'">
		<div>
			<button type="button" class="cancelbtn">Cancel</button>
			<button type="submit" class="signupbtn">Save</button> 
		</div>
	</form>
</div>
';
$main_content .= '</div>
		</div>
	</div>
</section>';
	}

//CURRICULUM & CAREERS


	if (! empty ($major['content']['curriculum_text']) OR ! empty ($major['content']['careers_headline'])){

$main_content .= '
<section class="teaser-collection section-wrap collection--two-columns section-wrap--nipple-down">
	<div class="collection__items">
		<div class="teaser collection__item">
			<div class="teaser__body">
				<div class="teaser__headline"><h3 class="headline-group"><span class="head">Curriculum</span></h3></div>
				<div class="teaser__editorial" id="curriculum_text"  contenteditable="true">'.$major['content']['curriculum_text'].'</div>
				<div class="teaser__links">
					<a class=" link--rich" href="#curriculum_link_form" rel="modal:open"><span>'.$major['content']['curriculum_link_text'].'</span></a>';
					$modal .= '
<div id="curriculum_link_form" class="modal">
	<form action="action_page.php" id="curriculum_link">
		<label for="curriculum_link_text">Link Text</label> 
		<input type="text" placeholder="Link Text" name="curriculum_link_text" required value="'.$major['content']['curriculum_link_text'].'">
		<label for="curriculum_link_url">URL</label> 
		<input type="text" placeholder="https://..." name="curriculum_link_url" required value="'.$major['content']['curriculum_link_url'].'">
		<div>
			<button type="button" class="cancelbtn">Cancel</button>
			<button type="submit" class="signupbtn">Save</button> 
		</div>
	</form>
</div>
';
$main_content .= '
				</div>';
				$degree_maps = get_degree_maps($major_id);
				if (! empty($degree_maps)){
					$headline = 'Degree Map';
					if (count($degree_maps) > 1){
						$headline = 'Degree Maps';
					}
					$main_content .= '
				<div class="teaser__headline"><h3 class="headline-group "><span class="head">'.$headline.'</span></h3></div>
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
		<div class="teaser collection__item">
			<div class="teaser__body">
				<div class="teaser__headline"><h3 class="headline-group"><span class="head" id="careers_headline" contenteditable="true">'.$major['content']['careers_headline'].'</span></h3></div>
				<div class="teaser__editorial" id="careers_text" contenteditable="true">'.$major['content']['careers_text'].'</div>
				<div class="teaser__links">
					<a class=" link--rich"  href="#careers_link_form" rel="modal:open"><span>'.$major['content']['careers_link_text'].'</span></a>
				</div>
			</div>
		</div>
	</div>
</section>';
					$modal .= '
<div id="careers_link_form" class="modal">
	<form action="action_page.php" id="careers_link">
		<label for="careers_link_text">Link Text</label> 
		<input type="text" placeholder="Link Text" name="careers_link_text" required value="'.$major['content']['careers_link_text'].'">
		<label for="careers_link_url">URL</label> 
		<input type="text" placeholder="https://..." name="careers_link_url" required value="'.$major['content']['careers_link_url'].'">
		<div>
			<button type="button" class="cancelbtn">Cancel</button>
			<button type="submit" class="signupbtn">Save</button> 
		</div>
	</form>
</div>
';

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
'.$modal.PHP_EOL;	}
	

	}
	return $main_content;
}


