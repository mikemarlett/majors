<?php
require_once('/data/www/config/functions.php');

if (isset($_GET['id']) or isset($_POST['id'])){
	if (isset($_GET['id'])){
		$program_content_id = $mysqli->real_escape_string($_GET['id']);
	}
	if (isset($_POST['id'])){
		$program_content_id = $mysqli->real_escape_string($_POST['id']);
	}

	$sql = "SELECT * FROM `strat_comm_programs_content` WHERE `id` = '$program_content_id';";
	$program_page = mysqli_single_row($sql);
	foreach ($program_page as $key => $var){
		if (! isset($$key)){
			$$key = $var;
		}
	}
	//print_r($program_page);
	
	$sql = "SELECT * FROM `strat_comm_academic_programs` 
		INNER JOIN `strat_comm_program_content_key` ON 
			`strat_comm_academic_programs`.`id` = `strat_comm_program_content_key`.`academic_program_id` 
				WHERE `strat_comm_program_content_key`.`program_content_id` = '$program_content_id';";
	$academic_program = mysqli_single_row($sql);
	//print_r($academic_program);

	
	//get the colleges and departments associated with this program conent
	$sql = "SELECT `strat_comm_departments`.`department`, `strat_comm_colleges`.`college` FROM 
	((`strat_comm_departments` 
	INNER JOIN `strat_comm_department_content_key` ON `strat_comm_departments`.`id` = `strat_comm_department_content_key`.`department_id`)
	INNER JOIN `strat_comm_colleges` ON `strat_comm_departments`.`college_id` = `strat_comm_colleges`.`id`)
	WHERE `strat_comm_department_content_key`.`program_content_id` = '$program_content_id';";
	$departments = mysqli_multiple_rows($sql);
	//do something with the departments later
	//print_r($departments);
	
	$similar_programs = array();
	$sql = "SELECT
		`t1`.`academic_program` AS `academic_program`,
		`t1`.`id` AS `program_id`,
		`t1`.`program_simple_type` AS `program_simple_type`,
		`t1`.`program_type` AS `program_type`,
		`t2`.`id` AS `content_id`,
		`t2`.`main_image_url` AS `image` 
		FROM 
			`strat_comm_academic_programs` AS `t1`,
			`strat_comm_programs_content` as `t2`,
			`strat_comm_similar_programs`,
			`strat_comm_program_content_key`
		
		WHERE
			`strat_comm_similar_programs`.`main_academic_program_id` = `t1`.`id` AND
			`strat_comm_similar_programs`.`similar_academic_program_id` = '".$academic_program['academic_program_id']."' AND
			`strat_comm_program_content_key`.`academic_program_id` = `t1`.`id` AND
			`strat_comm_program_content_key`.`program_content_id` =  `t2`.`id`;";

	$main_similar_programs = mysqli_multiple_rows($sql);
	//print_r($main_similar_programs);


	//add them to the response list
	$simprogram_count = 0;
	if ($main_similar_programs){
		foreach ($main_similar_programs as $similar_program){
			if ($simprogram_count < 6){
			if ($similar_program['image']){
				$similar_program_image = $similar_program['image'];
				$similar_program_image = str_replace(
					'/uploads/',
					'https://wichita.edu/academics/majors/_images/',
					$similar_program_image
				);
			}else{
				$similar_program_image = '/images/no-image.jpg';
			}
			if (($similar_program['program_simple_type'] != 'Major') AND ($similar_program['program_simple_type'])){
				$similar_program_simple_type = ' ('.$similar_program['program_simple_type'].')' ;
			}elseif (($similar_program['program_simple_type'] != 'Major') AND (! $similar_program['program_simple_type'])){
				$similar_program_simple_type = ' ('.$similar_program['program_type'].')' ;
			}else{
				$similar_program_simple_type = '';
			}
			$similar_programs[]= '
		<a href="#" class="teaser collection__item teaser--card-wide teaser--card"> 
		<div class="teaser__image">
			<img src="'.$similar_program_image.'" alt="" class=""> 
		</div>
		<div class="teaser__body">
			<div class="teaser__headline">
				<div class="headline-group ">
					<span class="head">'.$similar_program['academic_program'].$similar_program_simple_type.'</span> 
				</div>
			</div>
		</div>
		</a>'.PHP_EOL;	
			$simprogram_count++;
			}
		}
	}

	//get secondary similar programs from db
	$sql = "SELECT
		`t1`.`academic_program` AS `academic_program`,
		`t1`.`id` AS `program_id`,
		`t1`.`program_simple_type` AS `program_simple_type`,
		`t2`.`id` AS `content_id`,
		`t2`.`main_image_url` AS `image` 
		FROM 
			`strat_comm_academic_programs` AS `t1`,
			`strat_comm_programs_content` as `t2`,
			`strat_comm_similar_programs`,
			`strat_comm_program_content_key`
		
		WHERE
			`strat_comm_similar_programs`.`similar_academic_program_id` = `t1`.`id` AND
			`strat_comm_similar_programs`.`main_academic_program_id` = '".$academic_program['academic_program_id']."' AND
			`strat_comm_program_content_key`.`academic_program_id` = `t1`.`id` AND
			`strat_comm_program_content_key`.`program_content_id` =  `t2`.`id`";
			
	$secondary_similar_programs = mysqli_multiple_rows($sql);
	//add them to the response list
	if ($secondary_similar_programs){
		foreach ($secondary_similar_programs as $similar_program){
			if ($simprogram_count < 6){
			if ($similar_program['image']){
				$similar_program_image = $similar_program['image'];
			}else{
				$similar_program_image = '/images/no-image.jpg';
			}
			if ($similar_program['program_simple_type'] != 'Major'){
				$similar_program_simple_type = ' ('.$similar_program['program_simple_type'].')' ;
			}else{
				$similar_program_simple_type = '';
			}
			$similar_programs[]= '
		<a href="#" class="teaser collection__item teaser--card-wide teaser--card"> 
		<div class="teaser__image">
			<img src="'.$similar_program_image.'" alt="" class=""> 
		</div>
		<div class="teaser__body">
			<div class="teaser__headline">
				<div class="headline-group ">
					<span class="head">'.$similar_program['academic_program'].$similar_program_simple_type.'</span> 
				</div>
			</div>
		</div>
		</a>'.PHP_EOL;	
			$simprogram_count++;
			}
		}
	}
}

if (! $admissions_link_url){
	$admissions_link_url = 'https://wichita.edu/admissions';
}

$use_em = array('use_main_image_caption', 'use_main_image_credit', 'use_inside_the_program', 'use_inside_link', 'use_wildcard_link');
foreach ($use_em as $var){
	if (($$var == NULL) OR ($$var == 1)){
		$$var = 'checked="checked"';
	}else{
		$$var = '';
	}
}

if (! $academic_program['program_simple_type']){
	$academic_program['program_simple_type'] = $academic_program['program_type'];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
	<?php include($_SERVER['DOCUMENT_ROOT'].'/_resources/includes/headcode.inc'); ?>
<?php 
global $header_extra;
if (! empty ($header_extra)){ echo $header_extra; } 
?>
	<title>Programs Page</title>
</head>
<body itemscope itemtype="http://schema.org/WebPage">

<?php include($_SERVER['DOCUMENT_ROOT'].'/_resources/includes/header.inc'); ?>
  
<main class="main">



<header class="page-header ">
	<div class="page-header__bar">
		<div class="page-header__page-title">
			<h1 class="headline-group ">
				<span class="head">Details: <?php echo $academic_program['academic_program'].', '.$academic_program['program_simple_type']; ?></span> 
			</h1>
		</div>
	</div>
</header>
<section class="section-wrap section-wrap--shade-light">
	<div class="program-card ">
		<div class="program-card__body">
			<h2 class="headline-group ">
				<span class="superhead"><?php echo $academic_program['program_simple_type']; ?></span> <span class="head"><?php echo $academic_program['academic_program']; ?></span> 
			</h2>
			<div class="link-collection ">
				<ul>
					<li><a href="#">All Programs</a></li>
<?php 
foreach ($departments as $department){
	echo '
	 <li><a href="#">'.$department['college'].'</a></li>
	 <li><a href="#">'.$department['department'].'</a></li>
';
}
?>
				</ul>
			</div>
			<div class="program-card__detail">
<?php 
if (! $description){
	$description = '<p>Description here.</p>';
}
?>
				<p>
					<div id="description" contenteditable="true" class="edit"><?php echo $description; ?></div>
				</p>
			</div>
			<div class="program-card__cta">
<?php 
if (! $learn_how){
	$learn_how = 'Learn how '.$academic_program['academic_program'].' <br>is the right fit for you.';
}
?>
				<h3 id="learn_how" contenteditable="true" class="edit">
				<?php echo $learn_how; ?>
				</h3>
				<div class="button-collection ">
<?php 
if ($academic_program['graduate'] == 1){
?>
<a href="https://www.applyweb.com/fixie/form/s/T8j180p" <?php 
}else{
?>
<a href="http://webs.wichita.edu/?u=admissionsforms&p=/wsu_rfi/" 
<?php 
}
?> role="button" class="button "> <svg role="img" class="icon button__leading-icon" aria-hidden="true"><use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--info"></use></svg> Request Info</a>

				<a href="https://wsu-info.org/go/split.htm" role="button" class="button button--accent"> Start Your Application <svg role="img" class="icon button__trailing-icon" aria-hidden="true"><use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--arrow-right"></use></svg> </a> 
				</div>
			</div>
		</div>
		<div class="program-card__image">
			<div class="captioned-media ">
<?php 
if (! $main_image_url){
	$main_image_url = 'https://wichita.edu/academics/majors/_images/no-image.jpg';
}
if (! $main_image_caption){
	$main_image_caption = 'Needs caption';
}
if (! $main_image_credit){
	$main_image_credit = 'Credit';
}
if (! $main_image_alt){
	$main_image_alt = 'Alt tag: Describe what is in the image; don\'t repeat the caption.';
}
if (! $main_video_url){
	$main_video_url = 'Video URL';
}
	$main_image_url = str_replace('/uploads/', 'https://wichita.edu/academics/majors/_images/', $main_image_url);
?>


				<figure>

					<div class="figure-wrapper">
					<!-- mdm -->
					<div class="image_edit_div"  id="main_image_edit_div">
					<form name="main_image_edit" id="main_image_edit" method="post" action="update_image.php" enctype="multipart/form-data">
						<?php 
				if (! empty($program_page['id'])) echo '						<input type="hidden" name="program_content_id" value="'.$program_page['id'].'">'.PHP_EOL;
				if (! empty($_SESSION['real_user']['id'])) echo '						<input type="hidden" name="user_id" value="'.$_SESSION['real_user']['id'].'">'.PHP_EOL;
						?>
						<input type="hidden" name="target_field" value="main_image_url">
						<div class="form-group">
							<label for="main_image_file">Image URL</label><br/>
							<input type="url" name="image_url" id="main_image_file" required value="<?php echo  $main_image_url; ?>" />
							<button type="submit" value="Save" class="btn btn-wsu" >Save</button>
						</div>
					</form>
					<hr>
						<p><strong>Alt Text:</strong> 
						<span class="edit" id="main_image_alt" contenteditable="true"><?php echo $main_image_alt; ?></span></p>
					</div>
					<!-- / mdm -->
						<img src="<?php echo  $main_image_url; ?>" id="main_image_url" alt="<?php echo $main_image_alt; ?>" class=""> <cite id="use_main_image_credit_" class="cite--photo-credit" <?php if ($use_main_image_credit == ''){ echo 'style="display: none;"';} ?>> <svg role="img" class="icon" aria-hidden="true"><use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--camera"></use></svg> <span id="main_image_credit" contenteditable="true"><?php echo $main_image_credit; ?></span> </cite>
					</div>
					<figcaption id="use_main_image_caption_" <?php if ($use_main_image_caption == ''){ echo 'style="display: none;"';} ?>>
						<p class="edit" id="main_image_caption" contenteditable="true">
							<?php echo $main_image_caption; ?> 
						</p>
					</figcaption>
				</figure>
			</div>
		</div>
	</div>
</section>
<section class="teaser-collection section-wrap collection--two-columns" id="curriculum_admissions">
	<div class="collection__items">
		<div class="teaser collection__item ">
			<div class="teaser__body">
<?php 
if (! $wildcard_headline){
	$wildcard_headline = 'Wildcard headline';
}
if (! $wildcard_text){
	$wildcard_text = '<p>Wildcard text here</p>';
}
if (! $wildcard_link_text){
	$wildcard_link_text = 'Link';
}
if (! $wildcard_link_url){
	$wildcard_link_url = '#';
}

//override for applied learning
	$wildcard_headline = 'Applied learning at Wichita State';
	$wildcard_text = '<p>At Wichita State, applied learning is everything. In fact, every degree we offer has a guaranteed applied learning or research experience built right into it — equipping you with the relevant skills and experience to make you workforce ready before graduation.</p>';
	$wildcard_link_text = 'Learn about our innovative approach';
	$wildcard_link_url = '/applied_learning';

?>
				<div class="teaser__headline">
					<h3 class="headline-group ">
						<span class="head" id="wildcard_headline" contenteditable="false"><?php echo $wildcard_headline; ?></span> 
					</h3>
				</div>
				<div class="teaser__editorial" id="wildcard_text" contenteditable="false"><?php echo $wildcard_text; ?></div>

				<div class="teaser__links" id="use_wildcard_link_" <?php if ($use_wildcard_link === '0'){ echo 'style="display:none;"'; }   ?>>
					<a href="<?php echo $wildcard_link_url; ?>" class=" link--rich"> <span class="" id="wildcard_link_text" contenteditable="false"><?php echo $wildcard_link_text; ?></span> </a> 
				</div>

			</div>
		</div>
<?php 
if (! $admissions_headline){
	$admissions_headline = 'Admission to the '.$academic_program['academic_program'].' Program';
}
if (! $admissions_text){
	$admissions_text = '<p>Admissions text here</p>';
}
if (! $admissions_link_text){
	$admissions_link_text = 'Admission requirements';
}
if (! $admissions_link_url){
	$admissions_link_url = '/admissions';
}
?>
		<div class="teaser collection__item ">
			<div class="teaser__body">
				<div class="teaser__headline">
					<h3 class="headline-group ">
						<span id="admissions_headline" contenteditable="true" class="head edit"><?php echo $admissions_headline; ?></span> 
					</h3>
				</div>
				<div id="admissions_text" contenteditable="true" class="teaser__editorial edit">
<?php echo $admissions_text; ?>
				</div>
				<div class="teaser__links">
					<a href="<?php echo $admissions_link_url; ?>" class=" link--rich"> <span class="section_foot_link edit" id="admissions_link_text" contenteditable="true"><?php echo $admissions_link_text; ?></span> </a> 
				</div>
			</div>
		</div>
	</div>
</section>
<?php 
if ($use_inside_the_program == 'checked="checked"'){
?>
<section id="use_inside_the_program_" class="section-wrap section-wrap--dots" style="background-position: 0px calc(90% - 23.5px);">
<?php
}else{
?>
<section id="use_inside_the_program_" class="section-wrap section-wrap--dots" style="background-position: 0px calc(90% - 23.5px); display:none;">
<?php 
}
if (! $inside_the_program_headline){
	$inside_the_program_headline = 'Inside headline';
}
if (! $inside_the_program_text){
	$inside_the_program_text = '<p>Inside text here</p>';
}
if (! $inside_the_program_link_text){
	$inside_the_program_link_text = 'Link';
}
if (! $inside_the_program_link_url){
	$inside_the_program_link_url = '#';
}
if (! $inside_the_program_image_url){
	$inside_the_program_image_url = '/images/no-image.jpg';
}
if (! $inside_the_program_image_alt){
	$inside_the_program_image_alt = '';
}
?>	<header class="section-header section-header--no-border">
		<h2>
			Inside the Program
		</h2>
	</header>
	<div class="teaser teaser--columned-intro">
		<div class="teaser__image">
<!-- mdm -->
	<div class="image_edit_div" id="inside_the_program_image_edit_div">
	<form name="inside_the_program_image_edit" id="inside_the_program_image_edit" method="post" action="/strat-comm-upload.php" enctype="multipart/form-data">
		<?php 
if (! empty($program_page['id'])) echo '		<input type="hidden" name="program_content_id" value="'.$program_page['id'].'">'.PHP_EOL;
if (! empty($_SESSION['real_user']['id'])) echo '		<input type="hidden" name="user_id" value="'.$_SESSION['real_user']['id'].'">'.PHP_EOL;
		?>
		<input type="hidden" name="target_field" value="inside_the_program_image_url">
		<div class="form-group">
			<label for="inside_the_program_image_file">Select Your Image</label><br/>
			<input type="file" name="file" id="inside_the_program_image_file" required />
			<button type="submit" value="Upload" class="btn btn-wsu" >Upload</button>
		</div>
		<h4 id='loading' style="display:none;"><i class="fa fa-spinner fa-spin" style="font-size:24px"></i> Loading...</h4>
		<div id="message"></div>
	</form>
	<hr>
		<p><strong>Alt Tag:</strong> 
		<span class="edit" id="inside_the_program_image_alt" contenteditable="true"><?php echo $inside_the_program_image_alt; ?></span></p>
	</div>
<!-- /mdm -->
			<img id="inside_the_program_image_url" src="<?php echo $inside_the_program_image_url; ?>" alt="<?php echo $inside_the_program_image_alt; ?>" class="">
		</div>
		<div class="teaser__body">
			<div class="teaser__headline">
				<h3 class="headline-group ">
					<span class="head edit" id="inside_the_program_headline" contenteditable="true"><?php echo $inside_the_program_headline; ?></span> 
				</h3>
			</div>
			<div class="teaser__editorial edit" id="inside_the_program_text" contenteditable="true"><?php echo $inside_the_program_text; ?></div><!-- editable -->
			<div class="teaser__links" id="use_inside_link_" <?php if ($use_inside_link == ''){ echo 'style="display: none;"';} ?>>
				<a href="<?php echo $inside_the_program_link_url; ?>" class=" link--rich link--has-icon"> <svg role="img" class="icon icon-leading" aria-hidden="true"><use xlink:href="/_resources/images/sprites/svg-sprite-custom-symbol.svg#design--trophy"></use></svg> <span class="edit" id="inside_the_program_link_text" contenteditable="true"><?php echo $inside_the_program_link_text; ?></span> </a> 
			</div>
		</div>
	</div>
</section>
<section class="teaser-collection section-wrap collection--two-columns section-wrap--nipple-down">
	<div class="collection__items">
		<div class="teaser collection__item ">
			<div class="teaser__body">
				<div class="teaser__headline">
					<h3 class="headline-group ">
						<span class="head">Curriculum</span> 
					</h3>
<?php 
if (! $curriculum_text){
	$curriculum_text = '<p>Curriculum text here</p>';
}
if (! $curriculum_link_text){
	$curriculum_link_text = 'View the '.$academic_program['academic_program'].' Curriculum';
}
if (! $curriculum_link_url){
	$curriculum_link_url = '#';
}
?>
				</div>
				<div id="curriculum_text" contenteditable="true" class="teaser__editorial edit">
					<?php echo $curriculum_text; ?>
				</div><!-- editable -->
				<div class="teaser__links">
					<a href="#<?php //echo $curriculum_link_url; ?>" class=" link--rich"> <span id="curriculum_link_text" class=" edit" contenteditable="true"><?php echo $curriculum_link_text; ?></span> </a> 
				</div>
			</div>
		</div>
		<div class="teaser collection__item ">
<?php 
if (! $careers_headline){
	$careers_headline = 'Careers in '.$academic_program['academic_program'];
}
if (! $careers_text){
	$careers_text = '<p>Careers text here</p>';
}
if (! $careers_link_text){
	$careers_link_text = 'Link';
}
if (! $careers_link_url){
	$careers_link_url = '#';
}
?>			<div class="teaser__body">
				<div class="teaser__headline">
					<h3 class="headline-group ">
						<span class="head edit" id="careers_headline" contenteditable="true"><?php echo $careers_headline; ?></span> 
					</h3>
				</div>
				<div class="teaser__editorial edit" id="careers_text" contenteditable="true" ><?php echo $careers_text; ?></div>
				<div class="teaser__links">
					<a  href="<?php echo $careers_link_url; ?>" class=" link--rich"> <span  class="edit" id="careers_link_text" contenteditable="true"><?php echo $careers_link_text; ?></span> </a> 
				</div>
			</div>
		</div>
	</div>
</section>
<section class="teaser-collection collection--two-columns section-wrap section-wrap--shade-dark section-wrap--image-background-texturize collection--two-columns-early-break">
	<header class="section-header section-header--no-border section-header--centered collection__header">
		<h2>
			Explore Similar Programs
		</h2>
	</header>
	<div class="collection__items">
<?php 
	if (isset($similar_programs) and ($similar_programs)){
		
		foreach ($similar_programs as $similar_program){
			echo $similar_program;
		}
	}
?>
	</div>
	<div class="section-wrap__image">
		<img src="<?php echo $similar_program_image; //this is just the last image ?>" alt="" class=""> 
	</div>
</section>
</main> 

	<?php include($_SERVER['DOCUMENT_ROOT'].'/_resources/includes/footer.inc'); ?>
	<?php include($_SERVER['DOCUMENT_ROOT'].'/_resources/includes/footcode.inc'); ?>
	<?php global $footer_extra; if (! empty($footer_extra)) echo $footer_extra; ?>
<!-- /svg store -->
<script>
	/*!
	* scriptLoader - v0.1
	*
	* Copyright (c) 2014 Dave Olsen, http://dmolsen.com
	* Licensed under the MIT license
	*
	*/
	
	var scriptLoader = {
	  run: function(js,cb,target) {
	    var s  = document.getElementById(target+'-'+cb);
	    for (var i = 0; i < js.length; i++) {
	      var src = (typeof js[i] != 'string') ? js[i].src : js[i];
	      var c   = document.createElement('script');
	      c.src   = '../../'+src+'?'+cb;
	      if (typeof js[i] != 'string') {
	        if (js[i].dep !== undefined) {
	          c.onload = function(dep,cb,target) {
	            return function() {
	              scriptLoader.run(dep,cb,target);
	            }
	          }(js[i].dep,cb,target);
	        }
	      }
	      s.parentNode.insertBefore(c,s);
	    }
	  }
	}
</script>
<script id="pl-js-polyfill-insert-0">
	(function() {
	  if (self != top) {
	    var cb = '0';
	    var js = [];
	    if (typeof document !== 'undefined' && !('classList' in document.documentElement)) {
	      js.push('styleguide/bower_components/classList.min.js');
	    }
	    scriptLoader.run(js,cb,'pl-js-polyfill-insert');
	  }
	})();
</script>
<script id="pl-js-insert-0">
	(function() {
	  if (self != top) {
	    var cb = '0';
	    var js = [ { 'src': 'styleguide/bower_components/jwerty.min.js', 'dep': [ 'styleguide/js/patternlab-pattern.min.js' ] } ];
	    scriptLoader.run(js,cb,'pl-js-insert');
	  }
	})();
</script>
<!-- End Pattern Lab -->
<script src="/_resources/js/svg4everybody.js" defer="">
</script>
<script src="/_resources/js/main.js" defer="">
</script>
<script src="/javascript/jquery-3.2.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
<script src="/javascript/bootstrap/bootstrap.min.js"></script>
<script src="https://use.fontawesome.com/1547c26dbf.js"></script>
<script src="/javascript/lightbox2/js/lightbox.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.form/4.2.2/jquery.form.min.js" integrity="sha384-FzT3vTVGXqf7wRfy8k4BiyzvbNfeYjK+frTVqZeNDFl8woCbF0CYG6g2fMEFFo/i" crossorigin="anonymous"></script>
<!-- <script src="/javascript/mutation-summary-master/src/mutation-summary.js"></script> -->
<script>
  function resizeIframe(obj) {
    obj.style.height = obj.contentWindow.document.body.scrollHeight + 'px';
  }
</script>
<script>$("#dt_user_picker").change(function() {
     this.form.submit();
});
</script>
<script src="/javascript/ckeditor/ckeditor.js"></script>
<script>
	//turn off automatic editor creation at first
    CKEDITOR.disableAutoInline = true;
    CKEDITOR.inline( 'description', {customConfig: '/javascript/config-inline-simple.js'} );
    CKEDITOR.inline( 'curriculum_text', {customConfig: '/javascript/config-inline-simple.js'} );
    CKEDITOR.inline( 'admissions_text', {customConfig: '/javascript/config-inline-simple.js'} );
    CKEDITOR.inline( 'inside_the_program_text', {customConfig: '/javascript/config-inline-simple.js'} );
//    CKEDITOR.inline( 'wildcard_text', {customConfig: '/javascript/config-inline-simple.js'} );
    CKEDITOR.inline( 'careers_text', {customConfig: '/javascript/config-inline-simple.js'} );
</script>
<?php
echo '<script>
$(document).ready(function(){
 
 // Add Class
 $(".edit").click(function(){
  $(this).addClass("editMode");
 });

 // Save data
 $(".edit").focusout(function(){
  $(this).removeClass("editMode");
  var program_content_id = '.$program_page['id'].'
  var field_name = this.id;
  var value = $(this).html();

  $.ajax({
   url: "update_programs.php",
   type: "post",
   data: { field:field_name, value:value, program_content_id:program_content_id},
   success:function(response){
    console.log("Saved "+field_name+" successfully."); 
   }
  });
 
 });

});
</script>
';
?>
<script>
$(document).ready(function (e) {
	$("#main_image_edit").on('submit',(function(e) {
		e.preventDefault();
		$("#message").empty();
		$('#loading').show();
		$.ajax({
			url: "strat-comm-upload.php", // Url to which the request is send
			type: "POST",             // Type of request to be send, called as method
			data: new FormData(this), // Data sent to server, a set of key/value pairs (i.e. form fields and values)
			contentType: false,       // The content type used when sending data to the server.
			cache: false,             // To unable request pages to be cached
			processData:false,        // To send DOMDocument or non processed data file it is set to false
			success: function(data)   // A function to be called if request succeeds
			{
				$('#loading').hide();
				$("#message").html(data);
			}
		});
	}));

	// Function to preview image after validation
	$(function() {
		$("#main_image_file").change(function() {
			$("#message").empty(); // To remove the previous error message
			var file = this.files[0];
			var imagefile = file.type;
			var match= ["image/jpeg","image/png","image/jpg"];
			if(!((imagefile==match[0]) || (imagefile==match[1]) || (imagefile==match[2]))) {
				$('#main_image_url').attr('src','/images/no-image.jpg');
				$("#message").html("<h3 class='text-danger'>Invalid File Type</h3>"+"<p id='error_message'>Only jpeg, jpg, png and (if you must) gif images allowed</p>");
				return false;
			}else{
				var reader = new FileReader();
				reader.onload = imageIsLoaded;
				reader.readAsDataURL(this.files[0]);
			}
		});
	});
	function imageIsLoaded(e) {
		$("#main_image_file").css("color","green");
		/*$('#image_preview').css("display", "block");*/
		$('#main_image_url').attr('src', e.target.result);
	};
});

$(document).ready(function (e) {
	$("#inside_the_program_image_edit").on('submit',(function(e) {
		e.preventDefault();
		$("#message").empty();
		$('#loading').show();
		$.ajax({
			url: "strat-comm-upload.php", // Url to which the request is send
			type: "POST",             // Type of request to be send, called as method
			data: new FormData(this), // Data sent to server, a set of key/value pairs (i.e. form fields and values)
			contentType: false,       // The content type used when sending data to the server.
			cache: false,             // To unable request pages to be cached
			processData:false,        // To send DOMDocument or non processed data file it is set to false
			success: function(data)   // A function to be called if request succeeds
			{
				$('#loading').hide();
				$("#message").html(data);
			}
		});
	}));

	// Function to preview image after validation
	$(function() {
		$("#inside_the_program_image_file").change(function() {
			$("#message").empty(); // To remove the previous error message
			var file = this.files[0];
			var imagefile = file.type;
			var match= ["image/jpeg","image/png","image/jpg"];
			if(!((imagefile==match[0]) || (imagefile==match[1]) || (imagefile==match[2]))) {
				$('#inside_the_program_image_url').attr('src','/images/no-image.jpg');
				$("#message").html("<h3 class='text-danger'>Invalid File Type</h3>"+"<p id='error_message'>Only jpeg, jpg, png and (if you must) gif images allowed</p>");
				return false;
			}else{
				var reader = new FileReader();
				reader.onload = imageIsLoaded;
				reader.readAsDataURL(this.files[0]);
			}
		});
	});
	function imageIsLoaded(e) {
		$("#inside_the_program_image_file").css("color","green");
		/*$('#image_preview').css("display", "block");*/
		$('#inside_the_program_image_url').attr('src', e.target.result);
	};
});

</script>
<?php
echo '<script>
$(document).ready(function(){
 
 // Save data
 $(".programs_form_field").change(function(){
  var field_name = this.id;
  var value = $(this).val();
  if (value == "on"){
	if ($(this).is(":checked")) {
		value = 1;
	} else {
		value = 0;
	}
  }
  var program_content_id = "'.$program_page['id'].'";

 if (
	(field_name == "use_inside_the_program") ||
	(field_name == "use_main_image_caption") ||
 	(field_name == "use_main_image_credit") ||
 	(field_name == "use_inside_link") ||
 	(field_name == "use_wildcard_link")
 	){
 		showhide(field_name, value);
 	}

	function showhide(field_name, value) {
		if (value == "0"){
			$("#"+field_name+"_").css("display","none");
		}else if (value == "1"){
			$("#"+field_name+"_").css("display","block");
		}
	};

  $.ajax({
   url: "update_programs.php",
   type: "post",
   data: { field:field_name, value:value, program_content_id:program_content_id},
   success:function(response){
    console.log("Form says :"+response); 
   }
  });
 
 });

});
</script>
';
?>
<!--experiments below-->


</body>
</html>
