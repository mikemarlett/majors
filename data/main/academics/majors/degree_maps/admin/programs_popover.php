<?php 
header('Access-Control-Allow-Origin: *');
require_once('functions.php');
//return html of popover
if (isset ($_GET['content']) AND isset($_GET['id'])){

	// ************** Main Text and Image **************** //
	if ( $_GET['content'] == 'main'){
		$sql = "SELECT `strat_comm_academic_programs`.`academic_program` , `strat_comm_programs_content`.`description`, `strat_comm_programs_content`.`learn_how`, `strat_comm_programs_content`.`main_image_url`, `strat_comm_programs_content`.`main_image_caption`, `strat_comm_programs_content`.`main_image_credit`, `strat_comm_programs_content`.`use_main_image_caption`, `strat_comm_programs_content`.`use_main_image_credit`, `strat_comm_programs_content`.`main_image_alt` FROM `strat_comm_programs_content`, `strat_comm_academic_programs`WHERE `strat_comm_programs_content`.`academic_program_id` = `strat_comm_academic_programs`.`id` AND `strat_comm_programs_content`.`id` = '".$_GET['id']."';";
		$program_content = mysqli_single_row($sql);
		$return_html = '<div class="popover-heading">Main: '.$program_content['academic_program'].'</div><div class="popover-body">';
		if ($program_content['description']){
			$return_html .='<div class="popover_p"><strong>Description:</strong> '.$program_content['description'].'</div>'.PHP_EOL;
		}else{
			$return_html .='<div class="popover_p"><strong><em>Needs intro text.</em></strong></div>'.PHP_EOL;
		}
		if (! $program_content['learn_how']){
			$return_html .='<div class="popover_p"><strong><em>Needs "learn how" text.</em></strong></div>'.PHP_EOL;
		}else{
			$return_html .='<div class="popover_p"><strong>Learn how:</strong> '.$program_content['learn_how'].'</div>'.PHP_EOL;
		}
		if (! $program_content['main_image_url']){
			$return_html .='<div class="popover_p"><strong><em> Needs an image.</em></strong></div>'.PHP_EOL;
		}else{
			$return_html .='<div class="popover_p"><strong>Image:</strong> <img src="'.$program_content['main_image_url'].'" style="width:100%; height: auto"></div>'.PHP_EOL;
		}
		if ((! $program_content['main_image_caption']) AND (($program_content['use_main_image_caption'] == 1) OR ($program_content['use_main_image_caption'] == NULL))){
			$return_html .='<div class="popover_p"><strong><em>Image needs a caption.</em></strong></div>'.PHP_EOL;
		}elseif ($program_content['use_main_image_caption'] == 0){
			$return_html .='<div class="popover_p"><strong>Image caption:</strong> None needed.</div>'.PHP_EOL;
		}else{
			$return_html .='<div class="popover_p"><strong>Image caption:</strong> '.$program_content['main_image_caption'].'</div>'.PHP_EOL;
		}
		if ((! $program_content['main_image_credit']) AND (($program_content['use_main_image_credit'] == 1) OR ($program_content['use_main_image_credit'] == NULL))){
			$return_html .='<div class="popover_p"><strong><em>Image needs a credit.</em></strong></div>'.PHP_EOL;
		}elseif ($program_content['use_main_image_credit'] == 0){
			$return_html .='<div class="popover_p"><strong>Image credit:</strong> None needed.</div>'.PHP_EOL;
		}else{
			$return_html .='<div class="popover_p"><strong>Image credit:</strong> '.$program_content['main_image_credit'].'</div>'.PHP_EOL;
		}
		if (! $program_content['main_image_alt']){
			$return_html .='<div class="popover_p"><strong><em>Image needs an alt tag.</em></strong></div>'.PHP_EOL;
		}else{
			$return_html .='<div class="popover_p"><strong>Alt tag:</strong> '.$program_content['main_image_alt'].'</div>'.PHP_EOL;
		}
		$return_html .= '</div>';

	// ************** Main Video **************** //
	}elseif($_GET['content'] == 'video'){
		$sql = "SELECT `strat_comm_academic_programs`.`academic_program` , `strat_comm_programs_content`.`main_video_url` FROM `strat_comm_programs_content`, `strat_comm_academic_programs`WHERE `strat_comm_programs_content`.`academic_program_id` = `strat_comm_academic_programs`.`id` AND `strat_comm_programs_content`.`id` = '".$_GET['id']."';";
		$program_content = mysqli_single_row($sql);
		if ($program_content['main_video_url']){
			$return_html = '<div class="popover-heading">Video: '.$program_content['academic_program'].'</div><div class="popover-body"><a href="'.$program_content['main_video_url'].'" target="_blank">'.$program_content['main_video_url'].'</a></div>';
		}else{
			$return_html = '<div class="popover-heading">Video: '.$program_content['academic_program'].'</div><div class="popover-body">No video has been set.</div>';
		}

	// ************** Curriculum **************** //
	}elseif($_GET['content'] == 'curriculum'){
		$sql = "SELECT `strat_comm_academic_programs`.`academic_program` , `strat_comm_programs_content`.`curriculum_text`, `strat_comm_programs_content`.`curriculum_link_text`, `strat_comm_programs_content`.`curriculum_link_url` FROM `strat_comm_programs_content`, `strat_comm_academic_programs`WHERE `strat_comm_programs_content`.`academic_program_id` = `strat_comm_academic_programs`.`id` AND `strat_comm_programs_content`.`id` = '".$_GET['id']."';";
		$program_content = mysqli_single_row($sql);

		$return_html = '<div class="popover-heading">Curriculum: '.$program_content['academic_program'].'</div><div class="popover-body">';
		if ($program_content['curriculum_text']){
			$return_html .='<div class="popover_p"><strong>Text:</strong> '.$program_content['curriculum_text'].'</div>'.PHP_EOL;
		}else{
			$return_html .='<div class="popover_p"><strong><em>Needs text.</em></strong></div>'.PHP_EOL;
		}
		if (! $program_content['curriculum_link_text']){
			$return_html .='<div class="popover_p"><strong><em> Needs link text.</em></strong></div>'.PHP_EOL;
		}else{
			$return_html .='<div class="popover_p"><strong>Link text:</strong> '.$program_content['curriculum_link_text'].'</div>'.PHP_EOL;
		}
		if (! $program_content['curriculum_link_url']){
			$return_html .='<div class="popover_p"><strong><em>Needs url</em></strong></div>'.PHP_EOL;
		}else{
			$return_html .='<p class="popover_p"><strong>URL:</strong> '.$program_content['curriculum_link_url'].PHP_EOL;
		}
		$return_html .= '</div>';

	// ************** Admissions **************** //
	}elseif($_GET['content'] == 'admissions'){
		$sql = "SELECT `strat_comm_academic_programs`.`academic_program` , `strat_comm_programs_content`.`admissions_headline`, `strat_comm_programs_content`.`admissions_text`, `strat_comm_programs_content`.`admissions_link_text`, `strat_comm_programs_content`.`admissions_link_url` FROM `strat_comm_programs_content`, `strat_comm_academic_programs`WHERE `strat_comm_programs_content`.`academic_program_id` = `strat_comm_academic_programs`.`id` AND `strat_comm_programs_content`.`id` = '".$_GET['id']."';";
		$program_content = mysqli_single_row($sql);

		$return_html = '<div class="popover-heading">Admissions: '.$program_content['academic_program'].'</div><div class="popover-body">';
		if ($program_content['admissions_headline']){
			$return_html .='<div class="popover_p"><strong>Headline</strong>: '.$program_content['admissions_headline'].'</div>';
		}else{
			$return_html .='<div class="popover_p"><strong><em>Needs headline</em></strong></div>'.PHP_EOL;
		}
		if (! $program_content['admissions_text']){
			$return_html .='<div class="popover_p"><strong><em>Needs text</em></strong></div>'.PHP_EOL;
		}else{
			$return_html .='<div class="popover_p"><strong>Text:</strong> '.$program_content['admissions_text'].'</div>'.PHP_EOL;
		}
		if (! $program_content['admissions_link_text']){
			$return_html .='<div class="popover_p"><strong><em>Needs link text</em></strong></div>'.PHP_EOL;
		}else{
			$return_html .='<div class="popover_p"><strong>Link text:</strong> '.$program_content['admissions_link_text'].'</div>'.PHP_EOL;
		}
		if (! $program_content['admissions_link_url']){
			$return_html .='<div class="popover_p"><strong><em>Needs url</em></strong></div>'.PHP_EOL;
		}else{
			$return_html .='<p class="popover_p"><strong>URL:</strong> '.$program_content['admissions_link_url'].PHP_EOL;
		}
		$return_html .= '</div>';

		// ************** Inside the Program **************** //
	}elseif($_GET['content'] == 'inside_the_program'){
		$sql = "SELECT `strat_comm_academic_programs`.`academic_program` , `strat_comm_programs_content`.`inside_the_program_headline`, `strat_comm_programs_content`.`inside_the_program_text`, `strat_comm_programs_content`.`use_inside_link`, `strat_comm_programs_content`.`inside_the_program_link_text`, `strat_comm_programs_content`.`inside_the_program_link_url`, `strat_comm_programs_content`.`inside_the_program_image_url`, `strat_comm_programs_content`.`inside_the_program_image_alt` FROM `strat_comm_programs_content`, `strat_comm_academic_programs`WHERE `strat_comm_programs_content`.`academic_program_id` = `strat_comm_academic_programs`.`id` AND `strat_comm_programs_content`.`id` = '".$_GET['id']."';";
		$program_content = mysqli_single_row($sql);

		$return_html = '<div class="popover-heading">Inside_the_program: '.$program_content['academic_program'].'</div><div class="popover-body">';
		if ($program_content['inside_the_program_headline']){
			$return_html .='<div class="popover_p"><strong>Headline</strong>: '.$program_content['inside_the_program_headline'].'</div>';
		}else{
			$return_html .='<div class="popover_p"><strong><em>Needs headline</em></strong></div>'.PHP_EOL;
		}
		
		if (! $program_content['inside_the_program_text']){
			$return_html .='<div class="popover_p"><strong><em>Needs text.</em></strong></div>'.PHP_EOL;
		}else{
			$return_html .='<div class="popover_p"><strong>Text:</strong> '.$program_content['inside_the_program_text'].'</div>'.PHP_EOL;
		}
		
		if ((! $program_content['inside_the_program_link_text']) AND (($program_content['use_inside_link'] == 1) OR ($program_content['use_inside_link'] == NULL))){
			$return_html .='<div class="popover_p"><strong><em>Needs link text.</em></strong></div>'.PHP_EOL;
		}elseif ($program_content['use_inside_link'] == 0){
			$return_html .='<div class="popover_p"><strong>Link text:</strong> None needed.</div>'.PHP_EOL;
		}else{
			$return_html .='<div class="popover_p"><strong>Link text:</strong> '.$program_content['inside_the_program_link_text'].'</div>'.PHP_EOL;
		}
		
		if ((! $program_content['inside_the_program_link_url']) AND (($program_content['use_inside_link'] == 1) OR ($program_content['use_inside_link'] == NULL))){
			$return_html .='<div class="popover_p"><strong><em>Needs url</em></strong></div>'.PHP_EOL;
		}elseif ($program_content['use_inside_link'] == 0){
			$return_html .='<div class="popover_p"><strong>URL:</strong> None needed.</div>'.PHP_EOL;
		}else{
			$return_html .='<p class="popover_p"><strong>URL:</strong> '.$program_content['inside_the_program_link_url'].PHP_EOL;
		}
		
		if (! $program_content['inside_the_program_image_url']){
			$return_html .='<div class="popover_p"><strong><em>Needs an image.</em></strong></div>'.PHP_EOL;
		}else{
			$return_html .='<div class="popover_p"><strong>Image:</strong> <img src="'.$program_content['inside_the_program_image_url'].'" style="width:100%; height: auto"></div>'.PHP_EOL;
		}
		
		if (! $program_content['inside_the_program_image_alt']){
			$return_html .='<div class="popover_p"><strong><em>Image needs alt tag.</em></strong></div>'.PHP_EOL;
		}else{
			$return_html .='<div class="popover_p"><strong>Alt tag:</strong> '.$program_content['inside_the_program_image_alt'].'</div>'.PHP_EOL;
		}

		$return_html .= '</div>';


	// ************** Wildcard **************** //
	}elseif($_GET['content'] == 'wildcard'){
		$sql = "SELECT `strat_comm_academic_programs`.`academic_program` , `strat_comm_programs_content`.`wildcard_headline`, `strat_comm_programs_content`.`wildcard_text`, `strat_comm_programs_content`.`use_wildcard_link`, `strat_comm_programs_content`.`wildcard_link_text`, `strat_comm_programs_content`.`wildcard_link_url` FROM `strat_comm_programs_content`, `strat_comm_academic_programs`WHERE `strat_comm_programs_content`.`academic_program_id` = `strat_comm_academic_programs`.`id` AND `strat_comm_programs_content`.`id` = '".$_GET['id']."';";
		$program_content = mysqli_single_row($sql);

		$return_html = '<div class="popover-heading">Wildcard: '.$program_content['academic_program'].'</div><div class="popover-body">';
		if ($program_content['wildcard_headline']){
			$return_html .='<div class="popover_p"><strong>Headline</strong>: '.$program_content['wildcard_headline'].'</div>';
		}else{
			$return_html .='<div class="popover_p"><strong><em>Needs headline</em></strong></div>'.PHP_EOL;
		}
		if (! $program_content['wildcard_text']){
			$return_html .='<div class="popover_p"><strong><em> Needs text.</em></strong></div>'.PHP_EOL;
		}else{
			$return_html .='<div class="popover_p"><strong> Needs text.:</strong> '.$program_content['wildcard_text'].'</div>'.PHP_EOL;
		}

		if ((! $program_content['wildcard_link_text']) AND (($program_content['use_wildcard_link'] == 1) OR ($program_content['use_wildcard_link'] == NULL))){
			$return_html .='<div class="popover_p"><strong><em>Needs link text.</em></strong></div>'.PHP_EOL;
		}elseif ($program_content['use_wildcard_link'] == 0){
			$return_html .='<div class="popover_p"><strong>Link text:</strong> None needed.</div>'.PHP_EOL;
		}else{
			$return_html .='<div class="popover_p"><strong>Link text:</strong> '.$program_content['wildcard_link_text'].'</div>'.PHP_EOL;
		}

		if ((! $program_content['wildcard_link_url']) AND (($program_content['use_wildcard_link'] == 1) OR ($program_content['use_wildcard_link'] == NULL))){
			$return_html .='<div class="popover_p"><strong><em>Needs url</em></strong></div>'.PHP_EOL;
		}elseif ($program_content['use_wildcard_link'] == 0){
			$return_html .='<div class="popover_p"><strong>URL:</strong> None needed.</div>'.PHP_EOL;
		}else{
			$return_html .='<p class="popover_p"><strong>URL:</strong> '.$program_content['wildcard_link_url'].PHP_EOL;
		}
	
		$return_html .= '</div>';

	// ************** Careers **************** //
	}elseif($_GET['content'] == 'careers'){
		$sql = "SELECT `strat_comm_academic_programs`.`academic_program` , `strat_comm_programs_content`.`careers_headline`, `strat_comm_programs_content`.`careers_text`, `strat_comm_programs_content`.`careers_link_text`, `strat_comm_programs_content`.`careers_link_url` FROM `strat_comm_programs_content`, `strat_comm_academic_programs`WHERE `strat_comm_programs_content`.`academic_program_id` = `strat_comm_academic_programs`.`id` AND `strat_comm_programs_content`.`id` = '".$_GET['id']."';";
		$program_content = mysqli_single_row($sql);

		$return_html = '<div class="popover-heading">Careers: '.$program_content['academic_program'].'</div><div class="popover-body">';
		if ($program_content['careers_headline']){
			$return_html .='<div class="popover_p"><strong>Headline</strong>: '.$program_content['careers_headline'].'</div>';
		}else{
			$return_html .='<div class="popover_p"><strong><em>Needs headline.</em></strong></div>'.PHP_EOL;
		}
		if (! $program_content['careers_text']){
			$return_html .='<div class="popover_p"><strong><em>Needs text.</em></strong></div>'.PHP_EOL;
		}else{
			$return_html .='<div class="popover_p"><strong>Text:</strong> '.$program_content['careers_text'].'</div>'.PHP_EOL;
		}
		if (! $program_content['careers_link_text']){
			$return_html .='<div class="popover_p"><strong><em>Needs link text.</em></strong></div>'.PHP_EOL;
		}else{
			$return_html .='<div class="popover_p"><strong>Link text:</strong> '.$program_content['careers_link_text'].'</div>'.PHP_EOL;
		}
		if (! $program_content['careers_link_url']){
			$return_html .='<div class="popover_p"><strong><em>Needs url</em></strong></div>'.PHP_EOL;
		}else{
			$return_html .='<p class="popover_p"><strong>URL:</strong> '.$program_content['careers_link_url'].PHP_EOL;
		}
	
		$return_html .= '</div>';
	}

	echo $return_html;

}

