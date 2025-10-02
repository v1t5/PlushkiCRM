<?php

declare(strict_types=1);

namespace Plushki\Notifications\Domain;

/**
 * A delivery medium. Phase 1 only sends 'tg'; sms/email land later. The
 * delivered_notifications.channel column carries the same enum so adding one
 * doesn't require a migration.
 */
enum Channel: string
{
    case TG = 'tg';
}
