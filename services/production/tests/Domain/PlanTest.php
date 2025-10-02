<?php

declare(strict_types=1);

namespace Plushki\Production\Tests\Domain;

use Plushki\Production\Domain\Plan;
use Plushki\Production\Domain\PlanItem;
use Plushki\Production\Domain\PlanStatus;
use PHPUnit\Framework\TestCase;

final class PlanTest extends TestCase
{
    public function testCreateStartsDraft(): void
    {
        $date = new \DateTimeImmutable('2026-06-06', new \DateTimeZone('UTC'));
        $plan = Plan::create($date);

        self::assertSame(PlanStatus::Draft, $plan->status);
        self::assertFalse($plan->isPublished());
        self::assertSame('default', $plan->tenantId);
        self::assertNull($plan->publishedAt);
        self::assertSame($date, $plan->planDate);
        self::assertNotSame('', $plan->id);
    }

    public function testIsPublishedReflectsStatus(): void
    {
        $plan = Plan::create(new \DateTimeImmutable('2026-06-06', new \DateTimeZone('UTC')));
        self::assertFalse($plan->isPublished());

        $plan->status = PlanStatus::Published;
        self::assertTrue($plan->isPublished());
    }

    public function testPlanStatusEnumValues(): void
    {
        self::assertSame('draft', PlanStatus::Draft->value);
        self::assertSame('published', PlanStatus::Published->value);
    }

    public function testPlanItemHoldsAccumulatedQty(): void
    {
        $now = new \DateTimeImmutable('2026-06-06T00:00:00Z');
        $item = new PlanItem('item-1', 'plan-1', 'product-1', 12, $now, $now);

        self::assertSame('item-1', $item->id);
        self::assertSame('plan-1', $item->planId);
        self::assertSame('product-1', $item->productId);
        self::assertSame(12, $item->qty);
    }
}
