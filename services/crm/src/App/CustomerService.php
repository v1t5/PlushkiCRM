<?php

declare(strict_types=1);

namespace Plushki\Crm\App;

use Symfony\Component\Uid\Uuid;
use Plushki\Crm\Domain\Customer;
use Plushki\Crm\Domain\DomainException;
use Plushki\Crm\Domain\ErrorCode;
use Plushki\Crm\Domain\Identity;
use Plushki\Crm\Domain\IdentityType;
use Plushki\Crm\Platform\Events\Envelope;
use Plushki\Crm\Ports\CustomerRepo;
use Plushki\Crm\Ports\OutboxEvent;

/**
 * CustomerService owns customer registration + identity lookup. It is the only
 * path that writes customers/identities; the loyalty consumer resolves
 * (ensureWalkin / getByIdentity) but never creates a real customer for an
 * unknown ref.
 */
final class CustomerService
{
    private const CUSTOMER_REGISTERED = 'crm.v1.customer_registered';

    public function __construct(private readonly CustomerRepo $repo)
    {
    }

    /**
     * @param list<RegisterIdentity> $identities
     * @return array{0: Customer, 1: list<Identity>}
     */
    public function register(string $tenantId, string $displayName, array $identities): array
    {
        if ($tenantId === '') {
            $tenantId = 'default';
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $cid = Uuid::v7()->toRfc4122();
        $c = new Customer($cid, $tenantId, trim($displayName), $now, $now);

        $ids = [];
        foreach ($identities as $ri) {
            $val = trim($ri->value);
            if ($val === '') {
                throw DomainException::of(ErrorCode::IdentityValueRequired);
            }
            $ids[] = new Identity(Uuid::v7()->toRfc4122(), $tenantId, $cid, $ri->type, $val, null, $now);
        }

        $evt = $this->customerRegisteredEvent($c, $ids);
        $this->repo->createWithIdentities($c, $ids, $evt);

        return [$c, $ids];
    }

    /** @return array{0: Customer, 1: list<Identity>} */
    public function get(string $id): array
    {
        $c = $this->repo->getById($id);

        return [$c, $this->repo->listIdentities($id)];
    }

    /** @return list<\Plushki\Crm\Ports\CustomerWithIdentities> */
    public function list(string $tenantId, string $q, int $limit): array
    {
        if ($tenantId === '') {
            $tenantId = 'default';
        }

        return $this->repo->list($tenantId, trim($q), $limit);
    }

    /** @return array{0: Customer, 1: Identity} */
    public function resolveByIdentity(string $tenantId, IdentityType $type, string $value): array
    {
        if ($tenantId === '') {
            $tenantId = 'default';
        }

        return $this->repo->getByIdentity($tenantId, $type, $value);
    }

    public function ensureWalkin(string $tenantId): Customer
    {
        if ($tenantId === '') {
            $tenantId = 'default';
        }

        return $this->repo->ensureWalkin($tenantId);
    }

    /** @param list<Identity> $ids */
    private function customerRegisteredEvent(Customer $c, array $ids): OutboxEvent
    {
        $idData = [];
        foreach ($ids as $id) {
            $idData[] = ['type' => $id->type->value, 'value' => $id->value];
        }
        $envelope = Envelope::build(
            schema: self::CUSTOMER_REGISTERED,
            data: ['customer_id' => $c->id, 'display_name' => $c->displayName, 'identities' => $idData],
            actorType: 'system',
            actorId: 'crm',
            occurredAt: $c->createdAt->format('Y-m-d\TH:i:s.uP'),
            tenantId: $c->tenantId,
        );

        return new OutboxEvent(
            eventId: $envelope->eventId,
            aggregateId: $c->id,
            aggregateType: 'customer',
            schema: self::CUSTOMER_REGISTERED,
            payload: $envelope->toJson(),
            occurredAt: $c->createdAt,
            tenantId: $c->tenantId,
            traceId: $envelope->traceId,
        );
    }
}
