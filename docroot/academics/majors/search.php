<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$app->run(\Majors\Http\PublicMajorsController::class);
