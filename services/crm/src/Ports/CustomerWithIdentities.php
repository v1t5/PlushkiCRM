<?php

declare(strict_types=1);

namespace Plushki\Crm\Ports;

use Plushki\Crm\Domain\Customer;
use Plushki\Crm\Domain\Identity;
use Plushki\Crm\Domain\Loyalty;

/**
 * CustomerWithIdentities is a customer joined with its identities (and loyalty
 * totals, when available) for list endpoints.
 */
final class CustomerWithIdentities
{
    /** @param list<Identity> $identities */
    public function __construct(
        public Customer $customer,
        public array $identities,
        public ?Loyalty $loyalty,
    ) {
    }
}
