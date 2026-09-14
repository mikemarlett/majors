<?php

declare(strict_types=1);

namespace Majors\Support;

/** JSON responses for the ajax endpoints: one envelope, one exit. */
final class Json
{
    public static function send(array $payload, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    public static function ok(array $extra = []): never
    {
        self::send(['success' => true] + $extra);
    }

    public static function fail(string $message, int $status = 400, array $extra = []): never
    {
        // Both key styles are emitted because the existing admin JS checks a mix
        // of response.success / response.status / response.error / response.message.
        self::send(['success' => false, 'status' => 'error', 'error' => $message, 'message' => $message] + $extra, $status);
    }
}
