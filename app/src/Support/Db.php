<?php

declare(strict_types=1);

namespace Majors\Support;

use mysqli;
use RuntimeException;

/**
 * Database connection.
 *
 *  driver 'site'  — the WSU servers: include /data/www/config/functions.php
 *                   (which requires mysql.php and creates $mysqli). The include
 *                   runs inside a function so nothing else leaks into the global
 *                   scope; $mysqli is then exported to $GLOBALS because the shared
 *                   helpers in functions.php (and any legacy code) use `global $mysqli`.
 *  driver 'dsn'   — an explicit host/user/pass/name for local development.
 */
final class Db
{
    public static function connect(Config $config): mysqli
    {
        $driver = $config->string('db.driver', 'site');
        $mysqli = match ($driver) {
            'site' => self::fromSiteConfig($config->string('db.site_functions', '/data/www/config/functions.php')),
            'dsn'  => self::fromDsn($config),
            default => throw new RuntimeException("Unknown db.driver '{$driver}'."),
        };
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $mysqli->set_charset('utf8mb4');
        $GLOBALS['mysqli'] = $mysqli;
        return $mysqli;
    }

    private static function fromSiteConfig(string $functionsFile): mysqli
    {
        if (!is_file($functionsFile)) {
            throw new RuntimeException("Shared config not found: {$functionsFile}");
        }
        $mysqli = (static function () use ($functionsFile): mixed {
            require_once $functionsFile;
            // mysql.php creates $mysqli in the including scope; older builds
            // assign it to $GLOBALS directly. Accept either.
            return $mysqli ?? ($GLOBALS['mysqli'] ?? null);
        })();
        if (!$mysqli instanceof mysqli) {
            throw new RuntimeException('functions.php did not provide a mysqli connection.');
        }
        return $mysqli;
    }

    private static function fromDsn(Config $config): mysqli
    {
        return new mysqli(
            $config->string('db.host', '127.0.0.1'),
            $config->string('db.user'),
            $config->string('db.pass'),
            $config->string('db.name', 'formshandlerdb'),
            (int) $config->get('db.port', 3306),
            $config->string('db.socket') ?: null,
        );
    }
}
