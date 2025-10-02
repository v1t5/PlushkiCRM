<?php

declare(strict_types=1);

namespace Plushki\Catalog\Domain;

/**
 * Carries an ErrorCode up to the HTTP adapter, which maps it to problem+json.
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