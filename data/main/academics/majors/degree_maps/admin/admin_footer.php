	</main>
<!-- OU Search Ignore Start Here -->
<?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/footer.inc"); ?>
<?php include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/footcode.inc"); ?>
<?php
if (isset($footer_items) and ($footer_items)){
	foreach ($footer_items as $item){
		echo $item.PHP_EOL;
	}
}else{
	echo '<!-- where are the footer items? -->';
}
?>
<?php // include($_SERVER['DOCUMENT_ROOT'] . "/_resources/includes/analytics.inc"); ?>
<!-- OU Search Ignore End Here -->
</body>
</html>
