<?php

declare(strict_types=1);

namespace Plushki\Reporting\Adapters\Events;

use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Plushki\Reporting\Platform\Events\Envelope;
use Plushki\Reporting\Platform\Events\PoisonException;
use Plushki\Reporting\Ports\ProjectionRepo;
use Plushki\Reporting\Ports\StockLowIn;

/** Appends inventory.v1.stock_low.# into stock_low_events. */
final class StockLowConsumer
{
    public function __construct(
        private readonly ProjectionRepo $repo,
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
        $ingredientId = (string) ($d['ingredient_id'] ?? '');
        if (!Uuid::isValid($ingredientId)) {
            throw new PoisonException('invalid ingredient_id');
        }
        $warehouseRaw = (string) ($d['warehouse_id'] ?? '');
        $warehouseId = Uuid::isValid($warehouseRaw) ? $warehouseRaw : null;

        $this->repo->applyStockLow(new StockLowIn(
            eventId: $env->eventId,
            tenantId: $env->tenantId !== '' ? $env->tenantId : 'default',
            ingredientId: $ingredientId,
            sku: (string) ($d['sku'] ?? ''),
            name: (string) ($d['name'] ?? ''),
            warehouseId: $warehouseId,
            thresholdQtyInBase: (int) ($d['threshold_qty_in_base'] ?? 0),
            currentQtyInBase: (int) ($d['qty_in_base'] ?? 0),
            defaultUnitCode: (string) ($d['default_unit_code'] ?? ''),
            defaultUnitFactor: (int) ($d['default_unit_factor'] ?? 1),
            occurredAt: self::parseTime($env->occurredAt),
        ));
    }

    private static function parseTime(string $s): \DateTimeImmutable
    {
        if ($s !== '') {
            try {
                return new \DateTimeImmutable($s);
            } catch (\Throwable) {
            }
        }

        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
