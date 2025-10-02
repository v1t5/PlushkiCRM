<?php

declare(strict_types=1);

namespace Plushki\Orders\Tests\Domain;

use Plushki\Orders\Domain\Item;
use PHPUnit\Framework\TestCase;

final class ItemTest extends TestCase
{
    public function testSubtotalIsPriceTimesQtyInKopecks(): void
    {
        $it = new Item(1, 'prod-1', 'Croissant', 'CRS-1', 15000, 3);
        self::assertSame(45000, $it->subtotalKopecks());
    }

    public function testSubtotalWithQtyOne(): void
    {
        $it = new Item(1, 'prod-1', 'Bun', 'BUN-1', 9900, 1);
        self::assertSame(9900, $it->subtotalKopecks());
    }

    public function testSubtotalIsIntegerExact(): void
    {
        $it = new Item(2, 'prod-2', 'Cake', 'CKE-2', 333, 7);
        $sub = $it->subtotalKopecks();
        self::assertIsInt($sub);
        self::assertSame(2331, $sub);
    }
}
