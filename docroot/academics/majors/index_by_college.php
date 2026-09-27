<?php

// The address the CMS listing page had; same listing, from the database.
declare(strict_types=1);

$_GET += ['order' => 'college'];
require __DIR__ . '/_bootstrap.php';
$app->run(\Majors\Http\PublicMajorsController::class);
