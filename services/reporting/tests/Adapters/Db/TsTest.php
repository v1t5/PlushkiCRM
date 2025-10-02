<?php

declare(strict_types=1);

namespace Plushki\Reporting\Tests\Adapters\Db;

use PHPUnit\Framework\TestCase;
use Plushki\Reporting\Adapters\Db\Ts;

final class TsTest extends TestCase
{
    public function testFmtNormalisesToUtcSpaceSeparated(): void
    {
        $dt = new \DateTimeImmutable('2026-06-01T13:30:00.000000+02:00');
        // +02:00 → 11:30:00 UTC.
        $this->assertSame('2026-06-01 11:30:00.000000+00:00', Ts::fmt($dt));
    }

    public function testRfcNormalisesToUtcWithTSeparator(): void
    {
        $dt = new \DateTimeImmutable('2026-06-01T13:30:00.000000+02:00');
        $this->assertSame('2026-06-01T11:30:00.000000+00:00', Ts::rfc($dt));
    }

    public function testParseConvertsToUtc(): void
    {
        $dt = Ts::parse('2026-06-01T13:30:00+02:00');
        $this->assertSame('UTC', $dt->getTimezone()->getName());
        $this->assertSame('2026-06-01 11:30:00', $dt->format('Y-m-d H:i:s'));
    }

    public function testRoundTripFmtThenParseIsStable(): void
    {
        $dt = new \DateTimeImmutable('2026-12-31T23:59:59.123456+00:00');
        $reparsed = Ts::parse(Ts::fmt($dt));
        $this->assertSame($dt->format('Y-m-d H:i:s.u'), $reparsed->format('Y-m-d H:i:s.u'));
    }
}
