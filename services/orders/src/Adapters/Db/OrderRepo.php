<?php

declare(strict_types=1);

namespace Plushki\Orders\Adapters\Db;

use Doctrine\DBAL\Connection;
use Plushki\Orders\Domain\Channel;
use Plushki\Orders\Domain\DomainException;
use Plushki\Orders\Domain\ErrorCode;
use Plushki\Orders\Domain\Item;
use Plushki\Orders\Domain\Order;
use Plushki\Orders\Domain\Status;
use Plushki\Orders\Ports\ListFilter;
use Plushki\Orders\Ports\OrderRepo as OrderRepoPort;

/**
 * OrderRepo is the DBAL implementation of the order persistence port. Reads
 * return the aggregate with its items attached (batch-loaded for list queries).
 * Hand-written SQL, no ORM.
 */
final class OrderRepo implements OrderRepoPort
{
    private const COLS = 'id, tenant_id, channel, customer_ref, status, total_kopecks, created_at, updated_at';

    public function __construct(private readonly Connection $db)
    {
    }

    public function getById(string $id): Order
    {
        $row = $this->db->fetchAssociative(
            'SELECT ' . self::COLS . ' FROM orders WHERE id = CAST(:id AS uuid)',
            ['id' => $id],
        );
        if ($row === false) {
            throw DomainException::of(ErrorCode::OrderNotFound);
        }
        $o = self::scan($row);
        $o->items = $this->listItems($id);

        return $o;
    }

    public function listByCustomer(string $tenantId, string $customerRef, int $limit): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT ' . self::COLS . ' FROM orders
             WHERE tenant_id = :tenant AND customer_ref = :customer
             ORDER BY created_at DESC
             LIMIT CAST(:limit AS integer)',
            ['tenant' => $tenantId, 'customer' => $customerRef, 'limit' => $limit],
        );

        return $this->attachItems(array_map(self::scan(...), $rows));
    }

    public function list(ListFilter $f): array
    {
        $tenant = $f->tenantId !== '' ? $f->tenantId : 'default';
        $limit = ($f->limit <= 0 || $f->limit > 500) ? 50 : $f->limit;

        $conds = ['tenant_id = :tenant'];
        $params = ['tenant' => $tenant];
        if ($f->customerRef !== null) {
            $conds[] = 'customer_ref = :customer';
            $params['customer'] = $f->customerRef;
        }
        if ($f->status !== null) {
            $conds[] = 'status = :status';
            $params['status'] = $f->status->value;
        }
        if ($f->channel !== null) {
            $conds[] = 'channel = :channel';
            $params['channel'] = $f->channel->value;
        }
        if ($f->from !== null) {
            $conds[] = 'created_at >= CAST(:from AS timestamptz)';
            $params['from'] = Ts::fmt($f->from);
        }
        if ($f->to !== null) {
            $conds[] = 'created_at < CAST(:to AS timestamptz)';
            $params['to'] = Ts::fmt($f->to);
        }
        $params['limit'] = $limit;

        $sql = 'SELECT ' . self::COLS . ' FROM orders WHERE ' . implode(' AND ', $conds)
            . ' ORDER BY created_at DESC LIMIT CAST(:limit AS integer)';

        $rows = $this->db->fetchAllAssociative($sql, $params);

        return $this->attachItems(array_map(self::scan(...), $rows));
    }

    public function updateStatus(string $id, Status $status, \DateTimeImmutable $updatedAt): void
    {
        $affected = $this->db->executeStatement(
            'UPDATE orders SET status = :status, updated_at = CAST(:at AS timestamptz) WHERE id = CAST(:id AS uuid)',
            ['id' => $id, 'status' => $status->value, 'at' => Ts::fmt($updatedAt)],
        );
        if ($affected === 0) {
            throw DomainException::of(ErrorCode::OrderNotFound);
        }
    }

    /** @return list<Item> */
    private function listItems(string $orderId): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT line_no, product_id, name_snapshot, sku_snapshot, price_kopecks_snapshot, qty
             FROM order_items WHERE order_id = CAST(:oid AS uuid) ORDER BY line_no ASC',
            ['oid' => $orderId],
        );

        return array_map(self::scanItem(...), $rows);
    }

    /**
     * Batch-load items for several orders in one query and attach them, keeping
     * the order of $orders intact.
     *
     * @param list<Order> $orders
     * @return list<Order>
     */
    private function attachItems(array $orders): array
    {
        if ($orders === []) {
            return $orders;
        }
        $ids = array_map(static fn (Order $o): string => $o->id, $orders);
        $rows = $this->db->fetchAllAssociative(
            'SELECT order_id, line_no, product_id, name_snapshot, sku_snapshot, price_kopecks_snapshot, qty
             FROM order_items WHERE order_id = ANY(CAST(:ids AS uuid[])) ORDER BY order_id, line_no',
            ['ids' => PgArray::encode($ids)],
        );
        $byOrder = [];
        foreach ($rows as $r) {
            $byOrder[(string) $r['order_id']][] = self::scanItem($r);
        }
        foreach ($orders as $o) {
            $o->items = $byOrder[$o->id] ?? [];
        }

        return $orders;
    }

    /** @param array<string, mixed> $row */
    private static function scan(array $row): Order
    {
        return new Order(
            id: (string) $row['id'],
            tenantId: (string) $row['tenant_id'],
            channel: Channel::from((string) $row['channel']),
            customerRef: (string) $row['customer_ref'],
            status: Status::from((string) $row['status']),
            totalKopecks: (int) $row['total_kopecks'],
            items: [],
            createdAt: Ts::parse((string) $row['created_at']),
            updatedAt: Ts::parse((string) $row['updated_at']),
        );
    }

    /** @param array<string, mixed> $row */
    private static function scanItem(array $row): Item
    {
        return new Item(
            lineNo: (int) $row['line_no'],
            productId: (string) $row['product_id'],
            nameSnapshot: (string) $row['name_snapshot'],
            skuSnapshot: (string) $row['sku_snapshot'],
            priceKopecksSnapshot: (int) $row['price_kopecks_snapshot'],
            qty: (int) $row['qty'],
        );
    }
}