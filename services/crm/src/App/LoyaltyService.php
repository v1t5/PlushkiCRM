<?php

declare(strict_types=1);

namespace Plushki\Crm\App;

use Psr\Log\LoggerInterface;
use Plushki\Crm\Domain\CustomerRef;
use Plushki\Crm\Domain\DomainException;
use Plushki\Crm\Domain\ErrorCode;
use Plushki\Crm\Domain\IdentityType;
use Plushki\Crm\Domain\Loyalty;
use Plushki\Crm\Platform\Events\Envelope;
use Plushki\Crm\Ports\LoyaltyRepo;
use Plushki\Crm\Ports\OutboxEvent;

/**
 * LoyaltyService owns the loyalty side: read access for HTTP and the
 * orders.v1.fulfilled handler. Idempotent at the DB layer (applied_order_events
 * PK on event_id).
 */
final class LoyaltyService
{
    private const LOYALTY_UPDATED = 'crm.v1.loyalty_updated';

    public function __construct(
        private readonly CustomerService $customers,
        private readonly LoyaltyRepo $loyalty,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function get(string $customerId): Loyalty
    {
        return $this->loyalty->get($customerId);
    }

    /**
     * Resolves the customer_ref (creating the per-tenant walk-in singleton on
     * demand) and bumps loyalty. Returns whether the apply was fresh. An
     * unattributable ref is logged and skipped (no error).
     */
    public function applyOrderFulfilled(FulfilledInput $in): bool
    {
        $customerId = $this->resolveCustomer($in->tenantId, $in->customerRef);
        if ($customerId === null) {
            $this->logger->info('unattributable customer_ref, skipping', [
                'customer_ref' => $in->customerRef,
                'event_id' => $in->eventId,
            ]);

            return false;
        }

        $evt = $this->loyaltyUpdatedEvent($customerId, $in);
        [$loyalty, $fresh] = $this->loyalty->applyOrderFulfilled(
            $in->eventId,
            $customerId,
            $in->orderId,
            $in->totalKopecks,
            $in->occurredAt,
            $evt,
        );
        if ($fresh) {
            $this->logger->info('loyalty bumped', [
                'customer_id' => $customerId,
                'order_id' => $in->orderId,
                'visit_count' => $loyalty->visitCount,
                'total_kopecks' => $loyalty->totalKopecks,
            ]);
        }

        return $fresh;
    }

    /** Resolve customer_ref → customer id, or null when unattributable. */
    private function resolveCustomer(string $tenantId, string $ref): ?string
    {
        $parsed = CustomerRef::split($ref);
        if ($parsed === null) {
            return null;
        }
        if ($parsed->type === IdentityType::PosWalkin) {
            // Every POS sale rolls into the per-tenant walk-in customer.
            return $this->customers->ensureWalkin($tenantId)->id;
        }
        try {
            [$c] = $this->customers->resolveByIdentity($tenantId, $parsed->type, $parsed->value);

            return $c->id;
        } catch (DomainException $e) {
            if ($e->errorCode === ErrorCode::IdentityNotFound) {
                // No customer registered yet — we don't auto-register here.
                return null;
            }
            throw $e;
        }
    }

    private function loyaltyUpdatedEvent(string $customerId, FulfilledInput $in): OutboxEvent
    {
        $envelope = Envelope::build(
            schema: self::LOYALTY_UPDATED,
            data: [
                'customer_id' => $customerId,
                'order_id' => $in->orderId,
                'customer_ref' => $in->customerRef,
                'total_kopecks' => $in->totalKopecks,
            ],
            actorType: 'system',
            actorId: 'crm',
            occurredAt: $in->occurredAt->format('Y-m-d\TH:i:s.uP'),
            tenantId: $in->tenantId,
        );

        return new OutboxEvent(
            eventId: $envelope->eventId,
            aggregateId: $customerId,
            aggregateType: 'loyalty',
            schema: self::LOYALTY_UPDATED,
            payload: $envelope->toJson(),
            occurredAt: $in->occurredAt,
            tenantId: $in->tenantId,
            traceId: $envelope->traceId,
        );
    }
}
