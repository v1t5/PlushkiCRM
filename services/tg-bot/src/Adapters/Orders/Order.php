<?php

declare(strict_types=1);

namespace Plushki\TgBot\Adapters\Orders;

/** The slice of an orders-service order the bot renders. */
final class Order
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly string $channel,
        public readonly string $customerRef,
        public readonly int $totalKopecks,
    ) {
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            id: (string) ($a['id'] ?? ''),
            status: (string) ($a['status'] ?? ''),
            channel: (string) ($a['channel'] ?? ''),
            customerRef: (string) ($a['customer_ref'] ?? ''),
            totalKopecks: (int) ($a['total_kopecks'] ?? 0),
        );
    }
}
