<?php
$title = 'Programs';
require_once('common.php');

function short_string($string, $length=20){
	$string = trim($string);
	if (strlen($string) > ($length)){
		$string = substr($string,0,$length-3).'...';
	}
	return $string;
}

//remove a program and clean up the database
function  remove_program($program_id){ 
	//program_id is the `strat_comm_academic_programs`.`id`
	//strat_comm_department_content_key
	//strat_comm_program_content_key
	//strat_comm_programs_content
	//strat_comm_similar_programs

	//delete related department content key
	$sql = "DELETE FROM `strat_comm_department_content_key` WHERE `program_content_id` = (SELECT `program_content_id` FROM `strat_comm_program_content_key` WHERE `academic_program_id` = '$program_id');";
	generic_mysqli_query($sql);


	//delete related program content key
	$sql = "DELETE FROM `strat_comm_program_content_key` WHERE `academic_program_id` = '$program_id';";
	generic_mysqli_query($sql);

	//delete related program content
	$sql = "DELETE FROM `strat_comm_programs_content` WHERE `academic_program_id` = '$program_id';";
	generic_mysqli_query($sql);


	//delete related program links
	$sql = "DELETE FROM `strat_comm_similar_programs` WHERE (`main_academic_program_id` = '$program_id' OR `similar_academic_program_id` = '$program_id');";
	generic_mysqli_query($sql);

	//delete related program links

	//delete actual program
	$sql = "DELETE FROM `strat_comm_academic_programs` WHERE `id` = '$program_id'";
	generic_mysqli_query($sql);
}

//if we have action=create, create a program page and get an id for it
if ( isset($_POST['action']) AND isset($_POST['program_id']) ){

	$program_id = $mysqli->real_escape_string($_POST['program_id']);
	
	if  ($_POST['action'] == 'delete'){

		$program_content_id = remove_program($program_id);

	}
}

$header_items[] = '<link rel="stylesheet" type="text/css" href="/javascript/DataTables/datatables.min.css"/>';

$header_items[] = '<style>
	.popover_p:not(:first-child){
		margin-top: 10px;
		padding-top: 10px;
		border-top: 1px rgba(0,0,0, 0.5) solid;
	}
</style>';

$footer_scripts[] = '<script type="text/javascript" src="javascript/DataTables/datatables.min.js"></script>
<script type="text/javascript" src="javascript/Bootstrap-Confirmation-master/src/popover.js"></script>';

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

$sql = "SELECT * from `strat_comm_colleges` WHERE 1;";
$colleges = mysqli_multiple_rows($sql);

require_once('head.php');
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

$sql = "SELECT `strat_comm_academic_programs`.`academic_program`, `strat_comm_academic_programs`.`college`, `strat_comm_colleges`.`code` as `code`, `strat_comm_program_content_key`.`program_content_id` 
	FROM `strat_comm_academic_programs`, `strat_comm_colleges`, `strat_comm_program_content_key` 
	WHERE `strat_comm_academic_programs`.`college`=`strat_comm_colleges`.`college` AND `strat_comm_program_content_key`.`academic_program_id` = `strat_comm_academic_programs`.`id`
	ORDER BY `strat_comm_colleges`.`code`, `strat_comm_academic_programs`.`graduate`, `strat_comm_academic_programs`.`department`, `strat_comm_academic_programs`.`academic_program`;";
$active_program_content = mysqli_multiple_rows($sql);

$completed_programs_option_select = array(0 => '<option value="">Select One</option>');


foreach ($active_program_content as $active_content){
	$completed_programs_option_select[$active_content['program_content_id']] = '<option value="'.$active_content['program_content_id'].'">'.$active_content['academic_program'].' ('.substr($active_content['college'], 0, 14).')</option>'.PHP_EOL;
}

$sql = "SELECT DISTINCT `strat_comm_academic_programs`.*, `strat_comm_colleges`.`code` as `code` 
	FROM `strat_comm_academic_programs`, `strat_comm_colleges`, `strat_comm_programs_content` 
	WHERE `strat_comm_academic_programs`.`college`=`strat_comm_colleges`.`college`
	ORDER BY `strat_comm_colleges`.`code`, `strat_comm_academic_programs`.`graduate`, `strat_comm_academic_programs`.`department`, `strat_comm_academic_programs`.`academic_program`;";
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
  	<th data-priority="2">Delete</th>
  	<th data-priority="1">Academic Program</th>
  	<th data-priority="6">College</th>
  	<th data-priority="7">Department</th>
  	<th data-priority="10">Degree</th>
  	<th data-priority="11">Readable Degree</th>
  	<th>Graduate</th>
  	<th>Online</th>
	<th data-priority="3">Note</th>
	<th data-priority="5">Last Update</th>
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
		$sql = "SELECT `program_content_id` FROM `strat_comm_program_content_key` WHERE `academic_program_id` = ".$academic_program['id'].";";
		$row = mysqli_single_row($sql);
		if ($row['program_content_id']){
			$academic_program['content_id'] = $row['program_content_id'];
			$sql = "SELECT * FROM `strat_comm_programs_content` WHERE `strat_comm_programs_content`.`id` = '".$academic_program['content_id']."';";
			//overwrite the empty array
			$program_content = mysqli_single_row($sql);
		}

		
		
		echo '<tr>
		<td style="white-space: nowrap;">				<form name="create_form" id="create_form" method="post" enctype="multipart/form-data">
					<input type="hidden" name="action" id="action" value="delete">
					<input type="hidden" name="program_id" id="program_id" value="'.$academic_program['id'].'">
					<input type="hidden" name="real_userid" value="'.$_SESSION['real_userinfo']['id'].'">
					<button type="submit" class="btn btn-wsu btn-sm" title="Delete this page" data-toggle="confirmation"><i class="fa fa-trash" aria-hidden="true"></i><span class="d-none d-lg-inline-block">&nbsp; Delete</span></button>
				</form>
    	</td>
    	<td style="white-space: nowrap;">
    		<div id="academic_program_'.$academic_program['id'].'">'.$academic_program['academic_program'].'</div>
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
    		<div id="program_simple_type_'.$academic_program['id'].'">'.$academic_program['program_simple_type'].'</div>
		</td>
';
		// ************** Graduate **************** //
		if ($academic_program['graduate'] == 1){
			$academic_program['graduate'] = '<span style="display:none">1</span>G';
		}else{
			$academic_program['graduate'] = '<span style="display:none">0</span>UG';
		}
		echo '    	<td>
    		'.$academic_program['graduate'].'
		</td>
    	';
		// ************** Online **************** //
		if ($academic_program['online_learning'] == 1){
			$academic_program['online_learning'] = '<span style="display:none">1</span>Yes';
		}else{
			$academic_program['online_learning'] = '<span style="display:none">0</span>No';
		}
		echo '    	<td>
    		'.$academic_program['online_learning'].'
		</td>
    	';

		// ************** Note **************** //
			if (! $academic_program['note']){
				$academic_program['note'] = '&nbsp;';
			}
			echo '<td><div id="note_'.$academic_program['id'].'">'.$academic_program['note'].'</div></td>';




		// ************** Timestamp **************** //

			if (($program_content['timestamp'] != NULL)){
				//echo str_replace(' ', '&nbsp;', date("F j, Y, g:i a", strtotime($row['lastmodified'])) ) ;
				$lastmodified = new DateTime($program_content['timestamp']);
				echo '<td style="text-align: center;"><span style="display:none;">'.str_replace(' ', '&nbsp;',$program_content['timestamp']).'</span><span style="white-space: nowrap;">'.ago($lastmodified).'</span></td>';
			}else{
				echo '<td style="text-align: center;"></td>';
			}
			echo PHP_EOL.'</tr>'.PHP_EOL;

	}
}

?>

</tbody>
</table>


</div><!-- /programs-table -->


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



<?php
//data-popover-content="#curriculum_'.$program_content['id'].'" data-popover-id="'.$program_content['id'].'" data-popover-section="curriculum"

$footer_scripts[]='<script>
$(document).ready(

	$(function(){
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
	})


);

$(".popover-dismiss").popover({
  trigger: "focus",
})
</script>
';

$footer_scripts[]="<script>
\$('#add').on('show.bs.modal', function (event) {
  var button = \$(event.relatedTarget) // Button that triggered the modal
  var content_id = button.data('content_id') // Extract info from data-* attributes
  var program_name = button.data('program_name') // Extract info from data-* attributes
  // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
  // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
  var modal = \$(this)
  modal.find('.modal-title').text('Add a Department to ' + program_name)
  modal.find('#content_id').val(content_id)
})
</script>
";

$footer_scripts[]="<script>
\$('#similar').on('show.bs.modal', function (event) {
  var button = \$(event.relatedTarget) // Button that triggered the modal
  var program_id = button.data('program_id') // Extract info from data-* attributes
  var program_name = button.data('program_name') // Extract info from data-* attributes
  var modal = \$(this)
  // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).

  \$.ajax({
   url: 'update_similar_programs.php',
   type: 'post',
   data: { program_id:program_id},
   success:function(response){
    	$('#this-similar-programs').html(response); 
   }
  });

  // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
  modal.find('.modal-title').text('Select a program similar to ' + program_name)
  modal.find('#program_id').val(program_id)
})
</script>
";

$footer_scripts[]="<script>
\$('#clone').on('show.bs.modal', function (event) {
  var button = \$(event.relatedTarget) // Button that triggered the modal
  var program_id = button.data('program_id') // Extract info from data-* attributes
  var program_name = button.data('program_name') // Extract info from data-* attributes
  // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
  // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
  var modal = \$(this)
  modal.find('.modal-title').text('Clone ' + program_name + ' from an existing page.')
  modal.find('#program_id').val(program_id)
})
</script>
";

$footer_scripts[]='<script>
$("#similar_programs_form").submit(function(e) {
    var url = "update_similar_programs.php";
    $.ajax({
           type: "POST",
           url: url,
           data: $("#similar_programs_form").serialize(), 
		   success:function(response){
				$("#this-similar-programs").html(response); 
		   }
         });
    e.preventDefault();
});
</script>
';

$footer_scripts[]='<script>
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
</script>
';


$footer_scripts[]='<script>
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
</script>
';


$footer_scripts[]='<script>
$(document).ready(function(){
	$("#select_added_college").change(function() {
		$("#select_added_department").load("get_departments.php?choice=" + $("#select_added_college").val());
	});
});
</script>';

$footer_scripts[]='<script>
$(document).ready(function(){
	$("#select_similar_college").change(function() {
		$("#select_similar_department").load("get_departments.php?choice=" + $("#select_similar_college").val());
	});
	$("#select_similar_department").change(function() {
		$("#select_added_program").load("get_programs.php?choice=" + $("#select_similar_department").val());
	});
});
</script>';


$footer_scripts[]='<script>
$(document).ready(function(){
	$("#select_college").change(function() {
		$("#select_department").load("get_departments.php?choice=" + $("#select_college").val());
	});
});
</script>';

$footer_scripts[]='<script>
$(document).ready(function(){
	$("#select_college").change(function() {
		$("#select_department").load("get_departments.php?choice=" + $("#select_college").val());
	});
});
</script>';



$footer_scripts[]='<script>
$(document).ready(function(){
 
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
   data: { field:field_name, value:value},
   success:function(response){
    	$(this).html(response); 
   }
  });
 
 });

});

</script>
';
$footer_scripts[]='<script>
$(document).ready(function(){
	$("#sidebar-container").removeAttr("class");
	$("#sidebar-container").attr("class", "d-none");
});	
</script>';

require_once('final_pagination.php');

