<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Db;

/**
 * PgArray converts between PHP string lists and Postgres text[] literals.
 * pdo_pgsql has no native array binding, so repositories pass the literal as
 * text and cast with `CAST(:x AS text[])`, and parse the `{a,b}` form coming
 * back.
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

    /** @return list<string> */
    public static function decode(?string $literal): array
    {
        if ($literal === null || $literal === '{}' || $literal === '') {
            return [];
        }
        $inner = substr($literal, 1, -1); // strip { }
        $out = [];
        $len = \strlen($inner);
        $buf = '';
        $inQuotes = false;
        for ($i = 0; $i < $len; $i++) {
            $ch = $inner[$i];
            if ($inQuotes) {
                if ($ch === '\\' && $i + 1 < $len) {
                    $buf .= $inner[++$i];
                } elseif ($ch === '"') {
                    $inQuotes = false;
                } else {
                    $buf .= $ch;
                }
                continue;
            }
            if ($ch === '"') {
                $inQuotes = true;
            } elseif ($ch === ',') {
                $out[] = $buf;
                $buf = '';
            } else {
                $buf .= $ch;
            }
        }
        $out[] = $buf;

        return $out;
    }
}
