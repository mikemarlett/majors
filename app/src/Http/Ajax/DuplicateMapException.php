<?php

declare(strict_types=1);

namespace Majors\Http\Ajax;

/** Refusal to create a second map for the same degree and year; carries the existing map's id so the UI can offer to open it. */
final class DuplicateMapException extends ActionException
{
    public function __construct(string $message, public readonly int $existingId)
    {
        parent::__construct($message, 409);
    }
}
