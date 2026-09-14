<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/../config/functions.php');
require_once('majors_admin_functions.php');
?>

<!DOCTYPE HTML><html lang="en">
<head>
<!-- OU Search Ignore Start Here -->
<?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/headcode.inc"); ?>
<link href="/_resources/js/DataTables/datatables.min.css" rel="stylesheet">
<script src="/_resources/js/DataTables/datatables.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.3/jquery-ui.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.css" />
<?php
/* 


#ffc217 color-primary (bright yellow)
#f3ad1c color-secondary (dark yellow)
#903b41 color-tertiary (dark red)
#bb4935 color-quaternary (medium red)
#c95e44 color-quinary (light red)
#72a6bf color-senary (light blue)
#879848 color-septenary (light blue)
#1f1f1f color-shade-darkest
#3b3b3b color-shade-darker
# color-shade-dark
#989898 color-shade
#c9c7c4 color-shade-light
#e1e0df color-shade-lighter
#f2f2f2 color-shade-lightest
*/

/* Sidebar links */
?>
<!-- OU Search Ignore End Here -->
	<title>Majors Admin</title>
	<style>
.sidebar {
  margin: 0;
  padding: 0;
  width: 200px;
  background-color: #1f1f1f;
  position: fixed;
  height: 100%;
  overflow: auto;
  color: #fff;
}
.sidebar ul, .sidebar li {
	margin: 0;
}

.sidebar li
{
	display: block;
	color: #e1e0df;
	padding: 16px;
	text-decoration: none;
	font-weight: 700;
	margin 0: ;
	border-bottom: thin #c9c7c4 solid;
}

.sidebar li a {
  color: #e1e0df;
  text-decoration: none;
  font-weight: 700;
  margin 0;
  border: none;
  display: block;
  width: 100%;
  height: 100%;
}

.sidebar [type='text'], .sidebar [type='password'], .sidebar [type='date'], .sidebar [type='datetime'], .sidebar [type='datetime-local'], .sidebar [type='month'], .sidebar [type='week'], .sidebar [type='email'], .sidebar [type='number'], .sidebar [type='search'], .sidebar [type='tel'], .sidebar [type='time'], .sidebar [type='url'], .sidebar [type='color'], .sidebar [type='range'], .sidebar textarea, .sidebar select{
	margin: 0;
	padding: 0.5rem;
}

/* Active/current link */
.sidebar li.active, .sidebar li.active a {
  background-color: #ffc217;
  color: #000;
}

/* Links on mouse-over */
.sidebar li:hover:not(.active), .sidebar li:hover:not(.active) a {
  background-color: #555;
  color: white;
}

.sidebar > ul > li > span.inactive {
  color: #3b3b3b;
}

/* Page content. The value of the margin-left property should match the value of the sidebar's width property */
div.content {
  margin-left: 200px;
  padding: 1px 16px;
  height: 1000px;
}

/* On screens that are less than 700px wide, make the sidebar into a topbar */
@media screen and (max-width: 700px) {
  .sidebar {
    width: 100%;
    height: auto;
    position: relative;
  }
  .sidebar li {float: left;}
  div.content {margin-left: 0;}
}

/* On screens that are less than 400px, display the bar vertically, instead of horizontally */
@media screen and (max-width: 400px) {
  .sidebar li {
    text-align: center;
    float: none;
  }
}


.dt-search {
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    align-content: center;
    align-items: baseline;
    justify-content: center;
}

div.dt-buttons {
    margin-right: 1rem;
}

.edit-button {
	background-color: #04AA6D;
	color: white;
	
}
.edit-button:hover {
    background-color: #059862 !important;
    color: white !important;
}
.blocker {
	z-index: 10000;

}

.modal a.close-modal {
    border-bottom: none;
}
	</style>
</head>
   <body>
<!-- The sidebar -->
<?php
$encoded_search = null;
if (! empty($search)){
	$encoded_search = urlencode($search);
}

$sidebar_menu = array(
	'Home' => $_SERVER['PHP_SELF'],
	'New' => $_SERVER['PHP_SELF'].'?action=new',
	'Edit' => $_SERVER['PHP_SELF'].'?action=edit&amp;id='.(int)$id,
	'Copy' => $_SERVER['PHP_SELF'].'?action=copy&amp;id='.(int)$id,
	'Search' => $_SERVER['PHP_SELF'].'?action=search',
	'Delete' => $_SERVER['PHP_SELF'].'?action=delete&amp;id='.(int)$id,
);

//BEGIN SIDEBAR
?>
<div class="sidebar">
<ul>
<?php
//strip out index.php so it won't mater if the user was on index sepcifically or if they were on the directory
$request_uri = str_replace( 'index.php', '', $_SERVER['REQUEST_URI']);
foreach ($sidebar_menu as $title => $url){
	$active = null;
	if (
		($url == $_SERVER['REQUEST_URI']) OR 
		($url == $request_uri) OR 
		(strtolower($title) == $filters['action']) 
	){
		$active = ' class="active"';
	}
	echo '<li'.$active.'>';
	if ($title != 'Search'){
		if ((($title == 'Copy') OR ($title == 'Edit') OR ($title == 'Delete')) AND empty($id)){
			echo '<span class="inactive">'.$title.'</span>';
		}else{
			echo '<a href="'.$url.'">'.$title.'</a>';
		}
	}else{
		echo '<input type="text" id="majorSearch" onkeyup="myFunction()" placeholder="Search..." title="Type in a major"></li>';
	}
	echo '<!-- url: '.$url.'  request_uri: '.$_SERVER['REQUEST_URI'].'-->';
	echo '</li>';
}
?>
</ul>

<?php // INFO Box
if (! empty($id)){
	echo get_info($id);
}
//End Info Box
?>
</div>
<?php 
	// END SIDEBAR
 ?>
<!-- Page content -->
<div class="content">
   <!-- OU Search Ignore Start Here --><?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/header.inc"); ?><!-- OU Search Ignore End Here -->
<main class="main main--slab">
<?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/alert.php"); ?>

<div id='admin_content' name='admin_content'>
<?php 

if (! empty($id) AND ($action != 'edit')){
	echo display_major($id);
}elseif(! empty($id) AND ($action == 'edit')){

	echo display_editable_major($id);

}elseif($action == 'new'){
	//if we are making a new one, we will want to start with a form to set up the information
	
	echo display_new_degree_form();


}else{
	//if we don't have a single ID to display, create the interface
	echo get_filters_section();
?>
<div class="main-wrapper" style="flex-direction: column;">
	<div id="search_results" class="divided-list" style="width:100%;">
<?php
	echo get_admin_table();
?>
	</div id="search_results">
</div class="main-wrapper">
<?php

} //end empty id
?>
</div id='admin_content'>
</main>
<!-- OU Search Ignore Start Here -->
<?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/footer.inc"); ?>


</div class="content">


<?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/footcode.inc"); ?>
<?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/analytics.inc"); ?>
<script src="/_resources/js/degree-search.js">
</script>
<script>
$(document).ready(function() {
	$('#program_pages_info').DataTable({
        dom: "Bfrtip",
        "order": [ [ 1, "asc" ], [ 2, "asc" ], [5, "asc"], [ 3, "asc" ] ], //college, graduate, department, program
		"autoWidth": false,
    	"colReorder": true,
		"pageLength": 25, 
		"aoColumnDefs": [ { "bSortable": false, "aTargets": [ 0 ] } ],
		"stateSave": true,
        buttons: [
            "colvis"
        ]
	});
} );

</script>
<script>
function myFunction() {
	// Declare variables
	var input, filter, ul, li, a, i;
	input = document.getElementById("mySearch");
	filter = input.value.toUpperCase();
	ul = document.getElementById("myMenu");
	li = ul.getElementsByTagName("li");
	
	// Loop through all list items, and hide those who don't match the search query
	for (i = 0; i < li.length; i++) {
		a = li[i].getElementsByTagName("a")[0];
		if (a.innerHTML.toUpperCase().indexOf(filter) > -1) {
			li[i].style.display = "";
		} else {
			li[i].style.display = "none";
		}
	}
}
</script>

<!-- OU Search Ignore End Here -->
</body>
</html>