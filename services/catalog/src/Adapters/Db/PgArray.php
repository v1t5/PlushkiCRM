<?php

declare(strict_types=1);

namespace Plushki\Catalog\Adapters\Db;

/**
 * Converts PHP string lists to Postgres array literals. pdo_pgsql has no native
 * array binding, so repositories pass the literal as text and cast with
 * `CAST(:x AS uuid[])`. Catalog only needs the encode side, for the
 * markPublished `event_id = ANY(...)` batch.
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
