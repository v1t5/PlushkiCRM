<?php

declare(strict_types=1);

namespace Plushki\TgBot\Adapters\Orders;

/**
 * Covers both "no such order" and "no such product" — the orders service
 * normalises a catalog 404 to its own 404, so the bot doesn't distinguish.
 */
final class OrderNotFound extends \RuntimeException
{
}
