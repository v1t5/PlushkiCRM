<?php

declare(strict_types=1);

namespace Plushki\Catalog\Adapters\Db;

use Doctrine\DBAL\Connection;
use Plushki\Catalog\Domain\RecipeLine;
use Plushki\Catalog\Ports\RecipeRepo as RecipeRepoPort;

/** DBAL implementation of the recipe persistence port. */
final class RecipeRepo implements RecipeRepoPort
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function listByProduct(string $productId): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, product_id, ingredient_id, qty, unit_id, created_at
             FROM recipe_lines
             WHERE product_id = CAST(:pid AS uuid)
             ORDER BY created_at ASC',
            ['pid' => $productId],
        );

        return array_map(self::scan(...), $rows);
    }

    /** @param array<string, mixed> $row */
    private static function scan(array $row): RecipeLine
    {
        return new RecipeLine(
            id: (string) $row['id'],
            productId: (string) $row['product_id'],
            ingredientId: (string) $row['ingredient_id'],
            qty: (int) $row['qty'],
            unitId: (string) $row['unit_id'],
            createdAt: Ts::parse((string) $row['created_at']),
        );
    }
}
