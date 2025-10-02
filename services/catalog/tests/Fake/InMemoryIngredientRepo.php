<?php

declare(strict_types=1);

namespace Plushki\Catalog\Tests\Fake;

use Plushki\Catalog\Domain\DomainException;
use Plushki\Catalog\Domain\ErrorCode;
use Plushki\Catalog\Domain\Ingredient;
use Plushki\Catalog\Ports\IngredientRepo;

/** Array-backed IngredientRepo for usecase tests. */
final class InMemoryIngredientRepo implements IngredientRepo
{
    /** @var array<string, Ingredient> */
    public array $byId = [];

    public function add(Ingredient $i): void
    {
        $this->byId[$i->id] = $i;
    }

    public function getById(string $id): Ingredient
    {
        if (!isset($this->byId[$id])) {
            throw DomainException::of(ErrorCode::IngredientNotFound);
        }

        return $this->byId[$id];
    }

    /** @return list<Ingredient> */
    public function listActive(string $tenantId): array
    {
        $out = [];
        foreach ($this->byId as $i) {
            if ($i->tenantId === $tenantId && !$i->isArchived()) {
                $out[] = $i;
            }
        }

        return $out;
    }
}
