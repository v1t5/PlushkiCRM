<?php

declare(strict_types=1);

namespace Plushki\Crm\Adapters\Db;

/**
 * PgArray converts PHP string lists into Postgres array literals (for the
 * mark-published `ANY(CAST(:ids AS uuid[]))` query).
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
