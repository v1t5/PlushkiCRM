<?php

declare(strict_types=1);

namespace Plushki\Notifications\Domain;

/**
 * Lets the app layer decide ack vs nak vs term without leaking broker-specific
 * handling.
 *   - Ack:  done (sent, duplicate, or deliberately skipped),
 *   - Nak:  transient, retry later (reserve/send failure),
 *   - Term: won't help to retry (malformed envelope, unsupported channel).
 * The consumer adapter maps these onto the generic Consumer's
 * return / Throwable / PoisonException contract.
 */
enum Outcome
{
    case Ack;
    case Nak;
    case Term;
}
