<?php

declare(strict_types=1);

namespace Plushki\Crm\Adapters\Db;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;
use Plushki\Crm\Domain\Customer;
use Plushki\Crm\Domain\DomainException;
use Plushki\Crm\Domain\ErrorCode;
use Plushki\Crm\Domain\Identity;
use Plushki\Crm\Domain\IdentityType;
use Plushki\Crm\Domain\Loyalty;
use Plushki\Crm\Ports\CustomerRepo as CustomerRepoPort;
use Plushki\Crm\Ports\CustomerWithIdentities;
use Plushki\Crm\Ports\OutboxEvent;

final class CustomerRepo implements CustomerRepoPort
{
    public function __construct(private readonly Connection $db)
    {
    }

    /** @param list<Identity> $ids */
    public function createWithIdentities(Customer $c, array $ids, OutboxEvent $evt): void
    {
        try {
            $this->db->transactional(function (Connection $tx) use ($c, $ids, $evt): void {
                $this->insertCustomer($tx, $c);
                foreach ($ids as $id) {
                    $this->insertIdentity($tx, $id);
                }
                $this->insertZeroLoyalty($tx, $c);
                OutboxRepo::insertInto($tx, $evt);
            });
        } catch (UniqueConstraintViolationException) {
            throw DomainException::of(ErrorCode::IdentityConflict);
        }
    }

    public function getById(string $id): Customer
    {
        $row = $this->db->fetchAssociative(
            'SELECT id, tenant_id, display_name, created_at, updated_at FROM customers WHERE id = CAST(:id AS uuid)',
            ['id' => $id],
        );
        if ($row === false) {
            throw DomainException::of(ErrorCode::CustomerNotFound);
        }

        return self::mapCustomer($row);
    }

    /** @return list<Identity> */
    public function listIdentities(string $customerId): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, tenant_id, customer_id, type, value, verified_at, created_at
             FROM customer_identities WHERE customer_id = CAST(:cid AS uuid) ORDER BY created_at ASC',
            ['cid' => $customerId],
        );

        return array_map(self::mapIdentity(...), $rows);
    }

    /** @return array{0: Customer, 1: Identity} */
    public function getByIdentity(string $tenantId, IdentityType $type, string $value): array
    {
        $row = $this->db->fetchAssociative(
            'SELECT i.id AS i_id, i.tenant_id AS i_tenant, i.customer_id, i.type, i.value, i.verified_at, i.created_at AS i_created,
                    c.id AS c_id, c.tenant_id AS c_tenant, c.display_name, c.created_at AS c_created, c.updated_at AS c_updated
             FROM customer_identities i JOIN customers c ON c.id = i.customer_id
             WHERE i.tenant_id = :tenant AND i.type = :type AND i.value = :value',
            ['tenant' => $tenantId, 'type' => $type->value, 'value' => $value],
        );
        if ($row === false) {
            throw DomainException::of(ErrorCode::IdentityNotFound);
        }
        $customer = new Customer(
            id: (string) $row['c_id'],
            tenantId: (string) $row['c_tenant'],
            displayName: (string) $row['display_name'],
            createdAt: Ts::parse((string) $row['c_created']),
            updatedAt: Ts::parse((string) $row['c_updated']),
        );
        $identity = new Identity(
            id: (string) $row['i_id'],
            tenantId: (string) $row['i_tenant'],
            customerId: (string) $row['customer_id'],
            type: IdentityType::from((string) $row['type']),
            value: (string) $row['value'],
            verifiedAt: $row['verified_at'] !== null ? Ts::parse((string) $row['verified_at']) : null,
            createdAt: Ts::parse((string) $row['i_created']),
        );

        return [$customer, $identity];
    }

    /** @return list<CustomerWithIdentities> */
    public function list(string $tenantId, string $q, int $limit): array
    {
        if ($limit <= 0 || $limit > 500) {
            $limit = 50;
        }
        $params = ['tenant' => $tenantId, 'limit' => $limit];
        if ($q !== '') {
            $sql = 'SELECT DISTINCT c.id, c.tenant_id, c.display_name, c.created_at, c.updated_at
                    FROM customers c
                    LEFT JOIN customer_identities i ON i.customer_id = c.id
                    WHERE c.tenant_id = :tenant AND (c.display_name ILIKE :q OR i.value ILIKE :q)
                    ORDER BY c.created_at DESC LIMIT CAST(:limit AS integer)';
            $params['q'] = '%' . $q . '%';
        } else {
            $sql = 'SELECT c.id, c.tenant_id, c.display_name, c.created_at, c.updated_at
                    FROM customers c WHERE c.tenant_id = :tenant
                    ORDER BY c.created_at DESC LIMIT CAST(:limit AS integer)';
        }
        $rows = $this->db->fetchAllAssociative($sql, $params);
        if ($rows === []) {
            return [];
        }
        $customers = array_map(self::mapCustomer(...), $rows);
        $ids = array_map(static fn (Customer $c): string => $c->id, $customers);

        $identsByCustomer = $this->identitiesByCustomers($ids);
        $loyaltyByCustomer = $this->loyaltyByCustomers($ids);

        $out = [];
        foreach ($customers as $c) {
            $out[] = new CustomerWithIdentities(
                customer: $c,
                identities: $identsByCustomer[$c->id] ?? [],
                loyalty: $loyaltyByCustomer[$c->id] ?? null,
            );
        }

        return $out;
    }

    public function ensureWalkin(string $tenantId): Customer
    {
        try {
            [$c] = $this->getByIdentity($tenantId, IdentityType::PosWalkin, 'walk-in');

            return $c;
        } catch (DomainException $e) {
            if ($e->errorCode !== ErrorCode::IdentityNotFound) {
                throw $e;
            }
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $customer = new Customer(Uuid::v7()->toRfc4122(), $tenantId, 'Walk-in', $now, $now);
        $identity = new Identity(Uuid::v7()->toRfc4122(), $tenantId, $customer->id, IdentityType::PosWalkin, 'walk-in', null, $now);

        try {
            $this->db->transactional(function (Connection $tx) use ($customer, $identity): void {
                $this->insertCustomer($tx, $customer);
                $this->insertIdentity($tx, $identity);
                $this->insertZeroLoyalty($tx, $customer);
            });
        } catch (UniqueConstraintViolationException) {
            // Lost a race — adopt the row that committed first.
            [$c] = $this->getByIdentity($tenantId, IdentityType::PosWalkin, 'walk-in');

            return $c;
        }

        return $customer;
    }

    private function insertCustomer(Connection $tx, Customer $c): void
    {
        $tx->executeStatement(
            'INSERT INTO customers (id, tenant_id, display_name, created_at, updated_at)
             VALUES (CAST(:id AS uuid), :tenant_id, :display_name, CAST(:created AS timestamptz), CAST(:updated AS timestamptz))',
            [
                'id' => $c->id, 'tenant_id' => $c->tenantId, 'display_name' => $c->displayName,
                'created' => Ts::fmt($c->createdAt), 'updated' => Ts::fmt($c->updatedAt),
            ],
        );
    }

    private function insertIdentity(Connection $tx, Identity $id): void
    {
        $tx->executeStatement(
            'INSERT INTO customer_identities (id, tenant_id, customer_id, type, value, verified_at, created_at)
             VALUES (CAST(:id AS uuid), :tenant_id, CAST(:cid AS uuid), :type, :value,
                     CAST(:verified AS timestamptz), CAST(:created AS timestamptz))',
            [
                'id' => $id->id, 'tenant_id' => $id->tenantId, 'cid' => $id->customerId,
                'type' => $id->type->value, 'value' => $id->value,
                'verified' => $id->verifiedAt !== null ? Ts::fmt($id->verifiedAt) : null,
                'created' => Ts::fmt($id->createdAt),
            ],
        );
    }

    private function insertZeroLoyalty(Connection $tx, Customer $c): void
    {
        $tx->executeStatement(
            'INSERT INTO loyalty (customer_id, tenant_id, visit_count, total_kopecks, last_visit_at, updated_at)
             VALUES (CAST(:cid AS uuid), :tenant_id, 0, 0, NULL, CAST(:updated AS timestamptz))
             ON CONFLICT (customer_id) DO NOTHING',
            ['cid' => $c->id, 'tenant_id' => $c->tenantId, 'updated' => Ts::fmt($c->updatedAt)],
        );
    }

    /**
     * @param list<string> $ids
     * @return array<string, list<Identity>>
     */
    private function identitiesByCustomers(array $ids): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, tenant_id, customer_id, type, value, verified_at, created_at
             FROM customer_identities WHERE customer_id = ANY(CAST(:ids AS uuid[]))
             ORDER BY customer_id, created_at ASC',
            ['ids' => PgArray::encode($ids)],
        );
        $out = [];
        foreach ($rows as $r) {
            $id = self::mapIdentity($r);
            $out[$id->customerId][] = $id;
        }

        return $out;
    }

    /**
     * @param list<string> $ids
     * @return array<string, Loyalty>
     */
    private function loyaltyByCustomers(array $ids): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT customer_id, tenant_id, visit_count, total_kopecks, last_visit_at, updated_at
             FROM loyalty WHERE customer_id = ANY(CAST(:ids AS uuid[]))',
            ['ids' => PgArray::encode($ids)],
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['customer_id']] = self::mapLoyalty($r);
        }

        return $out;
    }

    /** @param array<string, mixed> $r */
    private static function mapCustomer(array $r): Customer
    {
        return new Customer(
            id: (string) $r['id'],
            tenantId: (string) $r['tenant_id'],
            displayName: (string) $r['display_name'],
            createdAt: Ts::parse((string) $r['created_at']),
            updatedAt: Ts::parse((string) $r['updated_at']),
        );
    }

    /** @param array<string, mixed> $r */
    private static function mapIdentity(array $r): Identity
    {
        return new Identity(
            id: (string) $r['id'],
            tenantId: (string) $r['tenant_id'],
            customerId: (string) $r['customer_id'],
            type: IdentityType::from((string) $r['type']),
            value: (string) $r['value'],
            verifiedAt: $r['verified_at'] !== null ? Ts::parse((string) $r['verified_at']) : null,
            createdAt: Ts::parse((string) $r['created_at']),
        );
    }

    /** @param array<string, mixed> $r */
    private static function mapLoyalty(array $r): Loyalty
    {
        return new Loyalty(
            customerId: (string) $r['customer_id'],
            tenantId: (string) $r['tenant_id'],
            visitCount: (int) $r['visit_count'],
            totalKopecks: (int) $r['total_kopecks'],
            lastVisitAt: $r['last_visit_at'] !== null ? Ts::parse((string) $r['last_visit_at']) : null,
            updatedAt: Ts::parse((string) $r['updated_at']),
        );
    }
}
