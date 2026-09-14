<!DOCTYPE html>
<html lang="en">
<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/../config/functions.php');
require_once('maps_functions.php');

$title = 'Degree Maps';
if (! empty($degree_map_id)){
	$title = get_map_title($degree_map_id);
}
?>
<head>
<!-- OU Search Ignore Start Here -->
<?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/headcode.inc"); ?>
<!-- OU Search Ignore End Here -->
	<title><?php echo $title; ?></title>
	<meta name="Description" content="Degree Maps to guide students through degrees at Wichita State">
	<script type="text/javascript">
		var page_url="https://www.wichita.edu/majors/degree_maps/maps.php";
	</script>
<?php
$maps_style = 'maps_style.css';
if (file_exists($maps_style)) {
    echo '<link rel="stylesheet" href="maps_style.css?md='.date ("YmdHis", filemtime($maps_style)).'">';
}
?>
<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css" />
</head>
<body>
<!-- OU Search Ignore Start Here -->
<?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/header.inc"); ?>
<!-- OU Search Ignore End Here -->
<main class="main main--slab"><?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/alert.php"); ?>
<header class="page-header noprint">
	<div class="page-header__bar">
		<div class="page-header__page-title">
			<h1 class="headline-group"><span class="head">Degree Maps</span></h1>
		</div>
		<div class="section-nav">
			<div class="section-nav__toggle">
				<div class="section-nav__toggle-wrapper">
					<button class="toggleSectionNav primary-toggle">Section Menu <svg class="icon" title="Open Section Links"><use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--menu"></use></svg></button>
				</div>
			</div>
			<nav>
				<ul>
<?php include("_nav.ounav"); ?>
				</ul>
			</nav>
		</div>
	</div>
</header>

<section class="section-wrap section-wrap--arrows-bright noprint">
	<div class="landing-panel landing-panel--feature">
		<p class="landing-panel__text">Use the search field below to search the major maps by name, college, department, or degree type. Use the pulldown to customize your search. Add as many terms as you need to narrow your search.</p>
	</div>
</section>
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
			$academic_year = getCurrentAcademicYear();
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
		<?php
if (!empty ($order) && $order == 'college'){
	?>
			<input type="hidden" id="order" name="order" value="college">
<?php }else{ ?>
			<input type="hidden" id="order" name="order" value="alpha">
<?php } ?>			<label class="show-for-sr" for="selected_college">Select search fields</label>
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
<div class="main-wrapper" style="flex-direction: column;">
	<div style="width:100%;" class="noprint">
		<h2 class="heading5" >Select View:</h2>
		<p style="font-size: 14pt;">
		<?php
		
if (!empty ($order) && $order == 'college'){
	?><strong><a href="<?php echo $_SERVER['PHP_SELF']; ?>?order=college" name="order" id="college">All Programs by College</a></strong> &nbsp;&nbsp;|&nbsp;&nbsp; <a href="<?php echo $_SERVER['PHP_SELF']; ?>?order=alpha" name="order" id="alpha">All Programs by Alphabetical Listing</a><?php
}else{
	?><a href="<?php echo $_SERVER['PHP_SELF']; ?>?order=college" name="order" id="college">All Programs by College</a> &nbsp;&nbsp;|&nbsp;&nbsp; <strong><a href="<?php echo $_SERVER['PHP_SELF']; ?>?order=alpha" name="order" id="alpha">All Programs by Alphabetical Listing</a></strong><?php
}
		?>
		</p>
		<?php // echo '<p>Functions updated: '.date ("F d Y H:i:s.", filemtime('maps_functions.php')).'</p>'; ?>
	</div>

	<div id="search_results" class="divided-list" style="width:100%;">
	<?php 
	//if we haven't searched for anything or don't have a specific major in mind, fill this with a list of majors maps
//////LISTING ////////

if (! empty($degree_map_id)){
	$maps = display_degree_map($degree_map_id);
}elseif (!empty ($order) && $order == 'college'){
	$maps = maps_by_college();
}else{
	$maps = maps_by_alpha();
}
if (! empty($maps)){
	echo $maps;
}else{
	echo '<h2>Error: No results</h2>';
}

////// END LISTING /////////////
//	*/
	?>
	</div>
</div>
</main> 
<!-- OU Search Ignore Start Here -->
<?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/footer.inc"); ?>
<?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/footcode.inc"); ?>
<?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/analytics.inc"); ?>
<script src="/_resources/js/degree-maps-search.js"></script>
<script src="https://unpkg.com/@popperjs/core@2"></script>
<script src="https://unpkg.com/tippy.js@6"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    tippy('.footnote-link', {
      allowHTML: true,
      placement: 'bottom',
      trigger: 'mouseenter focus'
    });
  });
</script>
<!-- OU Search Ignore End Here -->
</body>
</html>
