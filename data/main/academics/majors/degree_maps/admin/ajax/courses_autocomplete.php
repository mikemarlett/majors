<?php
header('Content-Type: application/json');
require_once('../map_edit_functions.php');

$term = isset($_GET['term']) ? trim($_GET['term']) : '';
if (empty($term)) {
    // return an empty JSON array if no term
    echo json_encode([]);
    exit;
}
// We'll do a wildcard match, but also guard against injection
// We'll do manual escaping or parameter binding:
$searchQ = '%'.$term.'%';

$sql = "
  SELECT id, 
         scbcrse_subj_code, 
         scbcrse_crse_numb, 
         scbcrse_title,
         crs_longtitle,
         credit_hr_low,
         credit_hr_high
    FROM courses
   WHERE (scbcrse_title LIKE ? 
          OR crs_longtitle LIKE ?
          OR CONCAT(scbcrse_subj_code, ' ', scbcrse_crse_numb) LIKE ?)
    ORDER BY scbcrse_subj_code ASC, scbcrse_crse_numb ASC
   LIMIT 25
";

if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param('sss', $searchQ, $searchQ, $searchQ);
    $stmt->execute();
    $stmt->bind_result($id, $subj, $numb, $title, $longTitle, $hrLow, $hrHigh);
    $results = [];
    while ($stmt->fetch()) {
        // We define how to label the course in the dropdown
        $label = $subj.' '.$numb.': '.$longTitle;
        // Suppose we have $hrLow and $hrHigh from the database
        $hrLowFloat = floatval($hrLow);
        if (floor($hrLowFloat) === $hrLowFloat) {
            // It's a whole number (like 4.0), so turn it into an integer string "4"
            $hrLowClean = (string)(int)$hrLowFloat;
        } else {
            // Keep decimals, but maybe remove trailing zeros (optional)
            // e.g. 4.50 => "4.5"
            $hrLowClean = rtrim(rtrim($hrLow, '0'), '.');
        }

        $hrHighFloat = floatval($hrHigh);
        if (!empty($hrHigh) && $hrHighFloat > $hrLowFloat) {
            if (floor($hrHighFloat) === $hrHighFloat) {
                $hrHighClean = (string)(int)$hrHighFloat;
            } else {
                $hrHighClean = rtrim(rtrim($hrHigh, '0'), '.');
            }
            
            // Combine them (e.g. "3-4" or "3.5-4.25")
            $hours = $hrLowClean . '-' . $hrHighClean;
        } else {
            // Just $hrLow alone
            $hours = $hrLowClean;
        }


        // Return an associative array for each row
        $results[] = [
            'id'    => $id,
            'label' => $label,  // jQuery UI Autocomplete uses 'label' for display
            'value' => $label,  // 'value' will be set in the <input>
            'hours' => $hours,
            'fullTitle' => $longTitle,
            'scbcrse_subj_code' => $subj,
            'scbcrse_crse_numb' => $numb,
        ];
    }
    $stmt->close();
    echo json_encode($results);
} else {
    // If the statement couldn't be prepared, return an error or empty
    echo json_encode([]);
}