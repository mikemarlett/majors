<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/_bootstrap.php';
$app->run(\Majors\Http\AdminHelpController::class);
