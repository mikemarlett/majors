<?php

/**
 * Idempotent schema migration for the Majors / Degree Maps app.
 *
 *   php bin/migrate.php            apply what is missing
 *   php bin/migrate.php --dry-run  print the statements it would run
 *
 * Uses the same connection the app uses (config/app.php + app.local.php), so
 * on a server it runs through /data/www/config/functions.php. Inspects
 * information_schema first: safe to re-run, and tolerant of the drift between
 * the www-test copy of majors_users (which has `role`) and older copies
 * (which have `permission_level`).
 */

declare(strict_types=1);

$dry = in_array('--dry-run', $argv, true);
$app = require dirname(__DIR__) . '/app/bootstrap.php';
$db  = $app->db();
$schema = $db->query('SELECT DATABASE()')->fetch_row()[0];

$columns = static function (string $table) use ($db, $schema): array {
    $stmt = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $stmt->bind_param('ss', $schema, $table);
    $stmt->execute();
    $cols = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME');
    $stmt->close();
    return $cols;
};
$indexes = static function (string $table) use ($db, $schema): array {
    $stmt = $db->prepare('SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $stmt->bind_param('ss', $schema, $table);
    $stmt->execute();
    $idx = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'INDEX_NAME');
    $stmt->close();
    return $idx;
};
$run = static function (string $sql) use ($db, $dry): void {
    echo ($dry ? 'would run: ' : 'running:   ') . preg_replace('/\s+/', ' ', trim($sql)) . "\n";
    if (!$dry) {
        $db->query($sql);
    }
};

$users = $columns('majors_users');
if ($users === []) {
    fwrite(STDERR, "majors_users does not exist in {$schema}; nothing to do.\n");
    exit(1);
}

if (!in_array('netid', $users, true)) {
    $run('ALTER TABLE `majors_users` ADD COLUMN `netid` CHAR(8) NULL AFTER `email`');
}
if (!in_array('role', $users, true)) {
    $run("ALTER TABLE `majors_users` ADD COLUMN `role` ENUM('advisor','marketing','super_admin','none') NOT NULL DEFAULT 'none' AFTER `netid`");
    if (in_array('permission_level', $users, true)) {
        $run("UPDATE `majors_users` SET `role` = CASE LOWER(`permission_level`)
                WHEN 'administrator' THEN 'super_admin' WHEN 'admin' THEN 'super_admin'
                WHEN 'editor' THEN 'advisor' WHEN 'approver' THEN 'advisor' ELSE 'none' END");
    }
} else {
    // Column exists but may be a VARCHAR with legacy values; normalize then constrain.
    $run("UPDATE `majors_users` SET `role` = 'super_admin' WHERE LOWER(`role`) IN ('admin','administrator')");
    $run("UPDATE `majors_users` SET `role` = 'advisor' WHERE LOWER(`role`) IN ('editor','approver')");
    $run("UPDATE `majors_users` SET `role` = 'none' WHERE `role` NOT IN ('advisor','marketing','super_admin','none') OR `role` IS NULL");
    $run("ALTER TABLE `majors_users` MODIFY COLUMN `role` ENUM('advisor','marketing','super_admin','none') NOT NULL DEFAULT 'none'");
}
if (!in_array('is_active', $users, true)) {
    $run('ALTER TABLE `majors_users` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1');
}
if (!in_array('last_login_at', $users, true)) {
    $run('ALTER TABLE `majors_users` ADD COLUMN `last_login_at` DATETIME NULL');
}
foreach (['created_at', 'updated_at'] as $c) {
    if (!in_array($c, $users, true)) {
        $run("ALTER TABLE `majors_users` ADD COLUMN `{$c}` DATETIME NULL");
    }
}
if (in_array('ouauth_id', $users, true)) {
    $run("UPDATE `majors_users` SET `netid` = LOWER(`ouauth_id`)
           WHERE `netid` IS NULL AND `ouauth_id` REGEXP '^[A-Za-z][A-Za-z0-9]{2,7}$'");
}
$uidx = $indexes('majors_users');
if (!in_array('uq_majors_users_netid', $uidx, true)) {
    $run('ALTER TABLE `majors_users` ADD UNIQUE KEY `uq_majors_users_netid` (`netid`)');
}
if (!in_array('uq_majors_users_email', $uidx, true)) {
    // Duplicate emails would make this fail; report instead of guessing.
    $dupes = $db->query('SELECT `email`, COUNT(*) c FROM `majors_users` GROUP BY `email` HAVING c > 1')->fetch_all(MYSQLI_ASSOC);
    if ($dupes === []) {
        $run('ALTER TABLE `majors_users` ADD UNIQUE KEY `uq_majors_users_email` (`email`)');
    } else {
        echo "skipped unique email index: duplicate emails present: " . implode(', ', array_column($dupes, 'email')) . "\n";
    }
}

$run('CREATE TABLE IF NOT EXISTS `majors_user_colleges` (
        `user_id` INT UNSIGNED NOT NULL, `college_id` INT UNSIGNED NOT NULL,
        PRIMARY KEY (`user_id`, `college_id`), KEY `idx_college` (`college_id`)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
if (in_array('default_college_id', $users, true)) {
    $run('INSERT IGNORE INTO `majors_user_colleges` (`user_id`, `college_id`)
          SELECT `id`, `default_college_id` FROM `majors_users` WHERE `default_college_id` > 0');
}

$dmIdx = $indexes('degree_maps');
if (!in_array('idx_dm_year_college', $dmIdx, true)) {
    $run('ALTER TABLE `degree_maps` ADD INDEX `idx_dm_year_college` (`academic_year`, `college`)');
}
if (!in_array('idx_dm_family', $dmIdx, true)) {
    $run('ALTER TABLE `degree_maps` ADD INDEX `idx_dm_family` (`major`(100), `degree_type`(20), `college`(100))');
}
if (!in_array('idx_dm_program', $dmIdx, true)) {
    $run('ALTER TABLE `degree_maps` ADD INDEX `idx_dm_program` (`program_id`)');
}

echo $dry ? "dry run complete\n" : "migration complete\n";
