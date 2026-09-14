<?php

declare(strict_types=1);

namespace Majors\Auth;

/** What a sign-in provider tells us about the person who just authenticated. */
final class Identity
{
    public function __construct(
        public readonly ?string $netid,     // 8-char WSU network id (e.g. a123b456), lower-case
        public readonly string $email,      // lower-case
        public readonly string $givenName,
        public readonly string $surname,
    ) {
    }

    public function displayName(): string
    {
        $n = trim($this->givenName . ' ' . $this->surname);
        return $n !== '' ? $n : $this->email;
    }
}
