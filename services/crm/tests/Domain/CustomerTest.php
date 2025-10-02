<?php

declare(strict_types=1);

namespace Plushki\Crm\Tests\Domain;

use Plushki\Crm\Domain\Customer;
use Plushki\Crm\Domain\Identity;
use Plushki\Crm\Domain\IdentityType;
use PHPUnit\Framework\TestCase;

final class CustomerTest extends TestCase
{
    public function testCustomerHoldsImmutableFields(): void
    {
        $created = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $updated = new \DateTimeImmutable('2026-01-02T10:00:00+00:00');
        $c = new Customer('cust-1', 'acme', 'Jane Doe', $created, $updated);

        self::assertSame('cust-1', $c->id);
        self::assertSame('acme', $c->tenantId);
        self::assertSame('Jane Doe', $c->displayName);
        self::assertSame($created, $c->createdAt);
        self::assertSame($updated, $c->updatedAt);
    }

    public function testIdentityHoldsImmutableFields(): void
    {
        $created = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $id = new Identity('id-1', 'acme', 'cust-1', IdentityType::TG, '42', null, $created);

        self::assertSame('id-1', $id->id);
        self::assertSame('acme', $id->tenantId);
        self::assertSame('cust-1', $id->customerId);
        self::assertSame(IdentityType::TG, $id->type);
        self::assertSame('42', $id->value);
        self::assertNull($id->verifiedAt);
        self::assertSame($created, $id->createdAt);
    }
}
