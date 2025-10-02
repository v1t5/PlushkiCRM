<?php

declare(strict_types=1);

namespace Plushki\Orders\Tests\Domain;

use Plushki\Orders\Domain\Status;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StatusTest extends TestCase
{
    public function testEnumCases(): void
    {
        self::assertSame('placed', Status::Placed->value);
        self::assertSame('confirmed', Status::Confirmed->value);
        self::assertSame('cancelled', Status::Cancelled->value);
        self::assertSame('fulfilled', Status::Fulfilled->value);
        self::assertCount(4, Status::cases());
    }

    /**
     * Full transition matrix: every (from, to) pair across the four states.
     */
    #[DataProvider('transitionMatrix')]
    public function testCanTransitionTo(Status $from, Status $to, bool $expected): void
    {
        self::assertSame($expected, $from->canTransitionTo($to));
    }

    /**
     * @return iterable<string, array{Status, Status, bool}>
     */
    public static function transitionMatrix(): iterable
    {
        // Allowed.
        yield 'placed->confirmed' => [Status::Placed, Status::Confirmed, true];
        yield 'placed->cancelled' => [Status::Placed, Status::Cancelled, true];
        yield 'confirmed->fulfilled' => [Status::Confirmed, Status::Fulfilled, true];
        yield 'confirmed->cancelled' => [Status::Confirmed, Status::Cancelled, true];

        // Illegal from Placed.
        yield 'placed->fulfilled' => [Status::Placed, Status::Fulfilled, false];
        yield 'placed->placed' => [Status::Placed, Status::Placed, false];

        // Illegal from Confirmed.
        yield 'confirmed->placed' => [Status::Confirmed, Status::Placed, false];
        yield 'confirmed->confirmed' => [Status::Confirmed, Status::Confirmed, false];

        // Terminal: Cancelled rejects everything.
        yield 'cancelled->placed' => [Status::Cancelled, Status::Placed, false];
        yield 'cancelled->confirmed' => [Status::Cancelled, Status::Confirmed, false];
        yield 'cancelled->cancelled' => [Status::Cancelled, Status::Cancelled, false];
        yield 'cancelled->fulfilled' => [Status::Cancelled, Status::Fulfilled, false];

        // Terminal: Fulfilled rejects everything.
        yield 'fulfilled->placed' => [Status::Fulfilled, Status::Placed, false];
        yield 'fulfilled->confirmed' => [Status::Fulfilled, Status::Confirmed, false];
        yield 'fulfilled->cancelled' => [Status::Fulfilled, Status::Cancelled, false];
        yield 'fulfilled->fulfilled' => [Status::Fulfilled, Status::Fulfilled, false];
    }

    #[DataProvider('validRaw')]
    public function testIsValidAcceptsKnownStates(string $raw): void
    {
        self::assertTrue(Status::isValid($raw));
    }

    /** @return iterable<string, array{string}> */
    public static function validRaw(): iterable
    {
        yield 'placed' => ['placed'];
        yield 'confirmed' => ['confirmed'];
        yield 'cancelled' => ['cancelled'];
        yield 'fulfilled' => ['fulfilled'];
    }

    #[DataProvider('invalidRaw')]
    public function testIsValidRejectsUnknown(string $raw): void
    {
        self::assertFalse(Status::isValid($raw));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidRaw(): iterable
    {
        yield 'empty' => [''];
        yield 'unknown word' => ['shipped'];
        yield 'wrong case' => ['Placed'];
        yield 'padded' => [' placed '];
    }
}
