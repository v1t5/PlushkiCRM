<?php

declare(strict_types=1);

namespace Plushki\Notifications\Ports;

use Plushki\Notifications\Domain\Channel;
use Plushki\Notifications\Domain\Notification;

/**
 * Delivers a rendered Notification to its recipient's channel. Only
 * network-level failures surface (as a DomainException carrying
 * ErrorCode::SendFailed); an unsupported recipient is caught earlier at parse
 * time.
 */
interface Sender
{
    public function channel(): Channel;

    public function send(Notification $n): void;
}
