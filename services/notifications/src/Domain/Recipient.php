<?php

declare(strict_types=1);

namespace Plushki\Notifications\Domain;

/**
 * A parsed customer_ref. The orders service stores customer_ref as
 * `<channel>:<id>` (e.g. "tg:42"), the smallest convention that keeps
 * notifications channel-agnostic without a CRM lookup yet.
 */
final class Recipient
{
    public function __construct(
        public readonly Channel $channel,
        public readonly string $id,
    ) {
    }

    /**
     * Splits "tg:<chat_id>" into a Recipient. Anything else throws so we don't
     * silently drop messages addressed to an unknown channel — the consumer
     * ack-and-skips on this error.
     *
     *   - no/leading/trailing ":"        -> InvalidRecipient
     *   - empty id after the ":"         -> InvalidRecipient
     *   - known prefix but channel n/a   -> UnsupportedRecipient
     */
    public static function parse(string $customerRef): self
    {
        $customerRef = trim($customerRef);
        $idx = strpos($customerRef, ':');
        if ($idx === false || $idx <= 0 || $idx === \strlen($customerRef) - 1) {
            throw DomainException::of(ErrorCode::InvalidRecipient);
        }
        $prefix = strtolower(substr($customerRef, 0, $idx));
        $id = trim(substr($customerRef, $idx + 1));
        if ($id === '') {
            throw DomainException::of(ErrorCode::InvalidRecipient);
        }
        $channel = Channel::tryFrom($prefix);
        if ($channel === null) {
            throw DomainException::of(ErrorCode::UnsupportedRecipient);
        }

        return new self($channel, $id);
    }
}
