<?php

declare(strict_types=1);

namespace Plushki\Inventory\Adapters\Events;

use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Plushki\Inventory\App\EventLine;
use Plushki\Inventory\App\MovementService;
use Plushki\Inventory\Domain\ItemKind;
use Plushki\Inventory\Platform\Events\Envelope;
use Plushki\Inventory\Platform\Events\PoisonException;

/**
 * ProductionConsumer posts a CONSUMED_BY_PRODUCTION movement per recipe line
 * from production.v1.task_completed.#. Production snapshots the recipe
 * (ingredient_id + qty_in_base) into the event, so inventory never calls catalog
 * at consume time.
 *
 * WarehouseID is fixed at construction (resolved by the command at startup).
 */
final class ProductionConsumer
{
    public function __construct(
        private readonly MovementService $movements,
        private readonly string $warehouseId,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Envelope $env): void
    {
        $d = $env->data;
        if (!Uuid::isValid($env->eventId)) {
            $this->logger->warning('event id parse', ['schema' => $env->schema]);

            throw new PoisonException('invalid event_id');
        }
        $occurredAt = self::parseTime($env->occurredAt);

        $lines = [];
        foreach ((array) ($d['lines'] ?? []) as $l) {
            if (!\is_array($l)) {
                continue;
            }
            $ingId = (string) ($l['ingredient_id'] ?? '');
            if (!Uuid::isValid($ingId)) {
                throw new PoisonException('invalid ingredient_id');
            }
            $lines[] = new EventLine(ItemKind::Ingredient, $ingId, (int) ($l['qty_in_base'] ?? 0));
        }

        $this->movements->applyTaskCompleted($env->eventId, $this->warehouseId, $occurredAt, $lines);
    }

    private static function parseTime(string $s): ?\DateTimeImmutable
    {
        if ($s === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($s);
        } catch (\Throwable) {
            return null;
        }
    }
}
