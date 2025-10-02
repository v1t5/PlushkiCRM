<?php

declare(strict_types=1);

namespace Plushki\Crm\Adapters\Http;

use Plushki\Crm\Domain\Customer;
use Plushki\Crm\Domain\Identity;
use Plushki\Crm\Domain\Loyalty;
use Plushki\Crm\Ports\CustomerWithIdentities;

/**
 * Resp builds the JSON response bodies.
 */
final class Resp
{
    private static function ts(\DateTimeImmutable $dt): string
    {
        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP');
    }

    /** @param Identity $id */
    private static function identity(Identity $id): array
    {
        return [
            'id' => $id->id,
            'type' => $id->type->value,
            'value' => $id->value,
            'verified_at' => $id->verifiedAt !== null ? self::ts($id->verifiedAt) : null,
            'created_at' => self::ts($id->createdAt),
        ];
    }

    /**
     * @param list<Identity> $ids
     * @return array<string, mixed>
     */
    public static function customer(Customer $c, array $ids): array
    {
        return [
            'id' => $c->id,
            'tenant_id' => $c->tenantId,
            'display_name' => $c->displayName,
            'identities' => array_map(self::identity(...), $ids),
            'created_at' => self::ts($c->createdAt),
            'updated_at' => self::ts($c->updatedAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function listCustomer(CustomerWithIdentities $row): array
    {
        return [
            'id' => $row->customer->id,
            'tenant_id' => $row->customer->tenantId,
            'display_name' => $row->customer->displayName,
            'identities' => array_map(self::identity(...), $row->identities),
            'visit_count' => $row->loyalty?->visitCount ?? 0,
            'total_kopecks' => $row->loyalty?->totalKopecks ?? 0,
            'last_visit_at' => $row->loyalty?->lastVisitAt !== null ? self::ts($row->loyalty->lastVisitAt) : null,
            'created_at' => self::ts($row->customer->createdAt),
            'updated_at' => self::ts($row->customer->updatedAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function loyalty(Loyalty $l): array
    {
        return [
            'customer_id' => $l->customerId,
            'tenant_id' => $l->tenantId,
            'visit_count' => $l->visitCount,
            'total_kopecks' => $l->totalKopecks,
            'last_visit_at' => $l->lastVisitAt !== null ? self::ts($l->lastVisitAt) : null,
            'updated_at' => self::ts($l->updatedAt),
        ];
    }
}
