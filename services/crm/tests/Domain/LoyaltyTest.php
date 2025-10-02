<?php

declare(strict_types=1);

namespace Plushki\Crm\Tests\Domain;

use Plushki\Crm\Domain\Loyalty;
use PHPUnit\Framework\TestCase;

final class LoyaltyTest extends TestCase
{
    public function testLoyaltyHoldsIntTotalsInKopecks(): void
    {
        $last = new \DateTimeImmutable('2026-03-01T12:00:00+00:00');
        $updated = new \DateTimeImmutable('2026-03-01T12:00:01+00:00');
        $l = new Loyalty('cust-1', 'acme', 3, 150000, $last, $updated);

        self::assertSame('cust-1', $l->customerId);
        self::assertSame('acme', $l->tenantId);
        self::assertSame(3, $l->visitCount);
        self::assertSame(150000, $l->totalKopecks);
        self::assertSame($last, $l->lastVisitAt);
        self::assertSame($updated, $l->updatedAt);
    }

    public function testFreshLoyaltyMayHaveNullLastVisit(): void
    {
        $updated = new \DateTimeImmutable('2026-03-01T12:00:00+00:00');
        $l = new Loyalty('cust-2', 'acme', 0, 0, null, $updated);

        self::assertSame(0, $l->visitCount);
        self::assertSame(0, $l->totalKopecks);
        self::assertNull($l->lastVisitAt);
    }

    /**
     * Totals are integer kopecks; values are exact (no float drift).
     */
    public function testTotalsAreExactIntegers(): void
    {
        $updated = new \DateTimeImmutable('2026-03-01T12:00:00+00:00');
        $l = new Loyalty('cust-3', 'acme', 2, 1990 + 1990, null, $updated);

        self::assertSame(3980, $l->totalKopecks);
    }
}
