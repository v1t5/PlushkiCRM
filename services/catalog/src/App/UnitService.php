<?php

declare(strict_types=1);

namespace Plushki\Catalog\App;

use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Domain\Unit;
use Plushki\Catalog\Platform\Events\Envelope;
use Plushki\Catalog\Ports\OutboxEvent;
use Plushki\Catalog\Ports\OutboxRepo;
use Plushki\Catalog\Ports\UnitRepo;

/**
 * Manages units of measure. A non-base unit must resolve to a unit that is
 * itself a base — chains are not supported.
 */
final class UnitService
{
    public function __construct(
        private readonly UnitRepo $units,
        private readonly OutboxRepo $outbox,
    ) {
    }

    public function create(string $code, string $name, ?string $baseUnitId, int $factor): Unit
    {
        if ($baseUnitId !== null) {
            $base = $this->units->getById($baseUnitId);
            if ($base->isArchived()) {
                throw DomainException::of(ErrorCode::UnitArchived);
            }
            if (!$base->isBase()) {
                throw DomainException::of(ErrorCode::BaseUnitMustBeBase);
            }
        }
        $u = Unit::create($code, $name, $baseUnitId, $factor);
        $this->outbox->insertWithUnit($u, $this->unitCreatedEvent($u));

        return $u;
    }

    public function get(string $id): Unit
    {
        return $this->units->getById($id);
    }

    /** @return list<Unit> */
    public function list(string $tenantId): array
    {
        return $this->units->listActive($tenantId !== '' ? $tenantId : 'default');
    }

    private function unitCreatedEvent(Unit $u): OutboxEvent
    {
        $schema = 'catalog.v1.unit_created';
        $data = [
            'unit_id' => $u->id,
            'code' => $u->code,
            'name' => $u->name,
            'factor' => $u->factor,
        ];
        if ($u->baseUnitId !== null) {
            $data['base_unit_id'] = $u->baseUnitId;
        }
        $env = Envelope::build(
            schema: $schema,
            data: $data,
            actorType: 'system',
            actorId: 'catalog',
            occurredAt: $u->createdAt->format('Y-m-d\TH:i:s.uP'),
            tenantId: $u->tenantId,
        );

        return new OutboxEvent(
            eventId: $env->eventId,
            aggregateId: $u->id,
            aggregateType: 'unit',
            schema: $schema,
            payload: $env->toJson(),
            occurredAt: $u->createdAt,
            tenantId: $u->tenantId,
            traceId: $env->traceId,
        );
    }
}
