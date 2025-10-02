<?php

declare(strict_types=1);

namespace Plushki\Identity\App;

/** TokenPair is what register/login/refresh return to the client. */
final class TokenPair
{
    public function __construct(
        public string $accessToken,
        public \DateTimeImmutable $accessExpiry,
        public string $refreshToken,
        public \DateTimeImmutable $refreshExpiry,
    ) {
    }
}
