<?php 
require_once ('/data/www/config/functions.php');

//clean inputs

$search_options = array(
	'crs_crn' => 'CRN',
	'crs_subjcata' => 'Course Number (ex: POLS121)',
	'crs_title' => 'Course name',
	'crs_instrgrdr_FLname' => 'Instructor',
	'crs_method' => 'Course Method',
	'course_design' => 'Course Design',
	'testing_plan' => 'Testing Type',
	'all' => 'All Fields'
);


$fields = array('term', 'crs_crn', 'searchList', 'selected_fields', 'semester');

foreach ($search_options as $key => $search_option){
	$fields[] = $key;
}

foreach ($fields as $field){
	$$field = '';
}


if (! empty($_POST)){
	foreach ($_POST as $dirty_key => $filthy_animal){
		if ( in_array($dirty_key, $fields)){
			$_POST[$dirty_key] = htmlspecialchars($filthy_animal);
			$$dirty_key = $_POST[$dirty_key];
		}else{
			unset($_POST[$dirty_key]);
		}
	}
}
if (! empty($_GET)){
	foreach ($_GET as $dirty_key => $filthy_animal){
		if ( in_array($dirty_key, $fields)){
			$_GET[$dirty_key] = htmlspecialchars($filthy_animal);
			$$dirty_key = $_GET[$dirty_key];
		}else{
			unset($_GET[$dirty_key]);
		}
	}
}

if (!empty($semester)){
	if ($semester == 'Summer2021'){
		$term = '202130';
	}elseif ($semester == 'Fall2021'){
		$term = '202210';
	}elseif ($semester == 'Spring2022'){
		$term = '202220';
	}elseif ($semester == 'Summer2022'){
		$term = '202230';
	}elseif ($semester == 'Fall2022'){
		$term = '202310';
	}elseif ($semester == 'Spring2022'){
		$term = '202320';
	}else{
		$semester = '';
	}
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- OU Search Ignore Start Here --><?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/headcode.inc"); ?><!-- OU Search Ignore End Here -->
  <title>Course Design Plans</title>
  <meta name="Description" content="Information for returning to class at Wichita State">
  <script type="text/javascript">
	var page_url="https://www.wichita.edu/academics/course_plans.php";
  </script>
  <style type="text/css">
  div.c2 {display:none;}
  div.c1 {width:100%;}
  </style>
</head>
<body>
  <!-- OU Search Ignore Start Here --><?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/header.inc"); ?><!-- OU Search Ignore End Here -->
  <main class="main main--slab">
    <?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/alert.php"); ?>
		<header class="page-header">
            <div class="page-header__bar">
               <div class="page-header__page-title">
                  <h1 class="headline-group"><span class="head">Course Design Plans</span></h1>
               </div>
            </div>
         </header>
    <section class="section-wrap section-wrap--arrows-bright">
      <div class="landing-panel landing-panel--feature">
        <p class="landing-panel__text">As faculty determine their new course structures, they are reporting them here. Check back regularly for updates.</p>
        <div class="button-collection landing-panel__buttons">
          <a href="/services/registrar/spring2020-instructionalMethods.php" role="button" class="button">Course Codes Explained <svg class="icon button__trailing-icon" title="Course Codes Explained"><use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--arrow-right"></use></svg></a>
          <a href="/directories/index.php" role="button" class="button">Faculty/Staff Directory <svg class="icon button__trailing-icon" title="Faculty/Staff Directory"><use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--arrow-right"></use></svg></a>
          <?php /*
          <a href="/about/COVID-19/current_students.php" role="button" class="button">Spring Academic Calendar <svg class="icon button__trailing-icon" title="Spring Academic Calendar">
          <use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--arrow-right"></use></svg></a> */ ?>
        </div>
      </div>
    </section>
    <section class="section-wrap section-wrap--none &nbsp;">
      <p>Search below to see how much content is in-person/online, whether the online instruction is synchronous/asynchronous, and whether testing will be in-person/online. Instructors may also provide additional notes. Please email instructors with additional questions.</p>
      <p><strong>Filter your search by CRN, Course number, Course name, Instructor, or course design in the drop down menu, then add a search term</strong>.</p>
    </section>
    <section class="section-wrap section-wrap--shade-light section-wrap--short">
      <div class="search-filters row">
      <div class="col-4">
        <form id="search_select" class="search-filters__select" name="course_design_search">
          <label class="show-for-sr" for="selected_fields">Select search fields</label>
          <select id="selected_fields" name="selected_fields">
            <optgroup label="Search Fields">
              <option value="default">Default Search</option>
<?php 
foreach ($search_options as $key => $search_option){
	$selected = '';
	if (!empty($_GET[$key])){
		$selected = ' selected';
	}
	echo '<option value="'.$key.'"'.$selected.'>'.$search_option.'</option>';
}
?>
           </optgroup>
          </select>
       </form>
	</div>
    <div class="col-4">
       <form id="select_semester" class="search-filters__search" method="post" name="course_design_search">
          <label class="show-for-sr" for="term">Select Semester</label>
          <select id="term" name="term">
            <optgroup label="Search Semester">
              <option value="" >Select Semester</option>
              <option value="202130" <?php if ($term == 202130) echo 'selected'; ?> >Summer 2021</option>
              <option value="202210" <?php if ($term == 202210) echo 'selected'; ?> >Fall 2021</option>
              <?php /* 
              <option value="202220">Spring 2022</option>
              <option value="202230">Summer 2022</option>
              <option value="202310">Fall 2022</option>
              <option value="202320">Spring 2023</option>
				*/ ?>
            </optgroup>
          </select>
       </form>
	</div>
    <div class="col-4">
<?php 
$search_value = '';
foreach ($search_options as $key => $search_option){
	if (!empty($_GET[$key])){
		$search_value = $_GET[$key];
	}
}

?>
        <form id="search_form" class="search-filters__search" method="post" name="course_design_search">
          <label class="show-for-sr" for="searchList">Search</label> <input id="searchList" class="" name="searchList" type="text" placeholder="Search Course Design Plans" value="<?php echo $search_value; ?>"> <button class="" type="submit" value="Search">Search</button>
        </form>
	</div>
      </div>
    </section>
    <div class="main-wrapper">
	<div id="search_results" class="divided-list" style="width:100%;"><?php
	/*
	if (! empty($_GET['crs_instrgrdr_FLname'])){
		ob_start(); // begin collecting output
		include_once '/data/www/main/_resources/php/course_design_results3.php';
		$result = ob_get_clean(); // retrieve output from course_design_results3.php, stop buffering
		$result = json_decode($result, true) ;
		//var_dump($result);
		if (! empty($result['results'])){
			echo $result['results'];
		}
	}*/
	
	 ?></div>
    </div>
    <section class="teaser-collection section-wrap section-wrap--shade-dark">
      <header class="section-header section-header--no-border collection__header">
        <h2>Brief Code Descriptions</h2>
        <div class="button-collection">
          <a href="/services/registrar/spring2020-instructionalMethods.php" class="button" role="button">Full Code Descriptions <svg class="icon button__trailing-icon" title=" ">
          <use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--arrow-right"></use></svg></a>
        </div>
      </header>
      <div class="collection__items">
        <div class="teaser collection__item">
          <div class="teaser__body teaser__headline">
            <h2 class="heading4 headline-group"><span>HYB (hybrid)*</span></h2>
          </div>
          <div class="teaser__editorial">
            <p>Limited in-person instruction with some synchronous and/or asynchronous online engagement.</p>
            <p>*No additional online fee for HYB courses</p>
            <p> </p>
          </div>
        </div>
        <div class="teaser collection__item">
          <div class="teaser__body teaser__headline">
            <h2 class="heading4 headline-group"><span>HYO (hybrid online)</span></h2>
          </div>
          <div class="teaser__editorial">
            <p>All online instruction with required in-person testing.</p>
          </div>
        </div>
        <div class="teaser collection__item">
          <div class="teaser__body teaser__headline">
            <h2 class="heading4 headline-group"><span>IIS (fully online/synchronous)</span></h2>
          </div>
          <div class="teaser__editorial">
            <p>All online instruction with a regularly scheduled meeting time.</p>
          </div>
        </div>
        <div class="teaser collection__item">
          <div class="teaser__body teaser__headline">
            <h2 class="heading4 headline-group"><span>IIE (fully online/asynchronous)</span></h2>
          </div>
          <div class="teaser__editorial">
            <p>All online instruction with no scheduled meeting time.</p>
          </div>
        </div>
      </div>
    </section>
    <section class="section-wrap section-wrap--wheat &nbsp;">
      <div class="landing-panel landing-panel--quicklink">
        <h2 class="landing-panel__headline">Helpful links</h2>
        <div class="button-collection landing-panel__buttons">
          <a href="https://websvc-330.wichita.edu/CourseSearch/CourseSearch.aspx" role="button" class="button button button--large button--black-white">Course Schedule <svg class="icon button__trailing-icon" title="Course Schedule">
          <use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--arrow-right"></use></svg></a> <a href="/directories/index.php" role="button" class="button">Faculty/Staff Directory <svg class="icon button__trailing-icon" title="Faculty/Staff Directory">
          <use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--arrow-right"></use></svg></a> <a href="/directories/directory-a-z.php" role="button" class="button">A-Z Index <svg class="icon button__trailing-icon" title="A-Z Index">
          <use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--arrow-right"></use></svg></a>
        </div>
      </div>
    </section>
  </main><!-- OU Search Ignore Start Here --><?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/footer.inc"); ?><?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/footcode.inc"); ?><?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/analytics.inc"); ?>
  <div id="hidden" class="c2">
    <a id="de" href="https://a.cms.omniupdate.com/11/?skin=oucampus&amp;account=wsu&amp;site=www&amp;action=de&amp;path=/academics/course_plans.php">©</a>
  </div>
  <script src="/_resources/js/course-design-search.js?v=1"></script>
   <!-- OU Search Ignore End Here --><!-- ouc:info uuid="2019abeb-6d03-40a1-be69-17db690749cf"/ -->
</body>
</html>
