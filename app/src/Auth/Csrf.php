<?php

declare(strict_types=1);

namespace Majors\Auth;

/** Per-session CSRF token; sent by the admin JS as X-CSRF-Token (or _csrf). */
final class Csrf
{
    private const KEY = '_csrf';

    public function token(): string
    {
        if (empty($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public function valid(?string $candidate): bool
    {
        if ($candidate === null || $candidate === '' || empty($_SESSION[self::KEY])) {
            return false;
        }
        return hash_equals((string) $_SESSION[self::KEY], $candidate);
    }

    public function rotate(): void
    {
        unset($_SESSION[self::KEY]);
    }
}
