<?php
//set and maybe override
$title = 'Degree Programs';
$description = '<meta name="Description" content="All Wichita State University degree programs.">';

require_once($_SERVER['DOCUMENT_ROOT'].'/academics/majors/majors_functions.php');
//set in majors_functions; here for notation: $vars = array('id', 'search', 'order', 'alpha', 'cat', 'dept', 'college', 'online_learning', 'online_only', 'graduate', 'academic_year', 'selected_year');

//figure out what we are doing with the page and set up any items that we need to change.

?>
<!DOCTYPE HTML>
<html lang="en">
<head>
<!-- OU Search Ignore Start Here -->
<?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/headcode.inc"); ?>
<!-- OU Search Ignore End Here -->
	<title><?php echo $title; ?></title>
	<?php echo $description; ?>
	<script type="text/javascript"> var page_url="https://www.wichita.edu/academics/majors/index.php"; </script>
</head>
<body>

<!-- OU Search Ignore Start Here -->
<?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/header.inc"); ?>
<!-- OU Search Ignore End Here -->
<main class="main main--slab">
<?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/alert.php"); ?>
<?php 

if (! empty($id)){
	echo display_major($id);
}else{
?>
	<header class="page-header page-header--hero">
		<div class="page-header__bar">
			<div class="page-header__page-title">
				<h1 class="headline-group"><span class="head"><?php echo $title; ?></span></h1>
			</div>
			<div class="section-nav">
				<div class="section-nav__toggle">
					<div class="section-nav__toggle-wrapper">
						<button class="toggleSectionNav primary-toggle">Section Menu<svg class="icon" title="Open Section Links"><use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--menu"></use></svg></button>
					</div>
				</div>
				<nav>
					<ul>
						<?php
						echo get_section_nav();
						?>
					</ul>
				</nav>
			</div>
		</div>
		<div class="page-header__hero"><img src="/academics/_images/leaning_woman.jpg" alt="Reclining Figure sculpture by Henry Moore near Ablah library."></div>
	</header>
<?php 
	echo get_filters_section();
?>
	<div class="main-wrapper" style="flex-direction: column;">
		<div id="search_results" class="divided-list" style="width:100%;">
<?php
	if ($filters['order'] == 'college'){
		echo majors_by_college();
	}else{
		$majors = get_majors();
		echo majors_by_alpha($majors);
	}
?>
		</section>
		</div id="search_results">
	</div class="main-wrapper">
</main>








<?php
}
$filetime = filemtime($_SERVER['DOCUMENT_ROOT'] . '/_resources/js/degree-search.js');
?>
<!-- OU Search Ignore Start Here -->
<?php
include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/footer.inc");
include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/footcode.inc");
echo '<script src="/_resources/js/degree-search.js?'.$filetime.'" ></script>'.PHP_EOL;
include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/analytics.inc");
?>
<!-- OU Search Ignore End Here -->
</body>
</html>
