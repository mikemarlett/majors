<?php

declare(strict_types=1);

namespace Majors\Support;

/**
 * Minimal PSR-4 autoloader. The servers have no composer, so this is the
 * whole dependency story: one namespace prefix mapped to one directory.
 */
final class Autoloader
{
    public static function register(string $prefix, string $baseDir): void
    {
        $prefix  = rtrim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, '/') . '/';

        spl_autoload_register(static function (string $class) use ($prefix, $baseDir): void {
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }
}
