<?php

declare(strict_types=1);

namespace Plushki\Notifications\Domain;

/**
 * Carries an ErrorCode out of the domain/app layers. The consumer adapter maps
 * the code to an ack/nak/term outcome; the telegram sender raises SendFailed for
 * a retryable network error.
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
