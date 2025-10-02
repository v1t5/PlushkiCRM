<?php

declare(strict_types=1);

namespace Plushki\Notifications\Tests\Fake;

use Plushki\Notifications\Domain\Channel;
use Plushki\Notifications\Domain\Notification;
use Plushki\Notifications\Ports\Sender;

/**
 * Recording Sender. Captures every Notification handed to send() so tests can
 * assert what would have gone out — never touches the network. Set $throws to
 * simulate a delivery failure.
 */
final class FakeSender implements Sender
{
    /** @var list<Notification> */
    public array $sent = [];

    public function __construct(
        private readonly Channel $channel = Channel::TG,
        public ?\Throwable $throws = null,
    ) {
    }

    public function channel(): Channel
    {
        return $this->channel;
    }

    public function send(Notification $n): void
    {
        if ($this->throws !== null) {
            throw $this->throws;
        }
        $this->sent[] = $n;
    }
}
