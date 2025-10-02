<?php

declare(strict_types=1);

namespace Plushki\Reporting\Adapters\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Plushki\Reporting\Platform\Problem;
use Plushki\Reporting\Platform\ProblemException;

/**
 * Query-string parsing helpers the read controllers share. Dates are
 * YYYY-MM-DD (UTC).
 */
final class Api
{
    public const BASE = 'https://errors.plushki/reporting/';

    public static function json(mixed $body, int $status = 200): JsonResponse
    {
        return new JsonResponse($body, $status);
    }

    public static function tenantFrom(Request $request): string
    {
        $t = (string) $request->query->get('tenant_id', '');
        if ($t !== '') {
            return $t;
        }
        $h = $request->headers->get('X-Tenant-ID', '');

        return $h !== '' ? $h : 'default';
    }

    /**
     * Reads ?from=&to= as YYYY-MM-DD. Both omitted → last 30 days; exactly one
     * omitted → 400; to before from → 400.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    public static function fromTo(Request $request): array
    {
        $fromStr = (string) $request->query->get('from', '');
        $toStr = (string) $request->query->get('to', '');
        if ($fromStr === '' && $toStr === '') {
            $to = self::today();

            return [$to->modify('-30 days'), $to];
        }
        if ($fromStr === '' || $toStr === '') {
            throw self::fail('from and to are both required');
        }
        $from = self::parse($fromStr, 'from');
        $to = self::parse($toStr, 'to');
        if ($to < $from) {
            throw self::fail('to before from');
        }

        return [$from, $to];
    }

    /** Empty → today (UTC midnight); else parse YYYY-MM-DD. */
    public static function singleDate(Request $request, string $key): \DateTimeImmutable
    {
        $s = (string) $request->query->get($key, '');
        if ($s === '') {
            return self::today();
        }

        return self::parse($s, $key);
    }

    public static function limit(Request $request, int $default): int
    {
        $s = (string) $request->query->get('limit', '');
        if ($s === '' || !ctype_digit($s) || (int) $s <= 0) {
            return $default;
        }

        return (int) $s;
    }

    private static function parse(string $s, string $field): \DateTimeImmutable
    {
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $s, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if ($dt === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw self::fail($field . ' must be YYYY-MM-DD');
        }

        return $dt;
    }

    private static function today(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->setTime(0, 0, 0);
    }

    private static function fail(string $detail): ProblemException
    {
        return new ProblemException(Problem::new(self::BASE . 'validation-failed', 'Validation Failed', 400, $detail));
    }
}
