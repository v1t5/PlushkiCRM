<?php

declare(strict_types=1);

namespace Plushki\Catalog\Tests\Domain;

use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use PHPUnit\Framework\TestCase;

final class DomainExceptionTest extends TestCase
{
    public function testOfUsesErrorCodeValueAsMessage(): void
    {
        $e = DomainException::of(ErrorCode::ProductNotFound);

        self::assertSame(ErrorCode::ProductNotFound, $e->errorCode);
        self::assertSame('product not found', $e->getMessage());
    }

    public function testExplicitMessageOverridesDefault(): void
    {
        $e = new DomainException(ErrorCode::InvalidSKU, 'custom detail');

        self::assertSame(ErrorCode::InvalidSKU, $e->errorCode);
        self::assertSame('custom detail', $e->getMessage());
    }

    public function testIsRuntimeException(): void
    {
        self::assertInstanceOf(\RuntimeException::class, DomainException::of(ErrorCode::InvalidQty));
    }
}
