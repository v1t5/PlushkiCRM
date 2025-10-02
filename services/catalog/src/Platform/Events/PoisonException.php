<?php

declare(strict_types=1);

namespace Plushki\Catalog\Platform\Events;

/**
 * Tells the Consumer to drop a message without requeue (nack, requeue=false).
 * Throw it for messages that can never succeed — malformed payloads, unknown
 * schemas. Any other throwable from a handler is treated as transient and the
 * message is requeued.
 */
final class PoisonException extends \RuntimeException
{
}
