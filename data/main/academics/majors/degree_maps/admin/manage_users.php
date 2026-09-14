<?php
require_once('map_edit_functions.php');

$title = 'Manage Users';


?>
<!DOCTYPE html>
<html lang="en">
<head>
<!-- OU Search Ignore Start Here -->
<?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/headcode.inc"); ?>
<!-- OU Search Ignore End Here -->
	<title><?php echo $title; ?></title>
	<meta name="Description" content="Manage Users">
	<script type="text/javascript">
		var page_url="https://www.wichita.edu/majors/degree_maps/maps.php";
	</script>
<?php

$local_javascript = [
	'javascript/jquery-ui-1.14.1/jquery-ui.min.js',
	'javascript/manage_users.js'];
$local_css = [
	'javascript/jquery-ui-1.14.1/jquery-ui.min.css',
	'../maps_style.css',
	'css/map_edit_functions.css'];
	
foreach ($local_javascript as $file){
	$filepath = getcwd() .'/'. $file;
	if (file_exists($filepath)){
		$filemtime = filemtime($filepath);
		echo '<script src="'.$file.'?tm='.$filemtime.'" defer></script>';
	}else{
		echo $filepath." missing";
	}
}
foreach ($local_css as $file){
	$filepath = getcwd() .'/'. $file;
	if (file_exists($filepath)){
		$filemtime = filemtime($filepath);
		echo '<link rel="stylesheet" href="'.$file.'?tm='.$filemtime.'" />'.PHP_EOL;
	}else{
		echo $filepath." missing";
	}
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
			<h1 class="headline-group"><span class="head">Manage Users</span></h1>
		</div>
		<div class="section-nav">
			<div class="section-nav__toggle">
				<div class="section-nav__toggle-wrapper">
					<button class="toggleSectionNav primary-toggle">Section Menu <svg class="icon" title="Open Section Links"><use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--menu"></use></svg></button>
				</div>
			</div>
			<nav>
				<ul>
<?php // include("_nav.ounav"); ?>
				</ul>
			</nav>
		</div>
	</div>
</header>
<div class="main-wrapper">
<section class="section-wrap ">
  <!-- Container for the list of users -->
  <div id="user-list">
    <!-- This will be filled by an AJAX call to get_users.php -->
  </div>
  
  <button id="add-user-btn" class="button">Add New User</button>

  <!-- Hidden form for adding/editing a user -->
  <div id="user-form-modal" style="display:none;">
  </div>
  

  </section>

</div>

  </main>




<!-- OU Search Ignore Start Here -->
<?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/footer.inc"); ?>
<?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/footcode.inc"); ?>
<?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/analytics.inc"); ?>
<script src="/_resources/js/degree-maps-search.js"></script>
<script src="https://unpkg.com/@popperjs/core@2"></script>
<script src="https://unpkg.com/tippy.js@6"></script>

<!-- OU Search Ignore End Here -->
</body>
</html>
