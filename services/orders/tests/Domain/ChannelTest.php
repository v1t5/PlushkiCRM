<?php

declare(strict_types=1);

namespace Plushki\Orders\Tests\Domain;

use Plushki\Orders\Domain\Channel;
use Plushki\Orders\Domain\DomainException;
use Plushki\Orders\Domain\ErrorCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChannelTest extends TestCase
{
    public function testEnumCases(): void
    {
        self::assertSame('tg', Channel::TG->value);
        self::assertSame('pos', Channel::POS->value);
        self::assertSame('web', Channel::Web->value);
        self::assertCount(3, Channel::cases());
    }

    #[DataProvider('parseable')]
    public function testParseNormalisesAndMatches(string $raw, Channel $expected): void
    {
        self::assertSame($expected, Channel::parse($raw));
    }

    /** @return iterable<string, array{string, Channel}> */
    public static function parseable(): iterable
    {
        yield 'plain tg' => ['tg', Channel::TG];
        yield 'uppercase' => ['POS', Channel::POS];
        yield 'mixed case' => ['Web', Channel::Web];
        yield 'padded + upper' => ['  TG  ', Channel::TG];
    }

    #[DataProvider('unparseable')]
    public function testParseRejectsUnknown(string $raw): void
    {
        try {
            Channel::parse($raw);
            self::fail('expected DomainException for ' . var_export($raw, true));
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidChannel, $e->errorCode);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function unparseable(): iterable
    {
        yield 'empty' => [''];
        yield 'unknown' => ['email'];
        yield 'whitespace only' => ['   '];
    }
}
