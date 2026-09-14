<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/../config/functions.php');
error_reporting(E_ALL);
$title = 'Programs';
define('isWSU',true);

if (empty($header_items) OR (! is_array($header_items)) ){
	$header_items = array();
}

$header_items_temp = array(
	'<meta charset="utf-8" />',
	'<meta lang="en-us">',
	'<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">',
	'<meta name="description" content="A tool for inspecting Department Tools pages and assessing their health">',
	'<meta name="author" content="Mike Marlett">',
	'<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">',
	'<link href="//cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css" rel="stylesheet">',
	'<link href="css/dashboard.css" rel="stylesheet">',
	'<link href="css/style_v3.css" rel="stylesheet">',
	'<style>
	.dropdown-submenu {
  position: relative;
}

.dropdown-submenu a::after {
  transform: rotate(-90deg);
  position: absolute;
  right: 6px;
  top: .8em;
}

.dropdown-submenu .dropdown-menu {
  top: 0;
  left: 100%;
  margin-left: .1rem;
  margin-right: .1rem;
  z-index: 2000;
}
</style>'
	);

$header_items = array_merge($header_items_temp, $header_items);

//footer scripts will get loaded at the bottom of the page, so they won't be pre populated
$footer_scripts = array(
'<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>',
'<script src="https://kit.fontawesome.com/a88f374b61.js" crossorigin="anonymous"></script>',
'<script src="https://code.jquery.com/jquery-3.6.4.min.js" integrity="sha256-oP6HI9z1XaZNBrJURtCoUT5SUnxFr8s3BzRl+cbzUq8=" crossorigin="anonymous"></script>',
'<script src="//cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js" crossorigin="anonymous"></script>',
'<script>
  function resizeIframe(obj) {
    obj.style.height = obj.contentWindow.document.body.scrollHeight + \'px\';
  }
</script>',
'<script type="text/javascript">
$(document).ready(function() {
    $("body").tooltip({ selector: \'[data-toggle=tooltip]\' });
});
</script>
',
);



function short_string($string, $length=20){
	$string = trim($string);
	if (strlen($string) > ($length)){
		$string = substr($string,0,$length-3).'...';
	}
	return $string;
}

//setup basic program page stuff and return the id of the page created
function  create_program_page($program_id){ //program_id is the `majors_academic_programs`.`id`
		global $mysqli;
		//get values for filling in default values on select fields
		$sql = "SELECT * FROM `majors_academic_programs` WHERE `id` = '$program_id'";
		$academic_program = mysqli_single_row($sql); 
		$learn_how = $mysqli->real_escape_string('Learn how '.$academic_program['academic_program'].' <br>is the right fit for you.');
		$curriculum_link_text = $mysqli->real_escape_string('View the '.$academic_program['academic_program'].' Curriculum');
		$admissions_headline = $mysqli->real_escape_string('Admission to the '.$academic_program['academic_program'].' Program');
		$admissions_link_text = $mysqli->real_escape_string($academic_program['academic_program'].' Admission Requirements');
		$careers_headline = $mysqli->real_escape_string('Careers in '.$academic_program['academic_program']);

		$sql = "INSERT INTO `majors_programs_content` (`id`, `academic_program_id`, `learn_how`, `curriculum_link_text`, `admissions_headline`, `admissions_link_text`, `careers_headline`, `timestamp`) 
		VALUES (NULL, '$program_id', '$learn_how', '$curriculum_link_text', '$admissions_headline', '$admissions_link_text', '$careers_headline', '".date('Y-m-d H:i:s')."');";
		if (! $result = $mysqli->query($sql)){
//			printf("Error message: %s\n<br>$sql<br>", $mysqli->error);
			die;
		}else{
			$program_content_id = $mysqli->insert_id;
		}
		//assuming that we didn't die ...
		// set the program_content_key, too
		$sql = "INSERT INTO `majors_program_content_key` (`id`, `academic_program_id`, `program_content_id`) VALUES (NULL, '$program_id', '$program_content_id');";
		if (! $result = $mysqli->query($sql)){
		//	printf("Error message: %s\n<br>$sql<br>", $mysqli->error);
			die;
		}else{
			$last_id = $mysqli->insert_id;
		}
		//assuming that we didn't die ...
		// get the department 
		$sql = "SELECT `majors_departments`.`id` FROM `majors_departments`, `majors_academic_programs` WHERE `majors_departments`.`department` = `majors_academic_programs`.`department` AND `majors_academic_programs`.`id` = '$program_id';";
		$row = mysqli_single_row($sql);
		$department_id = $row['id'];
		// set the department_content_key, too
		$sql = "INSERT INTO `majors_department_content_key` (`id`, `department_id`, `program_content_id`) VALUES (NULL, '$department_id', '$program_content_id');";
		if (! $result = $mysqli->query($sql)){
//			printf("Error message: %s\n<br>$sql<br>", $mysqli->error);
			die;
		}else{
			$last_id = $mysqli->insert_id;
		}
		return ($program_content_id);
}

//if we have action=create, create a program page and get an id for it
if ( isset($_POST['action']) AND isset($_POST['program_id']) ){

	$program_id = $mysqli->real_escape_string($_POST['program_id']);
	
	if  ($_POST['action'] == 'create'){

		$program_content_id = create_program_page($program_id);

	}elseif ($_POST['action'] == 'edit'){
		//Do we need to do anything here for edit? Yes, idiot, we need the content ID.
		// get the department 
		$sql = "SELECT `majors_programs_content`.`id` FROM `majors_program_content_key`, `majors_programs_content` WHERE `majors_program_content_key`.`program_content_id` = `majors_programs_content`.`id` AND `majors_program_content_key`.`academic_program_id` = '$program_id';";
		$row = mysqli_single_row($sql);
		$program_content_id = $row['id'];
		
	}elseif (($_POST['action'] == 'clone') AND isset($_POST['created_programs'])){
		$program_content_id = create_program_page($program_id);
		$cloned_program = $mysqli->real_escape_string($_POST['created_programs']);

		$sql = "SELECT `description`, `learn_how`, `main_image_url`, `main_video_url`, `main_image_caption`, `main_image_credit`, `main_image_alt`, `curriculum_text`,  `curriculum_link_text`,  `curriculum_link_url`, `admissions_headline`, `admissions_text`, `admissions_link_text`, `admissions_link_url`, `inside_the_program_headline`, `inside_the_program_text`, `inside_the_program_link_text`, `inside_the_program_link_url`, `inside_the_program_image_url`, `inside_the_program_image_alt`, `wildcard_headline`, `wildcard_text`, `wildcard_link_text`, `wildcard_link_url`, `careers_headline`, `careers_text`, `careers_link_text`, `careers_link_url` FROM `majors_programs_content` WHERE `id` = '$cloned_program';";
		$new_program = mysqli_single_row($sql);
		
		$new_program['id']=$program_content_id;
		$new_program['timestamp']=date('Y-m-d H:i:s');
		$sql = build_insert_update_sql('majors_programs_content', 'id', $new_program);

		//generic_mysqli_query($sql);
		
	}
}

$header_items[] = '<style>
	.popover_p:not(:first-child){
		margin-top: 10px;
		padding-top: 10px;
		border-top: 1px rgba(0,0,0, 0.5) solid;
	}
</style>';


$footer_scripts[] = '<script>$(document).ready(function() {
	$(\'#program_pages_info\').DataTable({
        dom: "Bfrtip",
        "order": [ [ 2, "asc" ], [5, "asc"], [ 3, "asc" ], [ 1, "asc" ] ], //college, graduate, department, program
		"autoWidth": false,
    	"colReorder": true,
		"pageLength": 25, 
		"aoColumnDefs": [ { "bSortable": false, "aTargets": [ 0 ] } ],
		"stateSave": true,
        buttons: [
            "colvis"
        ]
	});
} );</script>';


$sql = "SELECT * from `majors_colleges` WHERE 1;";
$colleges = mysqli_multiple_rows($sql);

require_once('admin_head.php');
?>
<body>
	<main role="main" class="col">
<?php
			if ($message){
				echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
	<i class="fa fa-exclamation-triangle fa-1x" aria-hidden="true"> </i> &nbsp;'.$message.'
	<button type="button" class="close" data-dismiss="alert" aria-label="Close">
    <span aria-hidden="true">&times;</span>
  </button>
</div>';
			}

$sql = "SELECT `majors_academic_programs`.`academic_program`, `majors_academic_programs`.`college`, `majors_colleges`.`code` as `code`, `majors_program_content_key`.`program_content_id` 
	FROM `majors_academic_programs`, `majors_colleges`, `majors_program_content_key` 
	WHERE `majors_academic_programs`.`college`=`majors_colleges`.`college` AND `majors_program_content_key`.`academic_program_id` = `majors_academic_programs`.`id`
	ORDER BY `majors_colleges`.`code`, `majors_academic_programs`.`graduate`, `majors_academic_programs`.`department`, `majors_academic_programs`.`academic_program`;";
$active_program_content = mysqli_multiple_rows($sql);

$completed_programs_option_select = array(0 => '<option value="">Select One</option>');


foreach ($active_program_content as $active_content){
	$completed_programs_option_select[$active_content['program_content_id']] = '<option value="'.$active_content['program_content_id'].'">'.$active_content['academic_program'].' ('.substr($active_content['college'], 0, 14).')</option>'.PHP_EOL;
}

$sql = "SELECT DISTINCT `majors_academic_programs`.*, `majors_colleges`.`code` as `code` 
	FROM `majors_academic_programs`, `majors_colleges`, `majors_programs_content` 
	WHERE `majors_academic_programs`.`college`=`majors_colleges`.`college`
	ORDER BY `majors_colleges`.`code`, `majors_academic_programs`.`graduate`, `majors_academic_programs`.`department`, `majors_academic_programs`.`academic_program`;";
$academic_programs = mysqli_multiple_rows($sql);
?>

<h1>Academic Programs</h1>
<div class="container-fluid">
	<div id="programs_table">

<?php
/*
  <tr>
  	<th></th>
  	<th colspan="5">Program</th>
  	<th	colspan="3">Actions</th>
  </tr>style="font-size: 0.8rem;"
*/
//print_r($_POST);
?>

<table class="table table-striped table-hover table-sm table-bordered text-left dt-responsive" id="program_pages_info"  >
 <caption></caption>
 <thead class="thead-dark">
  <tr>
  	<th data-priority="2">Edit</th>
  	<th data-priority="1">Academic Program</th>
  	<th data-priority="6">College</th>
  	<th data-priority="7">Department</th>
  	<th data-priority="10">Degree</th>
  	<th data-priority="11">Readable Degree</th>
  	<th>Graduate</th>
  	<th>Online</th>
	<th data-priority="3">Note</th>
	<th>Main</th>
	<th>Video</th>
	<th>Curriculum</th>
	<th>Admissions</th>
	<th>Inside</th>
	<th>Wildcard</th>
	<th>Careers</th>
	<th data-priority="5">Last Update</th>
  	<th data-priority="8">Clone / Add</th>
  	<th data-priority="4">Similar</th>
  </tr>
</thead>
<tbody>
<?php
if ($academic_programs) {
	// output data of each row
	
	/*
$academic_program['id'] = 1
$academic_program['content_id'] =
$academic_program['academic_program'] = Accounting
$academic_program['program_type'] = Minor
$academic_program['department'] = Accounting
$academic_program['college'] = BA
$academic_program['online_learning'] =
$academic_program['graduate'] = 0
	*/

	$academic_programs_option_select = array(0 => '<option value="">Select One</option>');
	foreach($academic_programs as $academic_program) {
/*
$program_content['id']
$program_content['academic_program_id']
$program_content['description']
$program_content['learn_how']
$program_content['main_image_url']
$program_content['main_video_url']
use_main_image_caption
$program_content['main_image_caption']
use_main_image_credit
$program_content['main_image_credit']
$program_content['main_image_alt']
$program_content['curriculum_text']
$program_content['curriculum_link_text']
$program_content['curriculum_link_url']
$program_content['admissions_headline']
$program_content['admissions_text']
$program_content['admissions_link_text']
$program_content['admissions_link_url']
$program_content['inside_the_program_headline']
$program_content['inside_the_program_text']
use_inside_link
$program_content['inside_the_program_link_text']
$program_content['inside_the_program_link_url']
$program_content['inside_the_program_image_url']
$program_content['inside_the_program_image_alt']
$program_content['wildcard_headline']
$program_content['wildcard_text']
$program_content['wildcard_link_text']
$program_content['wildcard_link_url']
$program_content['careers_headline']
$program_content['careers_text']
$program_content['careers_link_text']
$program_content['careers_link_url']
$program_content['note']
*/
		$program_content = array('id'=> NULL, 'academic_program_id'=> NULL, 'description'=> NULL, 'learn_how'=> NULL, 'main_image_url'=> NULL, 'main_video_url'=> NULL, 'use_main_image_caption'=> NULL, 'main_image_caption'=> NULL, 'main_image_credit'=> NULL, 'use_main_image_credit'=> NULL, 'main_image_alt'=> NULL, 'curriculum_text'=> NULL, 'curriculum_link_text'=> NULL, 'curriculum_link_url'=> NULL, 'admissions_headline'=> NULL, 'admissions_text'=> NULL, 'admissions_link_text'=> NULL, 'admissions_link_url'=> NULL, 'inside_the_program_headline'=> NULL, 'inside_the_program_text'=> NULL, 'use_inside_link'=> NULL, 'inside_the_program_link_text'=> NULL, 'inside_the_program_link_url'=> NULL, 'inside_the_program_image_url'=> NULL, 'inside_the_program_image_alt'=> NULL, 'wildcard_headline'=> NULL, 'wildcard_text'=> NULL, 'use_wildcard_link'=> NULL, 'wildcard_link_text'=> NULL, 'wildcard_link_url'=> NULL, 'careers_headline'=> NULL, 'careers_text'=> NULL, 'careers_link_text'=> NULL, 'careers_link_url'=> NULL, 'timestamp'=> NULL);

		//Set this up for the modal since we're already looping
		//also since we'are looping, let's get a content id
		$academic_program['content_id'] = NULL;
		$sql = "SELECT `program_content_id` FROM `majors_program_content_key` WHERE `academic_program_id` = ".$academic_program['id'].";";
		$row = mysqli_single_row($sql);
		if ($row['program_content_id']){
			$academic_program['content_id'] = $row['program_content_id'];
			$sql = "SELECT * FROM `majors_programs_content` WHERE `majors_programs_content`.`id` = '".$academic_program['content_id']."';";
			//overwrite the empty array
			$program_content = mysqli_single_row($sql);
		}

		
		
		echo '<tr>
		<td style="white-space: nowrap;">';
		if ($academic_program['content_id'] > -1){ //if we have a content ID
		echo '
    		<a class="btn btn-wsu btn-sm" title="Edit this page" href="program_mockup.php?id='.$academic_program['content_id'].'"><i class="fa fa-pencil-square" aria-hidden="true"></i><span class="d-none d-lg-inline-block">&nbsp; Edit</span></a>
';
		}else{
		echo '
				<form name="create_form" id="create_form" method="post" enctype="multipart/form-data">
					<input type="hidden" name="action" id="action" value="create">
					<input type="hidden" name="program_id" id="program_id" value="'.$academic_program['id'].'">
					<input type="hidden" name="real_userid" value="">
					<button type="submit" class="btn btn-wsu btn-sm" title="Create with this page"><i class="fa fa-rocket" aria-hidden="true"></i><span class="d-none d-lg-inline-block">&nbsp; Create</span></button>
				</form>
';		
		}
		
		
		echo '    	</td>
    	<td style="white-space: nowrap;">
    		<div contenteditable="true" class="edit" id="academic_program_'.$academic_program['id'].'" style="display:inline-block">'.$academic_program['academic_program'].'</div> ('.$academic_program['id'].')
    	</td>
    	<td style="white-space: nowrap;">
    		<a data-toggle="tooltip" title="'.htmlentities($academic_program['college']).'">'.$academic_program['code'].'</a><span style="display:none;">'.$academic_program['college'].'</span>
		</td>
    	<td style="white-space: nowrap;">
    		<a data-toggle="tooltip" title="'.htmlentities($academic_program['department']).'">'.htmlentities(short_string($academic_program['department'])).'</a>
		</td>
    	<td>
    		<a data-toggle="tooltip" title="'.htmlentities($academic_program['program_type']).'">'.htmlentities(short_string($academic_program['program_type'],20)).'</a>
		</td>
    	<td>
    		<div contenteditable="true" class="edit" id="program_simple_type_'.$academic_program['id'].'">'.$academic_program['program_simple_type'].'</div>
		</td>'.PHP_EOL;
	// ************** Graduate **************** //
		if ($academic_program['graduate'] == 1){
			$academic_program['graduate'] = '<span style="display:none">1</span>
		<form name="change_grad" id="change_grad_form_'.$academic_program['id'].'" method="post" action="update_program.php" enctype="multipart/form-data">
		<input type="hidden" name="program_id" id="content_id" value="">
		<input type="hidden" name="real_userid" value="">
		<select class="form-control program_select" id="graduate_'.$academic_program['id'].'" name="graduate">
		<option value="1" selected>G</option>
		<option value="0">UG</option>
		</select>
		</form>';
		}else{
			$academic_program['graduate'] = '<span style="display:none">0</span>
		<form name="change_grad" id="change_grad_form_'.$academic_program['id'].'" method="post" action="update_program.php" enctype="multipart/form-data">
		<input type="hidden" name="program_id" id="content_id" value="">
		<input type="hidden" name="real_userid" value="">
		<select class="form-control program_select" id="graduate_'.$academic_program['id'].'" name="graduate">
		<option value="1">G</option>
		<option value="0" selected>UG</option>
		</select>
		</form>';
		}


		echo '    	<td>
    		'.$academic_program['graduate'].'
		</td>
    	';
		// ************** Online **************** //
		if ($academic_program['online_learning'] == 1){
			$academic_program['online_learning'] = '<span style="display:none">1</span><span style="display:none">1</span>
		<form name="change_online" id="change_online_form_'.$academic_program['id'].'" method="post" action="update_program.php" enctype="multipart/form-data">
		<input type="hidden" name="program_id" id="content_id" value="">
		<input type="hidden" name="real_userid" value="">
		<select class="form-control program_select" id="online_learning_'.$academic_program['id'].'" name="online_learning">
		<option value="1" selected>Yes</option>
		<option value="0">No</option>
		</select>
		</form>';
		}else{
			$academic_program['online_learning'] = '<span style="display:none">0</span>		<form name="change_online" id="change_online_form_'.$academic_program['id'].'" method="post" action="update_program.php" enctype="multipart/form-data">
		<input type="hidden" name="program_id" id="content_id" value="">
		<input type="hidden" name="real_userid" value="">
		<select class="form-control program_select" id="online_learning_'.$academic_program['id'].'" name="online_learning">
		<option value="1" >Yes</option>
		<option value="0" selected>No</option>
		</select>
		</form>';
		}
		echo '    	<td>
    		'.$academic_program['online_learning'].'
		</td>
    	';

		// ************** Note **************** //
			if (! $academic_program['note']){
				$academic_program['note'] = '&nbsp;';
			}
			echo '<td><div contenteditable="true" class="edit" id="note_'.$academic_program['id'].'">'.$academic_program['note'].'</div></td>';



		// ************** Main Text and Image **************** //

		if (($program_content['description'] != NULL) OR ($program_content['learn_how'] != NULL) OR ($program_content['main_image_url'] != NULL) OR ($program_content['main_image_url'] != NULL) OR ($program_content['main_image_caption'] != NULL) OR ($program_content['main_image_alt'] != NULL)){
			
			if ($program_content['description']){
				$description_check = 'success';
			}else{
				$description_check = 'warning';
			}
			if (! $program_content['learn_how']){
				$description_check = 'warning';
			}
			if (! $program_content['main_image_url']){
				$description_check = 'warning';
			}
			if ((! $program_content['main_image_caption']) AND (($program_content['use_main_image_caption'] == 1) OR ($program_content['use_main_image_caption'] == NULL))){
				$description_check = 'warning';
			}
			if ((! $program_content['main_image_credit']) AND (($program_content['use_main_image_credit'] == 1) OR ($program_content['use_main_image_credit'] == NULL))){
				$description_check = 'warning';
			}
			if (! $program_content['main_image_alt']){
				$description_check = 'warning';
			}
			

			echo '<td style="text-align: center;"><a tabindex="0" data-toggle="popover" data-trigger="focus" data-html="true" data-popover-content="#main_'.$program_content['id'].'" data-popover-id="'.$program_content['id'].'" data-popover-section="main"><span class="text-'.$description_check.'"><i class="fa fa-check-circle"></i></span></a></td>';

		}else{
			echo '<td style="text-align: center;"><span style="display:none">0</span><a data-toggle="tooltip" title="No work has started."><span class="text-danger"><i class="fa fa-exclamation-triangle"></i></span></td>';
		}

		// ************** Main Video **************** //
		if ($program_content['main_video_url']){
			echo '<td style="text-align: center;"><a tabindex="0" data-toggle="popover" data-trigger="focus" data-html="true" data-popover-content="#video_'.$program_content['id'].'" data-popover-id="'.$program_content['id'].'" data-popover-section="video"><span class="text-success"><i class="fa fa-check-circle"></i></span></a></td>';
		}else{
			echo '<td style="text-align: center;"><span style="display:none">0</span><a data-toggle="tooltip" title="Got nothin\'."><span class="text-danger"><i class="fa fa-exclamation-triangle"></i></span></a></td>';
		}

		// ************** Curriculum **************** //
		if (($program_content['curriculum_text'] != NULL) OR ($program_content['curriculum_link_text'] != NULL) OR ($program_content['curriculum_link_url'])){
			if ($program_content['curriculum_text']){
				$description_check = 'success';
			}else{
				$description_check = 'warning';
			}
			if (! $program_content['curriculum_link_text']){
				$description_check = 'warning';
			}
			if (! $program_content['curriculum_link_url']){
			}
			
			echo '<td style="text-align: center;"><a tabindex="0" data-toggle="popover" data-trigger="focus" data-html="true" data-popover-content="#curriculum_'.$program_content['id'].'" data-popover-id="'.$program_content['id'].'" data-popover-section="curriculum"><span class="text-'.$description_check.'"><i class="fa fa-check-circle"></i></span></a></td>';
		}else{
			echo '<td style="text-align: center;"><span style="display:none">0</span><a data-toggle="tooltip" title="No work has started."><span class="text-danger"><i class="fa fa-exclamation-triangle"></i></span></td>';
		}

		// ************** Admissions **************** //

		if (($program_content['admissions_headline'] != NULL) OR ($program_content['admissions_text'] != NULL) OR ($program_content['admissions_link_text'] != NULL) OR ($program_content['admissions_link_url'])){
			if ($program_content['admissions_headline']){
				$description_check = 'success';
			}else{
				$description_check = 'warning';
			}
			if (! $program_content['admissions_text']){
				$description_check = 'warning';
			}
			if (! $program_content['admissions_link_text']){
				$description_check = 'warning';
			}
			if (! $program_content['admissions_link_url']){
				$description_check = 'warning';
			}
			


			echo '<td style="text-align: center;"><a tabindex="0" data-toggle="popover" data-trigger="focus" data-html="true" data-popover-content="#admissions_'.$program_content['id'].'" data-popover-id="'.$program_content['id'].'" data-popover-section="admissions"><span class="text-'.$description_check.'"><i class="fa fa-check-circle"></i></span></a></td>';
			
		}else{
			echo '<td style="text-align: center;"><span style="display:none">0</span><a data-toggle="tooltip" title="No work has started."><span class="text-danger"><i class="fa fa-exclamation-triangle"></i></span></td>';
		}



		// ************** Inside the Program **************** //

		if (($program_content['use_inside_the_program'] === "0")){
			echo '<td style="text-align: center;"><span style="display:none">0</span><a data-toggle="tooltip" title="Inside the Program is turned off."><span class="text-success"><i class="fa fa-check-circle"></i></span></td>';
		}elseif (
		($program_content['inside_the_program_headline'] != NULL) OR
		($program_content['inside_the_program_text'] != NULL) OR
		($program_content['inside_the_program_link_text'] != NULL) OR
		($program_content['inside_the_program_link_url'])
		){
			if ($program_content['inside_the_program_headline']){
				$description_check = 'success';
			}else{
				$description_check = 'warning';
			}
			
			if (! $program_content['inside_the_program_text']){
				$description_check = 'warning';
			}
			
			if ((! $program_content['inside_the_program_link_text']) AND (($program_content['use_inside_link'] == 1) OR ($program_content['use_inside_link'] == NULL))){
				$description_check = 'warning';
			}
			
			if ((! $program_content['inside_the_program_link_url']) AND (($program_content['use_inside_link'] == 1) OR ($program_content['use_inside_link'] == NULL))){
				$description_check = 'warning';
			}
			
			if (! $program_content['inside_the_program_image_url']){
				$description_check = 'warning';
			}
			
			if (! $program_content['inside_the_program_image_alt']){
				$description_check = 'warning';
			}

			echo '<td style="text-align: center;"><a tabindex="0" data-toggle="popover" data-trigger="focus" data-html="true" data-popover-content="#inside_the_program_'.$program_content['id'].'" data-popover-id="'.$program_content['id'].'" data-popover-section="inside_the_program"><span class="text-'.$description_check.'"><i class="fa fa-check-circle"></i></span></a></td>';
		}else{
			echo '<td style="text-align: center;"><span style="display:none">0</span><a data-toggle="tooltip" title="No work has started."><span class="text-danger"><i class="fa fa-exclamation-triangle"></i></span></td>';
		}


		// ************** Wildcard **************** //

		if (($program_content['wildcard_headline'] != NULL) OR ($program_content['wildcard_text'] != NULL) OR ($program_content['wildcard_link_text'] != NULL) OR ($program_content['wildcard_link_url'])){
			if ($program_content['wildcard_headline']){
				$description_check = 'success';
			}else{
				$description_check = 'warning';
			}
			if (! $program_content['wildcard_text']){
				$description_check = 'warning';
			}

			if ((! $program_content['wildcard_link_text']) AND (($program_content['use_wildcard_link'] == 1) OR ($program_content['use_wildcard_link'] == NULL))){
				$description_check = 'warning';
			}

			if ((! $program_content['wildcard_link_url']) AND (($program_content['use_wildcard_link'] == 1) OR ($program_content['use_wildcard_link'] == NULL))){
				$description_check = 'warning';
			}
			
			echo '<td style="text-align: center;"><a tabindex="0" data-toggle="popover" data-trigger="focus" data-html="true" data-popover-content="#wildcard_'.$program_content['id'].'" data-popover-id="'.$program_content['id'].'" data-popover-section="wildcard"><span class="text-'.$description_check.'"><i class="fa fa-check-circle"></i></span></a></td>';
		}else{
			echo '<td style="text-align: center;"><span style="display:none">0</span><a data-toggle="tooltip" title="No work has started."><span class="text-danger"><i class="fa fa-exclamation-triangle"></i></span></td>';
		}


		// ************** Careers **************** //

		if (($program_content['careers_headline'] != NULL) OR ($program_content['careers_text'] != NULL) OR ($program_content['careers_link_text'] != NULL) OR ($program_content['careers_link_url'])){
			if ($program_content['careers_headline']){
				$description_check = 'success';
			}else{
				$description_check = 'warning';
			}
			if (! $program_content['careers_text']){
				$description_check = 'warning';
			}
			if (! $program_content['careers_link_text']){
				$description_check = 'warning';
			}
			if (! $program_content['careers_link_url']){
				$description_check = 'warning';
			}
			
			echo '<td style="text-align: center;"><a tabindex="0" data-toggle="popover" data-trigger="focus" data-html="true" data-popover-section="careers" data-popover-id="'.$program_content['id'].'" data-popover-content="#careers_'.$program_content['id'].'" data-popover-id="'.$program_content['id'].'" data-popover-section="careers"><span class="text-'.$description_check.'"><i class="fa fa-check-circle"></i></span></a></td>';




		}else{
			echo '<td style="text-align: center;"><span style="display:none">0</span><a data-toggle="tooltip" title="No work has started."><span class="text-danger"><i class="fa fa-exclamation-triangle"></i></span></td>';
		}

		// ************** Timestamp **************** //

			if (($program_content['timestamp'] != NULL)){
				//echo str_replace(' ', '&nbsp;', date("F j, Y, g:i a", strtotime($row['lastmodified'])) ) ;
				$lastmodified = new DateTime($program_content['timestamp']);
				echo '<td style="text-align: center;"><span style="display:none;">'.str_replace(' ', '&nbsp;',$program_content['timestamp']).'</span><span style="white-space: nowrap;">'.ago($lastmodified).'</span></td>';
			}else{
				echo '<td style="text-align: center;"></td>';
			}
			

		// ************** More Buttons **************** //

		if ($academic_program['content_id'] > -1){ //if we have a content ID
		echo '		
		<td>
				<button type="button" class="btn btn-wsu btn-sm" data-toggle="modal" data-target="#add" data-conent_id="'.$academic_program['content_id'].'" data-program_name="'.$academic_program['academic_program'].'"><i class="fa fa-magnet fa-fw" aria-hidden="true"></i><span class="d-none d-lg-inline-block">&nbsp; Add</span></button>
		</td>
';
		}else{
			echo '			<td><button type="button" class="btn btn-wsu btn-sm" data-toggle="modal" data-target="#clone" data-program_id="'.$academic_program['id'].'" data-program_name="'.$academic_program['academic_program'].'"><i class="fa fa-clone fa-fw" aria-hidden="true"></i><span class="d-none d-lg-inline-block">&nbsp; Clone</span></button></td>
'; 
		}
		
		$sql = "SELECT COUNT(`main_academic_program_id`) as `count` FROM `majors_similar_programs` WHERE `main_academic_program_id` = '".$academic_program['id']."';";
		$main_badge = mysqli_single_row($sql);
		if ($main_badge['count'] > 0){
			$main_badge_display = ' <span class="badge">'.$main_badge['count'].'</span>';
		}else{
			$main_badge_display = '';
		}
		$sql = "SELECT COUNT(`similar_academic_program_id`) as `count` FROM `majors_similar_programs` WHERE `similar_academic_program_id` = '".$academic_program['id']."';";
		$secondary_badge = mysqli_single_row($sql);
		if ($secondary_badge['count'] > 0){
			$secondary_badge_display = ' <span class="badge" style="background-color: #aaa !important;">'.$secondary_badge['count'].'</span>';
		}else{
			$secondary_badge_display = '';
		}
		echo '		
		<td>
			<button type="button" class="btn btn-wsu btn-sm" data-toggle="modal" data-target="#similar" data-program_id="'.$academic_program['id'].'" data-program_name="'.$academic_program['academic_program'].'"><i class="fa fa-random fa-fw" aria-hidden="true"></i><span class="d-none d-lg-inline-block">&nbsp; Similar</span>'.$main_badge_display.$secondary_badge_display.'</button>
		</td>
	</tr>
';

}
}
?>

</tbody>
</table>


</div><!-- /programs-table -->

<div class="row align-items-center">
<div class="col my-2">
<button type="button" class="btn btn-wsu" data-toggle="modal" data-target="#create_program"><i class="fa fa-graduation-cap fa-fw" aria-hidden="true"></i><span class="d-none d-lg-inline-block">&nbsp; Create a Program</span></button>
</div>
</div>


</div><!-- / container -->
<?php
/*
//let's create a seperate html file with nothing but these program content messages
//we'll have the javascript call that file for the popovers
$file_html = '<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>Programs Content</title>
</head>
<body>
';
foreach($program_content_messages as $content_message){
	$file_html .= $content_message.PHP_EOL;
}
$file_html .= '</body>
</html>
';

$file = 'programs_content.html';
file_put_contents($file, $file_html); 
touch($file);

$file = 'programs_content.json';
file_put_contents($file, json_encode($program_content_messages)); 
touch($file);
unset ($program_content_messages);
*/

/* disable for testing
*/
?>


<!-- Button trigger modal -->
<!-- Modal -->
<div class="modal fade" id="add" tabindex="-1" role="dialog" aria-labelledby="Add_Program" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="Add_Program">Add Department</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true"> &times;</span></button>
			</div>
			<div class="modal-body">
				<p>Adding puts two or more Colleges and Departments onto a single program page.</p>
				<form name="add_program" id="add_program_form" method="post" action="add.php" enctype="multipart/form-data">
					<input type="hidden" name="content_id" id="content_id" value="">
					<input type="hidden" name="real_userid" value="">
					<div class="form-group">
						<label for="select_added_college">College: </label>
						<select class="form-control" id="select_added_college" name="select_added_college">
							<option value="">Choose a College</option>
						<?php
							foreach ($colleges as $college){
								echo '<option value="'.$college['id'].'">'.$college['college'].'</option>'.PHP_EOL;
							}
						?>
						</select>
					</div>
					<div class="form-group">
						<label for="select_added_department">Department: </label>
						<select class="form-control" id="select_added_department" name="select_added_department">
							<option value="">Choose a College first</option>
						</select>
				  </div>
				  
						<button type="submit" class="btn btn-wsu">Add</button>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<!-- Button trigger modal -->
<!-- Modal -->
<div class="modal fade" id="similar" tabindex="-1" role="dialog" aria-labelledby="similar_programs" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="similar_programs">Similar Programs</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true"> &times;</span></button>
			</div>
			<div class="modal-body">
				<form name="similar_programs_form" id="similar_programs_form" method="post" action="similar_programs.php" enctype="multipart/form-data">
					<input type="hidden" name="program_id" id="program_id" value="">
					<input type="hidden" name="real_userid" value="">
					<div class="form-group">
						<label for="select_similar_college">College: </label>
						<select class="form-control" id="select_similar_college" name="select_similar_college">
							<option value="">Choose a College</option>
						<?php
							foreach ($colleges as $college){
								echo '<option value="'.$college['id'].'">'.$college['college'].'</option>'.PHP_EOL;
							}
						?>
						</select>
					</div>
					<div class="form-group">
						<label for="select_similar_department">Department: </label>
						<select class="form-control" id="select_similar_department" name="select_similar_department">
							<option value="">Choose a College first</option>
						</select>
					</div>
					<div class="form-group">
						<label for="select_added_program">Program: </label>
						<select class="form-control" id="select_added_program" name="select_added_program">
							<option value="">Choose a Department first</option>
						</select>
					</div>

					<div class="form-group">
						<p>&nbsp;</p>
						<p><button type="submit" class="btn btn-wsu similar_button"  id="similar_programs_form_add" value="add">Add Similar</button></p>
					<ul class="list-group" id="this-similar-programs">
					</ul>
					</div>
						<hr>
				<div class="form-group row">
						    <div class="col">
<button type="submit" class="btn btn-wsu similar_button" id="similar_programs_form_clear" value="clear"><i class="fa fa-minus-circle fa-fw" aria-hidden="true"></i><span class="d-none d-lg-inline-block">&nbsp; Clear Main Similar</span></button></div>
 						    <div class="col ">
<button type="submit" class="btn btn-wsu float-right similar_button" id="similar_programs_form_clear_all" value="clear_all"><i class="fa fa-minus-circle fa-fw" aria-hidden="true"></i><span class="d-none d-lg-inline-block">&nbsp; Clear ALL Similar</span></button></col>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<!-- Button trigger modal -->
<!-- Modal -->
<div class="modal fade" id="clone" tabindex="-1" role="dialog" aria-labelledby="clone_program" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="clone_program">Clone a Program</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true"> &times;</span></button>
			</div>
			<div class="modal-body">
				<form name="clone_form" id="clone_program_form" method="post" enctype="multipart/form-data">
					<input type="hidden" name="program_id" id="program_id" value="">
					<input type="hidden" name="action" value="clone">
					<input type="hidden" name="real_userid" value="">
					<div class="form-group">
						<label for="select_a_program">Select a Program to Clone</label>
						<select class="form-control" id="created_programs" name="created_programs">
						<?php
							ksort($completed_programs_option_select);
							foreach ($completed_programs_option_select as $option){
								echo $option;
							}
						?>
						</select>
					  <button type="submit" class="btn btn-wsu" style="margin-top:20px; margin-bottom: 20px;">Clone</button>
						
				  </div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>


<!-- Button trigger modal -->
<!-- Modal -->
<div class="modal fade" id="create_program" tabindex="-1" role="dialog" aria-labelledby="create_programs" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="create_programs">Create a Program</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true"> &times;</span></button>
			</div>
			<div class="modal-body">
				<form name="create_program_form" id="create_program_form" method="post" action="similar_programs.php" enctype="multipart/form-data">
					<input type="hidden" name="real_userid" value="">
<div class="form-row align-items-center">
					<div class="col-md-12 mb-3">
					<div class="form-group">
						<label for="academic_program">Program: </label>
						<input type="text" class="form-control" id="academic_program" name="academic_program" placeholder="Program" required>
					</div>
					</div>
					<div class="col-md-6 mb-3">
					<div class="form-group">
						<label for="program_type">Program Type: </label>
						<input type="text" class="form-control" id="program_type" name="program_type" placeholder="e.g.: BA, MFA, Certificate ..." required>
					</div>
					</div>
					<div class="col-md-6 mb-3">
					<div class="form-group">
						<label for="program_type">Simple Program Type: </label>
						<input type="text" class="form-control" id="program_simple_type" name="program_simple_type" placeholder="e.g.: Major, Minor ..." required>
					</div>
					</div>
</div>

<div class="form-row align-items-center">
					<div class="col-auto">
					<div class="form-group">
						<label for="select_college">College: </label>
						<select class="form-control" id="select_college" name="select_college" required>
							<option>Choose a College</option>
						<?php
							foreach ($colleges as $college){
								echo '<option value="'.$college['id'].'">'.$college['college'].'</option>'.PHP_EOL;
							}
						?>
						</select>
					</div>
					</div>
</div>
<div class="form-row align-items-center">
					<div class="col-auto">
					<div class="form-group">
						<label for="select_department">Department: </label>
						<select class="form-control" id="select_department" name="select_department" required>
							<option>Choose a College first</option>
						</select>
					</div>
					</div>
</div>
<div class="form-row align-items-center">

					<div class="col-auto">
					  <div class="form-check mb-2">
						<input class="form-check-input" type="checkbox" id="online_learning">
						<label for="online_learning">Online</label>
					  </div>
					</div>
					<div class="col-auto">
					  <div class="form-check mb-2">
						<input class="form-check-input" type="checkbox" id="graduate">
						<label for="graduate">Graduate</label>
					  </div>
					</div>
</div>
						<p>&nbsp;</p>
						<p><button type="submit" class="btn btn-wsu">Create</button></p>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<div id="popover_container" style="display:none;"></div>



<?php
//data-popover-content="#curriculum_'.$program_content['id'].'" data-popover-id="'.$program_content['id'].'" data-popover-section="curriculum"

$footer_scripts[]='<script>

$(document).ready(
	
	function(){
	
	$("[data-toggle=popover]").popover({
		html : true,
		content: function() {
			$("#popover_container").empty();
			var program_id = $(this).attr("data-popover-id");
			var section = $(this).attr("data-popover-section");
			$.ajax({
				async: false,
				url: "https://wsu-migration.com/programs_popover.php?content="+section+"&id="+program_id ,
				dataType: "html",

				success: function(result){
				$( result ).appendTo( "#popover_container" );
				//$("#popover_container").html(result);
				}
			});
			return $("#popover_container").children(".popover-body").html();
		},
		title: function() {
		return $("#popover_container").children(".popover-heading").html();
		},
	})

	$(".popover-dismiss").popover({
		trigger: "focus",
	})

	$("#add").on("show.bs.modal", function (event) {
		var button = $(event.relatedTarget) ;
		var content_id = button.data("content_id") ;
		var program_name = button.data("program_name") ;

		var modal = $(this) ;
		modal.find(".modal-title").text("Add a Department to " + program_name) ;
		modal.find("#content_id").val(content_id) ;
	});

	$("#similar").on("show.bs.modal", function (event) {
		var button = $(event.relatedTarget)
		var program_id = button.data("program_id")
		var program_name = button.data("program_name")
		var modal = $(this)

		$.ajax({
			url: "update_similar_programs.php",
			type: "post",
			data: { program_id:program_id},
			success:function(response){
				$("#this-similar-programs").html(response); 
			}
		});
		modal.find(".modal-title").text("Select a program similar to " + program_name)
		modal.find("#program_id").val(program_id)
	});

	$("#clone").on("show.bs.modal", function (event) {
		var button = $(event.relatedTarget) 
		var program_id = button.data("program_id") 
		var program_name = button.data("program_name") 
		var modal = $(this)
		modal.find(".modal-title").text("Clone " + program_name + " from an existing page.")
		modal.find("#program_id").val(program_id)
	});

    $("#similar_programs_form").click(function(event) {
    	window.this_button = $(event.target).closest("button").val(); //set globally
 		console.log("that tickled " + this_button);
    });

	$("#similar_programs_form").submit( function(e) {
    	e.preventDefault();

	    var url = "update_similar_programs.php";
    	var data = $("#similar_programs_form").serialize();
		data = data + "&button=" + this_button;
 		console.log(data);
 		console.log(this_button);
    
	    $.ajax({
           type: "POST",
           url: url,
           data: data, 
		   success:function(response){
				$("#this-similar-programs").html(response); 
		   }
         });
	});

	
	$("#create_program_form").submit(function(e) {
		var url = "create_program.php";
		$.ajax({
			   type: "POST",
			   url: url,
			   data: $("#create_program_form").serialize(), 
			   success:function(response){
					$("#create_program").html(response); 
			   }
			 });
		e.preventDefault();
	});

	$("#add_program_form").submit(function(e) {
		var url = "add_program.php";
		$.ajax({
			   type: "POST",
			   url: url,
			   data: $("#add_program_form").serialize(), 
			   success:function(response){
					$("#add_program_form").html(response); 
			   }
			 });
		e.preventDefault();
	});

	$("#select_added_college").change(function() {
		$("#select_added_department").load("get_departments.php?choice=" + $("#select_added_college").val());
	});

	$("#select_similar_college").change(function() {
		$("#select_similar_department").load("get_departments.php?choice=" + $("#select_similar_college").val());
	});
	$("#select_similar_department").change(function() {
		$("#select_added_program").load("get_programs.php?choice=" + $("#select_similar_department").val());
	});

	$("#select_college").change(function() {
		$("#select_department").load("get_departments.php?choice=" + $("#select_college").val());
	});

 
	// Add Class
	$(".edit").click(function(){
		$(this).addClass("editMode");
	});

	// Save data
	$(".edit").focusout(function(){
		$(this).removeClass("editMode");
		var field_name = this.id;
		var value = $(this).html();

		$.ajax({
			url: "update_program_table.php",
			type: "post",
			data: { field:field_name, value:value }, success:function(response){ $(this).html(response); }
		});
	});

	$("#sidebar-container").removeAttr("class");
	$("#sidebar-container").attr("class", "d-none");
	
	$(".program_select").change(function(){
			var field_name = this.id;
			var value = $(this).val();
 			console.log("Field " + field_name);
 			console.log("Value " + value);

			$.ajax({
				url: "update_program_table.php",
				type: "post",
				data: { field:field_name, value:value }, success:function(response){ $(this).html(response); }
			});
		});
	
});	
</script>';

require_once('admin_footer.php');

?>