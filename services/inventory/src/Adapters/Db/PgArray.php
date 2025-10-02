<?php

declare(strict_types=1);

namespace Plushki\Inventory\Adapters\Db;

/**
 * PgArray converts PHP string lists into Postgres array literals. pdo_pgsql has
 * no native array binding, so repositories pass the literal as text and cast
 * with `CAST(:x AS uuid[])`.
 */
final class PgArray
{
    /** @param list<string> $values */
    public static function encode(array $values): string
    {
        $quoted = array_map(
            static fn (string $v): string => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $v) . '"',
            $values,
        );

        return '{' . implode(',', $quoted) . '}';
    }
}
