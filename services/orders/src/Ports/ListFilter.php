<?php

declare(strict_types=1);

namespace Plushki\Orders\Ports;

use Plushki\Orders\Domain\Channel;
use Plushki\Orders\Domain\Status;

/**
 * ListFilter is the input to OrderRepo::list. All fields except tenantId/limit
 * are optional (null = no filter). Time bounds are applied against created_at,
 * half-open: from <= created_at < to. Results are ordered by created_at DESC.
 */
final class ListFilter
{
    public function __construct(
        public string $tenantId = 'default',
        public ?string $customerRef = null,
        public ?Status $status = null,
        public ?Channel $channel = null,
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public int $limit = 0,
    ) {
    }
}
