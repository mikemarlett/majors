<?php
error_reporting(E_ALL);
/* /////////////
TO DELETE A Degree Map

https://www-test.wichita.edu/academics/majors/degree_maps/admin/maps.php?degree_map_id=922&delete=1&secret=squirrel

//////////  */ 



require_once('map_edit_functions.php');
if (! empty($_REQUEST['newMap']) && ($_REQUEST['newMap'] == 'New')){ 
	$_REQUEST['degree_map_id'] = null;
}


$degree_map_id = null;
if (! empty($_REQUEST['degree_map_id'])){
	$degree_map_id = (int)$_REQUEST['degree_map_id'];
}
if (! empty($_REQUEST['edit']) && ($_REQUEST['edit'] == '1')){
	$_REQUEST['editMap'] = 'Edit';
}

if (! empty($_REQUEST['delete']) && ($_REQUEST['delete'] == '1') && ! empty($degree_map_id) && ! empty($_REQUEST['secret']) && ($_REQUEST['secret'] == 'squirrel')){
	delete_degree_map($degree_map_id);
	$degree_map_id = null;
}

$maps = '';

if (! empty($degree_map_id)){
	if (! empty($_REQUEST['editMap']) && ($_REQUEST['editMap'] == 'Edit')){
		$maps .= display_edit_degree_map($degree_map_id);
	}elseif (! empty($_REQUEST['cloneMap']) && ($_REQUEST['cloneMap'] == 'Clone')){
		$new_map_id = clone_degree_map($degree_map_id);
		header("Location: maps.php?degree_map_id={$new_map_id}&editMap=Edit"); //redirect to the new map
		exit;
	}elseif (! empty($_REQUEST['newMap']) && ($_REQUEST['newMap'] == 'New')){
		$degree_map = build_empty_degree_map();
		$maps .= build_map_form($degree_map);
	}elseif (! empty($_REQUEST['delete']) && ($_REQUEST['delete'] == '1')){
		$delte_title = get_map_title($degree_map_id);
		if (! empty($delte_title)){
			preg_match('/\(.*?delete.*?\)/', $delte_title, $delete_me);
			if (! empty($delete_me)){
				delete_degree_map($degree_map_id);
				$degree_map_id = null;
				header("Location: maps.php"); //redirect to the new map
			}
		}
	}else{
		$maps .= display_degree_map($degree_map_id);
	}
}elseif (! empty($_REQUEST['newMap']) && ($_REQUEST['newMap'] == 'New')){
	$degree_map = build_empty_degree_map();
	$maps .= build_map_form($degree_map);
}elseif (!empty ($order) && $order == 'college'){
	$maps .= maps_by_college();
}else{
	$maps .= maps_by_alpha();
}

$title = "Degree Maps";
$description = "Degree Maps to guide students through degrees at Wichita State";
if (!isset($header_items)){
	$header_items = array();
}
if (!isset($footer_items)){
	$footer_items = array();
}

$local_javascript = [
	'javascript/jquery-ui-1.14.1/jquery-ui.min.js',
	'javascript/map_edit_functions.js'];
$local_css = [
	'javascript/jquery-ui-1.14.1/jquery-ui.min.css',
	'../maps_style.css',
	'css/map_edit_functions.css'];
	
foreach ($local_javascript as $file){
	$filepath = getcwd() .'/'. $file;
	if (file_exists($filepath)){
		$filemtime = filemtime($filepath);
		$footer_items[] = '<script src="'.$file.'?tm='.$filemtime.'" defer></script>';
	}
}
foreach ($local_css as $file){
	$filepath = getcwd() .'/'. $file;
	if (file_exists($filepath)){
		$filemtime = filemtime($filepath);
		$header_items[] =  '<link rel="stylesheet" href="'.$file.'?tm='.$filemtime.'" />'.PHP_EOL;
	}
}
$header_items[] = '<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css" />';
$header_items[] = '<link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet">';

$header_items[] = '<style>
  .drag-handle {
    cursor: grab;
    text-align: center;
  }

  .drag-handle:active {
    cursor: grabbing;
  }

  .fa-grip-lines {
    font-size: 1.5rem;
    color: #666;
  }

  .table--zebra-stripe tbody tr:nth-child(odd) {
    background-color: #f9f9f9;
  }

  .table--zebra-stripe tbody tr:hover {
    background-color: #eaeaea;
  }
  
</style>';


$footer_items[] = '<script src="https://unpkg.com/@popperjs/core@2"></script>';
$footer_items[] = '<script src="https://unpkg.com/tippy.js@6"></script>';
$footer_items[] = '<script src="/_resources/js/degree-maps-search.js"></script>';
$footer_items[] = '<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>';
$footer_items[] = `<script>
  document.addEventListener('DOMContentLoaded', function() {
    tippy('.footnote-link', {
      allowHTML: true,
      placement: 'bottom',
      trigger: 'mouseenter focus'
    });
  });
</script>
`;
$footer_items[] = '<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>';


$footer_items[] = `<script>
  document.addEventListener('DOMContentLoaded', function () {
    const tableBody = document.getElementById('sortableCourses');

    const sortable = new Sortable(tableBody, {
      animation: 150,
      handle: '.drag-handle',
      onEnd: function () {
        const updatedOrder = [...tableBody.querySelectorAll('tr')].map((row, index) => ({
          course_id: row.getAttribute('data-course-id'),
          order: index + 1
        }));

        // Send the updated order to the server
        fetch('/degree_maps/admin/ajax/save_course_order.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(updatedOrder)
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert('Order saved successfully!');
          } else {
            alert('Failed to save order. Please try again.');
          }
        })
        .catch(() => alert('Error communicating with the server.'));
      }
    });
  });
</script>`;






include('admin_head.php'); 
?>
<header class="page-header noprint">
	<div class="page-header__bar">
		<div class="page-header__page-title">
			<h1 class="headline-group"><span class="head">Edit Degree Maps</span></h1>
		</div>
	</div>
</header>


<section class="section-wrap section-wrap--shade-light section-wrap--short noprint">
	<div class="search-filters ">
		<form id="search_form" class="search-filters__search" method="post" name="degree_maps_search">
			<label class="show-for-sr" for="searchList">Search</label> 
			<input id="searchList" class="" name="searchList" type="text" placeholder="Search Degree Maps">
			<button class="" type="submit" value="Search"> Search </button>
		</form>
		<form id="search_academic_year" class="search-filters__select" name="degree_maps_search">
		<?php  //default to latest year in database if it isn't set already
		$academic_years = get_years();
		if (empty ($academic_year)){
			$academic_year = get_year();
		}
		?>
			<label class="show-for-sr" for="selected_year">Select Catalog Year</label>
<?php
	$options = '';
	foreach ($academic_years as $this_year){
		
		$selected = null;
		if ($this_year == $academic_year){
			$selected = ' selected';
		}
		$display_year = ($this_year-1)." - ".$this_year;
		// $display_year = $this_year;
		$options .= '				<option value="'.$this_year.'"'.$selected.'>'.$display_year.'</option>'.PHP_EOL;
	}
?>
			<select id="selected_year" name="selected_year">
				<optgroup label="Catalog Year"> 
			<?php echo $options; ?>
				</optgroup> 
			</select>
		</form>
		<form id="search_select" class="search-filters__select" name="degree_maps_search">
			<label class="show-for-sr" for="selected_college">Select search fields</label>
			<select id="selected_college" name="selected_college">
				<optgroup label="College"> 
				<option value="all">All Colleges</option>
<?php
echo get_colleges();
?>
				</optgroup>
			</select>
		</form>
	</div>
</section>
<section class="section-wrap section-wrap--arrows-dark section-wrap--short noprint">
	<div class="landing-panel landing-panel--quicklink">
		<h2 class="landing-panel__headline">Form Actions</h2>
		<form id="new_map" class="button_collection landing-panel__buttons" method="post" name="degree_maps_new" action="maps.php">
			<input type="hidden" name="degree_map_id" value="<?php echo $degree_map_id; ?>">
			<button id="newMap" class="button button--accent" style="margin-bottom: 1rem;" name="newMap" type="submit" value="New">New Map</button>
			<?php if (!empty($degree_map_id)) {
			$map = get_map_by_id($degree_map_id);
			$currentYear = getCurrentAcademicYear();
			?>
			<?php if ($map['academic_year'] > $currentYear && empty($_REQUEST['editMap'])) { ?>
			<!-- Future maps: allow editing and approving -->
			<button id="editMap" class="button button--accent" style="margin-bottom: 1rem;" name="editMap" type="submit" value="Edit">Edit Map</button>
			<?php
			// 		hold off on this for now	<button id="approveMap" class="button button--accent" style="margin-bottom: 1rem;" name="approveMap" type="submit" value="Approve">Approve</button>

			} elseif(! empty($_REQUEST['editMap']) ) { ?>
			<!-- Past or current maps: view only -->
			<button class="button button--accent" style="margin-bottom: 1rem;" name="viewMap" type="submit" value="View">View Map</button>
			<?php }else{  ?>
			<button id="cloneMap" class="button button--accent" style="margin-bottom: 1rem;" name="cloneMap" type="submit" value="Clone">Clone Map</button>
			<?php
			} ?>
			<?php } ?>


		</form>
	</div>
</section>

<div class="main-wrapper" style="flex-direction: column;">

<div id="search_results" class="divided-list" style="width:100%;">
	<?php 
if (! empty($maps)){
	echo $maps;
}else{
	echo '<h2>No results</h2>';
}


////// END LISTING /////////////
	
	?>
	</div>
</div>

<!-- Hidden modal for new map basics -->
<div id="new-map-modal" title="Create New Map" style="display:none;">
  <form id="new-map-form">
    <div>
      <label for="major">Degree Name:</label>
      <input type="text" name="major" id="major" required>
    </div>
    <div>
      <label for="degree_type">Degree Type:</label>
      <input type="text" name="degree_type" id="degree_type" required>
    </div>
    <div>
      <label for="college">College:</label>
      <select name="college" id="college" required>
        <?php echo get_all_colleges_options(); ?>
      </select>
    </div>
    <div>
      <label for="academicyear">Academic Year:</label>
      <select name="academicyear" id="academicyear" required>
        <?php echo get_academic_years_options(getCurrentAcademicYear() + 1); ?>
      </select>
    </div>
    <button type="submit" class="button">Create Map</button>
  </form>
</div>

<!-- OU Search Ignore Start Here -->
<div id="editCourseModal" title="Edit Course"></div>
<div id="editMapModal" title="Edit Degree Map Details"></div>
<div id="editHoursModal" title="Edit Degree Map Hours"></div>
<div id="editFootnotesModal" title="Edit Degree Map Footnotes"></div>

<?php
include('admin_footer.php');