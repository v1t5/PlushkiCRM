<?php

declare(strict_types=1);

namespace Plushki\Reporting\Tests\Adapters\Http;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Plushki\Reporting\Adapters\Http\Api;
use Plushki\Reporting\Platform\ProblemException;

final class ApiTest extends TestCase
{
    /** @param array<string, string> $query @param array<string, string> $headers */
    private function request(array $query = [], array $headers = []): Request
    {
        $req = new Request($query);
        foreach ($headers as $k => $v) {
            $req->headers->set($k, $v);
        }

        return $req;
    }

    public function testTenantFromQueryWins(): void
    {
        $req = $this->request(['tenant_id' => 'acme'], ['X-Tenant-ID' => 'other']);
        $this->assertSame('acme', Api::tenantFrom($req));
    }

    public function testTenantFromHeaderUsedWhenQueryMissing(): void
    {
        $req = $this->request([], ['X-Tenant-ID' => 'hdr']);
        $this->assertSame('hdr', Api::tenantFrom($req));
    }

    public function testTenantDefaultsToDefault(): void
    {
        $this->assertSame('default', Api::tenantFrom($this->request()));
    }

    public function testFromToBothOmittedYieldsLast30Days(): void
    {
        [$from, $to] = Api::fromTo($this->request());
        $diff = $to->diff($from);
        $this->assertSame(30, (int) $diff->days);
        // 'to' is today at UTC midnight.
        $this->assertSame('00:00:00', $to->format('H:i:s'));
    }

    public function testFromToParsesExplicitRange(): void
    {
        [$from, $to] = Api::fromTo($this->request(['from' => '2026-01-01', 'to' => '2026-01-31']));
        $this->assertSame('2026-01-01', $from->format('Y-m-d'));
        $this->assertSame('2026-01-31', $to->format('Y-m-d'));
        $this->assertSame('UTC', $from->getTimezone()->getName());
    }

    public function testFromToRejectsOnlyOneProvided(): void
    {
        $this->expectException(ProblemException::class);
        Api::fromTo($this->request(['from' => '2026-01-01']));
    }

    public function testFromToRejectsToBeforeFrom(): void
    {
        $this->expectException(ProblemException::class);
        Api::fromTo($this->request(['from' => '2026-02-01', 'to' => '2026-01-01']));
    }

    public function testFromToRejectsBadDate(): void
    {
        $this->expectException(ProblemException::class);
        Api::fromTo($this->request(['from' => '2026-13-99', 'to' => '2026-12-31']));
    }

    public function testFromToRejectsNonDateFormat(): void
    {
        $this->expectException(ProblemException::class);
        Api::fromTo($this->request(['from' => '01/02/2026', 'to' => '2026-12-31']));
    }

    public function testSingleDateEmptyReturnsTodayMidnight(): void
    {
        $d = Api::singleDate($this->request(), 'date');
        $this->assertSame('00:00:00', $d->format('H:i:s'));
        $this->assertSame('UTC', $d->getTimezone()->getName());
    }

    public function testSingleDateParsesValue(): void
    {
        $d = Api::singleDate($this->request(['date' => '2026-03-15']), 'date');
        $this->assertSame('2026-03-15', $d->format('Y-m-d'));
    }

    public function testSingleDateRejectsBadValue(): void
    {
        $this->expectException(ProblemException::class);
        Api::singleDate($this->request(['date' => 'nope']), 'date');
    }

    public function testLimitDefaultsWhenMissing(): void
    {
        $this->assertSame(10, Api::limit($this->request(), 10));
    }

    public function testLimitDefaultsOnNonDigit(): void
    {
        $this->assertSame(50, Api::limit($this->request(['limit' => 'abc']), 50));
    }

    public function testLimitDefaultsOnZero(): void
    {
        $this->assertSame(25, Api::limit($this->request(['limit' => '0']), 25));
    }

    public function testLimitDefaultsOnNegative(): void
    {
        // Leading '-' is not ctype_digit, so it falls back to the default.
        $this->assertSame(7, Api::limit($this->request(['limit' => '-5']), 7));
    }

    public function testLimitParsesValidValue(): void
    {
        $this->assertSame(42, Api::limit($this->request(['limit' => '42']), 10));
    }
}
