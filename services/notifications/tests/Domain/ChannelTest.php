<?php

declare(strict_types=1);

namespace Plushki\Notifications\Tests\Domain;

use PHPUnit\Framework\TestCase;
use Plushki\Notifications\Domain\Channel;

final class ChannelTest extends TestCase
{
    public function testTgCaseHasExpectedValue(): void
    {
        self::assertSame('tg', Channel::TG->value);
    }

    public function testTryFromKnownValue(): void
    {
        self::assertSame(Channel::TG, Channel::tryFrom('tg'));
    }

    public function testTryFromUnknownValueReturnsNull(): void
    {
        self::assertNull(Channel::tryFrom('sms'));
        self::assertNull(Channel::tryFrom('email'));
        self::assertNull(Channel::tryFrom('TG'));
    }

    public function testTgIsTheOnlyCase(): void
    {
        self::assertCount(1, Channel::cases());
        self::assertSame(Channel::TG, Channel::cases()[0]);
    }
}
