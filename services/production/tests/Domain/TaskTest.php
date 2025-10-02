<?php

declare(strict_types=1);

namespace Plushki\Production\Tests\Domain;

use Plushki\Production\Domain\DomainException;
use Plushki\Production\Domain\ErrorCode;
use Plushki\Production\Domain\Task;
use Plushki\Production\Domain\TaskStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaskTest extends TestCase
{
    public function testCreateStartsOpenWithDefaults(): void
    {
        $t = Task::create('plan-1', 'product-1', 5);

        self::assertSame(TaskStatus::Open, $t->status);
        self::assertSame('plan-1', $t->planId);
        self::assertSame('product-1', $t->productId);
        self::assertSame(5, $t->qty);
        self::assertSame('default', $t->tenantId);
        self::assertNull($t->bakerId);
        self::assertNull($t->startedAt);
        self::assertNull($t->completedAt);
        self::assertNotSame('', $t->id);
    }

    /** @return list<array{int}> */
    public static function invalidQtys(): array
    {
        return [[0], [-1], [-100]];
    }

    #[DataProvider('invalidQtys')]
    public function testCreateRejectsNonPositiveQty(int $qty): void
    {
        try {
            Task::create('plan-1', 'product-1', $qty);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidQty, $e->errorCode);
        }
    }

    public function testStartMovesToInProgressAndStampsBaker(): void
    {
        $t = Task::create('plan-1', 'product-1', 1);
        $t->start('baker-7');

        self::assertSame(TaskStatus::InProgress, $t->status);
        self::assertSame('baker-7', $t->bakerId);
        self::assertNotNull($t->startedAt);
    }

    public function testStartAllowsNullBaker(): void
    {
        $t = Task::create('plan-1', 'product-1', 1);
        $t->start(null);

        self::assertSame(TaskStatus::InProgress, $t->status);
        self::assertNull($t->bakerId);
        self::assertNotNull($t->startedAt);
    }

    public function testCompleteHappyPath(): void
    {
        $t = Task::create('plan-1', 'product-1', 1);
        $t->start('baker-1');
        $t->complete();

        self::assertSame(TaskStatus::Completed, $t->status);
        self::assertNotNull($t->completedAt);
    }

    public function testCancelFromOpen(): void
    {
        $t = Task::create('plan-1', 'product-1', 1);
        $t->cancel();

        self::assertSame(TaskStatus::Cancelled, $t->status);
    }

    public function testCancelFromInProgress(): void
    {
        $t = Task::create('plan-1', 'product-1', 1);
        $t->start(null);
        $t->cancel();

        self::assertSame(TaskStatus::Cancelled, $t->status);
    }

    public function testCannotCompleteWhileOpen(): void
    {
        $t = Task::create('plan-1', 'product-1', 1);

        $this->expectTransitionError(static fn () => $t->complete());
        self::assertSame(TaskStatus::Open, $t->status);
    }

    public function testCannotStartTwice(): void
    {
        $t = Task::create('plan-1', 'product-1', 1);
        $t->start(null);

        $this->expectTransitionError(static fn () => $t->start(null));
        self::assertSame(TaskStatus::InProgress, $t->status);
    }

    public function testCannotStartCompletedTask(): void
    {
        $t = Task::create('plan-1', 'product-1', 1);
        $t->start(null);
        $t->complete();

        $this->expectTransitionError(static fn () => $t->start(null));
    }

    public function testCannotCompleteCompletedTask(): void
    {
        $t = Task::create('plan-1', 'product-1', 1);
        $t->start(null);
        $t->complete();

        $this->expectTransitionError(static fn () => $t->complete());
    }

    public function testCannotCancelCompletedTask(): void
    {
        $t = Task::create('plan-1', 'product-1', 1);
        $t->start(null);
        $t->complete();

        $this->expectTransitionError(static fn () => $t->cancel());
        self::assertSame(TaskStatus::Completed, $t->status);
    }

    public function testCannotCancelCancelledTask(): void
    {
        $t = Task::create('plan-1', 'product-1', 1);
        $t->cancel();

        $this->expectTransitionError(static fn () => $t->cancel());
    }

    public function testCannotCompleteCancelledTask(): void
    {
        $t = Task::create('plan-1', 'product-1', 1);
        $t->cancel();

        $this->expectTransitionError(static fn () => $t->complete());
    }

    private function expectTransitionError(callable $fn): void
    {
        try {
            $fn();
            self::fail('expected DomainException InvalidTaskTransition');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::InvalidTaskTransition, $e->errorCode);
        }
    }
}
