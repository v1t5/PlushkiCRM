<?php

declare(strict_types=1);

namespace Plushki\Production\Tests\Domain;

use Plushki\Production\Domain\TaskStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaskStatusTest extends TestCase
{
    /** @return list<array{TaskStatus, TaskStatus}> */
    public static function allowedTransitions(): array
    {
        return [
            'open -> in_progress'        => [TaskStatus::Open, TaskStatus::InProgress],
            'open -> cancelled'          => [TaskStatus::Open, TaskStatus::Cancelled],
            'in_progress -> completed'   => [TaskStatus::InProgress, TaskStatus::Completed],
            'in_progress -> cancelled'   => [TaskStatus::InProgress, TaskStatus::Cancelled],
        ];
    }

    #[DataProvider('allowedTransitions')]
    public function testAllowedTransitionsAreAllowed(TaskStatus $from, TaskStatus $to): void
    {
        self::assertTrue($from->canTransitionTo($to));
    }

    /** @return list<array{TaskStatus, TaskStatus}> */
    public static function illegalTransitions(): array
    {
        return [
            // open cannot self-loop or jump to completed
            'open -> open'               => [TaskStatus::Open, TaskStatus::Open],
            'open -> completed'          => [TaskStatus::Open, TaskStatus::Completed],
            // in_progress cannot go back or self-loop
            'in_progress -> open'        => [TaskStatus::InProgress, TaskStatus::Open],
            'in_progress -> in_progress' => [TaskStatus::InProgress, TaskStatus::InProgress],
            // completed is terminal
            'completed -> open'          => [TaskStatus::Completed, TaskStatus::Open],
            'completed -> in_progress'   => [TaskStatus::Completed, TaskStatus::InProgress],
            'completed -> completed'     => [TaskStatus::Completed, TaskStatus::Completed],
            'completed -> cancelled'     => [TaskStatus::Completed, TaskStatus::Cancelled],
            // cancelled is terminal
            'cancelled -> open'          => [TaskStatus::Cancelled, TaskStatus::Open],
            'cancelled -> in_progress'   => [TaskStatus::Cancelled, TaskStatus::InProgress],
            'cancelled -> completed'     => [TaskStatus::Cancelled, TaskStatus::Completed],
            'cancelled -> cancelled'     => [TaskStatus::Cancelled, TaskStatus::Cancelled],
        ];
    }

    #[DataProvider('illegalTransitions')]
    public function testIllegalTransitionsAreRejected(TaskStatus $from, TaskStatus $to): void
    {
        self::assertFalse($from->canTransitionTo($to));
    }

    public function testTerminalStatesRejectEveryMove(): void
    {
        foreach ([TaskStatus::Completed, TaskStatus::Cancelled] as $terminal) {
            foreach (TaskStatus::cases() as $to) {
                self::assertFalse(
                    $terminal->canTransitionTo($to),
                    sprintf('%s should be terminal but allowed %s', $terminal->value, $to->value),
                );
            }
        }
    }

    public function testEnumValues(): void
    {
        self::assertSame('open', TaskStatus::Open->value);
        self::assertSame('in_progress', TaskStatus::InProgress->value);
        self::assertSame('completed', TaskStatus::Completed->value);
        self::assertSame('cancelled', TaskStatus::Cancelled->value);
    }
}
