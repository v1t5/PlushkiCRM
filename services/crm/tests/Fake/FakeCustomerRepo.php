<?php

declare(strict_types=1);

namespace Plushki\Crm\Tests\Fake;

use Plushki\Crm\Domain\Customer;
use Plushki\Crm\Domain\DomainException;
use Plushki\Crm\Domain\ErrorCode;
use Plushki\Crm\Domain\Identity;
use Plushki\Crm\Domain\IdentityType;
use Plushki\Crm\Ports\CustomerRepo;
use Plushki\Crm\Ports\CustomerWithIdentities;
use Plushki\Crm\Ports\OutboxEvent;

/**
 * Array-backed in-memory CustomerRepo. Enforces the IdentityConflict invariant
 * (an identity (tenant,type,value) may bind to only one customer) and records
 * the inline outbox events handed to write paths.
 */
final class FakeCustomerRepo implements CustomerRepo
{
    /** @var array<string, Customer> keyed by customer id */
    public array $customers = [];

    /** @var list<Identity> */
    public array $identities = [];

    /** @var list<OutboxEvent> */
    public array $events = [];

    /** @var array<string, Customer> keyed by tenant id */
    public array $walkins = [];

    public int $ensureWalkinCalls = 0;

    public function createWithIdentities(Customer $c, array $ids, OutboxEvent $evt): void
    {
        foreach ($ids as $id) {
            if ($this->findIdentity($id->tenantId, $id->type, $id->value) !== null) {
                throw DomainException::of(ErrorCode::IdentityConflict);
            }
        }
        $this->customers[$c->id] = $c;
        foreach ($ids as $id) {
            $this->identities[] = $id;
        }
        $this->events[] = $evt;
    }

    public function getById(string $id): Customer
    {
        return $this->customers[$id]
            ?? throw DomainException::of(ErrorCode::CustomerNotFound);
    }

    public function listIdentities(string $customerId): array
    {
        return array_values(array_filter(
            $this->identities,
            static fn (Identity $i): bool => $i->customerId === $customerId,
        ));
    }

    public function getByIdentity(string $tenantId, IdentityType $type, string $value): array
    {
        $identity = $this->findIdentity($tenantId, $type, $value);
        if ($identity === null) {
            throw DomainException::of(ErrorCode::IdentityNotFound);
        }

        return [$this->getById($identity->customerId), $identity];
    }

    public function list(string $tenantId, string $q, int $limit): array
    {
        $out = [];
        foreach ($this->customers as $c) {
            if ($c->tenantId !== $tenantId) {
                continue;
            }
            if ($q !== '' && !str_contains($c->displayName, $q)) {
                continue;
            }
            $out[] = new CustomerWithIdentities($c, $this->listIdentities($c->id), null);
            if (\count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    public function ensureWalkin(string $tenantId): Customer
    {
        ++$this->ensureWalkinCalls;
        if (!isset($this->walkins[$tenantId])) {
            $now = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
            $c = new Customer('walkin-' . $tenantId, $tenantId, 'Walk-in', $now, $now);
            $this->walkins[$tenantId] = $c;
            $this->customers[$c->id] = $c;
        }

        return $this->walkins[$tenantId];
    }

    private function findIdentity(string $tenantId, IdentityType $type, string $value): ?Identity
    {
        foreach ($this->identities as $i) {
            if ($i->tenantId === $tenantId && $i->type === $type && $i->value === $value) {
                return $i;
            }
        }

        return null;
    }
}
