<?php

declare(strict_types=1);

namespace Plushki\Notifications\Tests\Domain;

use PHPUnit\Framework\TestCase;
use Plushki\Notifications\Domain\Channel;
use Plushki\Notifications\Domain\DomainException;
use Plushki\Notifications\Domain\ErrorCode;
use Plushki\Notifications\Domain\Recipient;

final class RecipientTest extends TestCase
{
    public function testConstruction(): void
    {
        $rec = new Recipient(Channel::TG, '99');
        self::assertSame(Channel::TG, $rec->channel);
        self::assertSame('99', $rec->id);
    }

    public function testParseValidTgRef(): void
    {
        $rec = Recipient::parse('tg:42');
        self::assertSame(Channel::TG, $rec->channel);
        self::assertSame('42', $rec->id);
    }

    public function testParseTrimsSurroundingWhitespace(): void
    {
        $rec = Recipient::parse('  tg:42  ');
        self::assertSame('42', $rec->id);
    }

    public function testParseTrimsIdWhitespace(): void
    {
        $rec = Recipient::parse('tg: 42 ');
        self::assertSame('42', $rec->id);
    }

    public function testParseIsCaseInsensitiveOnPrefix(): void
    {
        $rec = Recipient::parse('TG:42');
        self::assertSame(Channel::TG, $rec->channel);
    }

    public function testParseUnsupportedChannelThrowsUnsupportedRecipient(): void
    {
        try {
            Recipient::parse('sms:42');
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::UnsupportedRecipient, $e->errorCode);
        }
    }

    public function testParseNoColonThrowsInvalidRecipient(): void
    {
        try {
            Recipient::parse('tg42');
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidRecipient, $e->errorCode);
        }
    }

    public function testParseLeadingColonThrowsInvalidRecipient(): void
    {
        try {
            Recipient::parse(':42');
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidRecipient, $e->errorCode);
        }
    }

    public function testParseTrailingColonThrowsInvalidRecipient(): void
    {
        try {
            Recipient::parse('tg:');
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidRecipient, $e->errorCode);
        }
    }

    public function testParseEmptyIdAfterTrimThrowsInvalidRecipient(): void
    {
        // Trailing-colon guard passes (idx is not last char), but id trims to ''.
        try {
            Recipient::parse('tg:   ');
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidRecipient, $e->errorCode);
        }
    }

    public function testParseEmptyStringThrowsInvalidRecipient(): void
    {
        try {
            Recipient::parse('');
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidRecipient, $e->errorCode);
        }
    }
}
