<?php

declare(strict_types=1);

namespace Plushki\TgBot\Adapters\Catalog;

/** The slice of a catalog product the bot renders. */
final class Product
{
    public function __construct(
        public readonly string $id,
        public readonly string $sku,
        public readonly string $name,
        public readonly string $description,
        public readonly int $priceKopecks,
    ) {
    }
}
