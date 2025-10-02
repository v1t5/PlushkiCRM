<?php

declare(strict_types=1);

namespace Plushki\Crm\Tests\Domain;

use Plushki\Crm\Domain\CustomerRef;
use Plushki\Crm\Domain\IdentityType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CustomerRefTest extends TestCase
{
    /**
     * @return iterable<string, array{string, IdentityType, string}>
     */
    public static function validProvider(): iterable
    {
        yield 'tg' => ['tg:42', IdentityType::TG, '42'];
        yield 'phone' => ['phone:+79991234567', IdentityType::Phone, '+79991234567'];
        yield 'email' => ['email:a@b.co', IdentityType::Email, 'a@b.co'];
        yield 'pos-walkin-marker' => ['pos:walk-in', IdentityType::PosWalkin, 'walk-in'];
        // Only the first colon splits; the rest is preserved verbatim as value.
        yield 'value-with-colon' => ['email:weird:value', IdentityType::Email, 'weird:value'];
    }

    #[DataProvider('validProvider')]
    public function testSplitParsesKnownPrefixes(string $ref, IdentityType $type, string $value): void
    {
        $parsed = CustomerRef::split($ref);

        self::assertInstanceOf(CustomerRef::class, $parsed);
        self::assertSame($type, $parsed->type);
        self::assertSame($value, $parsed->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nullProvider(): iterable
    {
        yield 'no-colon' => ['walkin'];
        yield 'unknown-prefix' => ['sms:123'];
        yield 'empty' => [''];
        yield 'leading-colon' => [':42'];        // idx === 0
        yield 'trailing-colon' => ['tg:'];        // idx === len-1, empty value
        yield 'colon-only' => [':'];
    }

    #[DataProvider('nullProvider')]
    public function testSplitReturnsNullForUnattributable(string $ref): void
    {
        self::assertNull(CustomerRef::split($ref));
    }

    public function testPosMarkerIsPreservedNotNormalized(): void
    {
        // The marker after "pos:" is kept as the value (mapping happens elsewhere).
        $parsed = CustomerRef::split('pos:register-7');

        self::assertNotNull($parsed);
        self::assertSame(IdentityType::PosWalkin, $parsed->type);
        self::assertSame('register-7', $parsed->value);
    }
}
