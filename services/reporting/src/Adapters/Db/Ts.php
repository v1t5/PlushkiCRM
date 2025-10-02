<?php

declare(strict_types=1);

namespace Plushki\Reporting\Adapters\Db;

/**
 * Ts converts DateTimeImmutable to/from Postgres timestamptz text, UTC-normalised.
 */
final class Ts
{
    public static function fmt(\DateTimeImmutable $dt): string
    {
        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }

    public static function rfc(\DateTimeImmutable $dt): string
    {
        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP');
    }

    public static function parse(string $s): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($s))->setTimezone(new \DateTimeZone('UTC'));
    }
}
