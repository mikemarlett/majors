<?php

declare(strict_types=1);

namespace Majors\Http\Ajax;

/** A user-facing failure from an ajax action; becomes the JSON error envelope. */
class ActionException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400)
    {
        parent::__construct($message);
    }
}
