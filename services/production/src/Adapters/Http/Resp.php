<?php

declare(strict_types=1);

namespace Plushki\Production\Adapters\Http;

use Plushki\Production\Domain\Plan;
use Plushki\Production\Domain\PlanItem;
use Plushki\Production\Domain\Task;

/**
 * Builds the JSON response bodies. Timestamps render RFC3339-with-offset;
 * plan_date as YYYY-MM-DD.
 */
final class Resp
{
    private static function ts(\DateTimeImmutable $dt): string
    {
        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP');
    }

    /**
     * @param list<PlanItem> $items
     * @return array<string, mixed>
     */
    public static function plan(Plan $p, array $items): array
    {
        $itemOut = [];
        foreach ($items as $it) {
            $itemOut[] = [
                'id' => $it->id,
                'product_id' => $it->productId,
                'qty' => $it->qty,
                'created_at' => self::ts($it->createdAt),
                'updated_at' => self::ts($it->updatedAt),
            ];
        }

        return [
            'id' => $p->id,
            'tenant_id' => $p->tenantId,
            'plan_date' => $p->planDate->format('Y-m-d'),
            'status' => $p->status->value,
            'published_at' => $p->publishedAt !== null ? self::ts($p->publishedAt) : null,
            'items' => $itemOut,
            'created_at' => self::ts($p->createdAt),
            'updated_at' => self::ts($p->updatedAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function task(Task $t): array
    {
        return [
            'id' => $t->id,
            'tenant_id' => $t->tenantId,
            'plan_id' => $t->planId,
            'product_id' => $t->productId,
            'qty' => $t->qty,
            'status' => $t->status->value,
            'baker_id' => $t->bakerId,
            'started_at' => $t->startedAt !== null ? self::ts($t->startedAt) : null,
            'completed_at' => $t->completedAt !== null ? self::ts($t->completedAt) : null,
            'created_at' => self::ts($t->createdAt),
            'updated_at' => self::ts($t->updatedAt),
        ];
    }
}
