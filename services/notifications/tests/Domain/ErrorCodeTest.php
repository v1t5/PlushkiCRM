<?php

declare(strict_types=1);

namespace Plushki\Notifications\Tests\Domain;

use PHPUnit\Framework\TestCase;
use Plushki\Notifications\Domain\DomainException;
use Plushki\Notifications\Domain\ErrorCode;

final class ErrorCodeTest extends TestCase
{
    public function testCaseValues(): void
    {
        self::assertSame('unsupported recipient channel', ErrorCode::UnsupportedRecipient->value);
        self::assertSame('invalid recipient', ErrorCode::InvalidRecipient->value);
        self::assertSame('send failed', ErrorCode::SendFailed->value);
    }

    public function testDomainExceptionCarriesErrorCode(): void
    {
        $ex = DomainException::of(ErrorCode::SendFailed);
        self::assertSame(ErrorCode::SendFailed, $ex->errorCode);
        self::assertInstanceOf(\RuntimeException::class, $ex);
    }

    public function testDomainExceptionDefaultMessageIsErrorCodeValue(): void
    {
        $ex = DomainException::of(ErrorCode::InvalidRecipient);
        self::assertSame('invalid recipient', $ex->getMessage());
    }

    public function testDomainExceptionCustomMessageIsPreserved(): void
    {
        $ex = new DomainException(ErrorCode::SendFailed, 'timeout talking to telegram');
        self::assertSame('timeout talking to telegram', $ex->getMessage());
        self::assertSame(ErrorCode::SendFailed, $ex->errorCode);
    }
}
