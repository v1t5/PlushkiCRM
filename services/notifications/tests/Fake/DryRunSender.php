<?php

declare(strict_types=1);

namespace Plushki\Notifications\Tests\Fake;

use Plushki\Notifications\Domain\Channel;
use Plushki\Notifications\Domain\Notification;
use Plushki\Notifications\Ports\Sender;

/**
 * A Sender that models the Telegram adapter's dry-run mode (empty bot token):
 * it records the intended send for inspection and reports success without
 * "calling out". Distinct from FakeSender only in intent — used to assert the
 * DRY-RUN path records the would-be message yet the delivery still succeeds.
 */
final class DryRunSender implements Sender
{
    /** @var list<array{chat_id: string, schema: string, body: string}> */
    public array $intended = [];

    public function channel(): Channel
    {
        return Channel::TG;
    }

    public function send(Notification $n): void
    {
        // Mirror the real adapter's dry-run branch: log-equivalent capture, no I/O.
        $this->intended[] = [
            'chat_id' => $n->recipient->id,
            'schema' => $n->schema,
            'body' => $n->body,
        ];
    }
}
