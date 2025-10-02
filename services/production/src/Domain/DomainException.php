<?php

declare(strict_types=1);

namespace Plushki\Production\Domain;

/**
 * DomainException carries an ErrorCode up to the HTTP adapter / consumer, which
 * maps it to problem+json or an ack/nak decision.
 */
final class DomainException extends \RuntimeException
{
    public function __construct(public readonly ErrorCode $errorCode, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $errorCode->value);
    }

    public static function of(ErrorCode $code): self
    {
        return new self($code);
    }
}
