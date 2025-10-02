<?php

declare(strict_types=1);

namespace Plushki\Crm\Ports;

use Plushki\Crm\Domain\Customer;
use Plushki\Crm\Domain\Identity;
use Plushki\Crm\Domain\IdentityType;

/**
 * CustomerRepo manages the canonical customer + identity rows. The
 * customer_registered outbox row is written in the same txn as the customer +
 * identities.
 */
interface CustomerRepo
{
    /**
     * @param list<Identity> $ids
     * @throws \Plushki\Crm\Domain\DomainException IdentityConflict
     */
    public function createWithIdentities(Customer $c, array $ids, OutboxEvent $evt): void;

    /** @throws \Plushki\Crm\Domain\DomainException CustomerNotFound */
    public function getById(string $id): Customer;

    /** @return list<Identity> */
    public function listIdentities(string $customerId): array;

    /**
     * @return array{0: Customer, 1: Identity}
     * @throws \Plushki\Crm\Domain\DomainException IdentityNotFound
     */
    public function getByIdentity(string $tenantId, IdentityType $type, string $value): array;

    /** @return list<CustomerWithIdentities> */
    public function list(string $tenantId, string $q, int $limit): array;

    /** Returns (creating if necessary) the singleton walk-in customer for a tenant. */
    public function ensureWalkin(string $tenantId): Customer;
}
