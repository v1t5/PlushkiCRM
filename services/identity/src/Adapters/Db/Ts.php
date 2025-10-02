<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Db;

/**
 * Ts converts DateTimeImmutable to/from the text form bound to / read from
 * Postgres timestamptz columns. All timestamps are normalised to UTC.
 */
final class Ts
{
    public static function fmt(\DateTimeImmutable $dt): string
    {
        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }

    public static function parse(string $s): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($s))->setTimezone(new \DateTimeZone('UTC'));
    }
}
