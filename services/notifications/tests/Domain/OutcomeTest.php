<?php

declare(strict_types=1);

namespace Plushki\Notifications\Tests\Domain;

use PHPUnit\Framework\TestCase;
use Plushki\Notifications\Domain\Outcome;

final class OutcomeTest extends TestCase
{
    public function testHasExactlyThreeCases(): void
    {
        self::assertCount(3, Outcome::cases());
    }

    public function testCasesAreAckNakTerm(): void
    {
        $names = array_map(static fn (Outcome $o): string => $o->name, Outcome::cases());
        self::assertSame(['Ack', 'Nak', 'Term'], $names);
    }

    public function testCasesAreDistinct(): void
    {
        self::assertNotSame(Outcome::Ack, Outcome::Nak);
        self::assertNotSame(Outcome::Nak, Outcome::Term);
        self::assertNotSame(Outcome::Ack, Outcome::Term);
    }
}
