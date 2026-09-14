<?php
require_once('../map_edit_functions.php');

if (!isset($_REQUEST['degree_map_id'])) {
    echo "Error: Missing degree_map_id.";
    exit;
}

$degree_map_id = intval($_REQUEST['degree_map_id']);
echo display_edit_degree_map($degree_map_id);
