<?php

declare(strict_types=1);

namespace Plushki\Notifications\Domain;

/**
 * The domain errors notifications surfaces. The consumer maps them to
 * ack/nak/term decisions.
 */
enum ErrorCode: string
{
    case UnsupportedRecipient = 'unsupported recipient channel';
    case InvalidRecipient = 'invalid recipient';
    case SendFailed = 'send failed';
}
