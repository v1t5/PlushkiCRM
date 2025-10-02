<?php

declare(strict_types=1);

namespace Plushki\Orders\App;

use Plushki\Orders\Domain\Channel;
use Plushki\Orders\Domain\DomainException;
use Plushki\Orders\Domain\ErrorCode;
use Plushki\Orders\Domain\Item;
use Plushki\Orders\Domain\Order;
use Plushki\Orders\Domain\PlaceInput;
use Plushki\Orders\Domain\Status;
use Plushki\Orders\Platform\Events\Envelope;
use Plushki\Orders\Ports\CatalogClient;
use Plushki\Orders\Ports\ListFilter;
use Plushki\Orders\Ports\OutboxEvent;
use Plushki\Orders\Ports\OutboxRepo;
use Plushki\Orders\Ports\OrderRepo;

/**
 * OrderService orchestrates order use cases. Catalog state is resolved at place
 * time and snapshotted into the order; later catalog edits never rewrite a
 * placed order. State changes publish through the outbox port so a partial
 * commit cannot drop the event.
 */
final class OrderService
{
    public function __construct(
        private readonly OrderRepo $orders,
        private readonly OutboxRepo $outbox,
        private readonly CatalogClient $catalog,
    ) {
    }

    /**
     * @param list<PlaceItem> $items
     */
    public function place(Channel $channel, string $customerRef, array $items): Order
    {
        if ($items === []) {
            throw DomainException::of(ErrorCode::EmptyOrder);
        }
        $resolved = [];
        foreach ($items as $in) {
            if ($in->qty <= 0) {
                throw DomainException::of(ErrorCode::InvalidQuantity);
            }
            $p = $this->catalog->getProduct($in->productId);
            $resolved[] = new Item(
                lineNo: 0,
                productId: $p->id,
                nameSnapshot: $p->name,
                skuSnapshot: $p->sku,
                priceKopecksSnapshot: $p->priceKopecks,
                qty: $in->qty,
            );
        }
        $order = Order::create(new PlaceInput($channel, $customerRef, $resolved));
        $evt = $this->orderEvent($order, 'orders.v1.placed', $this->placedEventData($order));
        $this->outbox->insertWithOrder($order, $evt);

        return $order;
    }

    public function confirm(string $id): Order
    {
        return $this->transition($id, Status::Confirmed, 'orders.v1.confirmed');
    }

    public function cancel(string $id): Order
    {
        return $this->transition($id, Status::Cancelled, 'orders.v1.cancelled');
    }

    public function fulfill(string $id): Order
    {
        return $this->transition($id, Status::Fulfilled, 'orders.v1.fulfilled');
    }

    public function get(string $id): Order
    {
        return $this->orders->getById($id);
    }

    /** @return list<Order> */
    public function listByCustomer(string $tenantId, string $customerRef, int $limit): array
    {
        if ($tenantId === '') {
            $tenantId = 'default';
        }
        if ($limit <= 0 || $limit > 200) {
            $limit = 50;
        }

        return $this->orders->listByCustomer($tenantId, $customerRef, $limit);
    }

    /** @return list<Order> */
    public function list(ListFilter $f): array
    {
        return $this->orders->list($f);
    }

    private function transition(string $id, Status $to, string $schema): Order
    {
        $o = $this->orders->getById($id);
        $o->transition($to);
        $evt = $this->orderEvent($o, $schema, $this->statusChangeEventData($o));
        $this->outbox->insertWithStatusChange($o->id, $o->status, $o->updatedAt, $evt);

        return $o;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function orderEvent(Order $o, string $schema, array $data): OutboxEvent
    {
        $env = Envelope::build(
            schema: $schema,
            data: $data,
            actorType: 'system',
            actorId: 'orders',
            occurredAt: $o->updatedAt->format('Y-m-d\TH:i:s.uP'),
            tenantId: $o->tenantId,
        );

        return new OutboxEvent(
            eventId: $env->eventId,
            aggregateId: $o->id,
            aggregateType: 'order',
            schema: $schema,
            payload: $env->toJson(),
            occurredAt: $o->updatedAt,
            tenantId: $o->tenantId,
            traceId: $env->traceId,
        );
    }

    /** @return array<string, mixed> */
    private function placedEventData(Order $o): array
    {
        return [
            'order_id' => $o->id,
            'channel' => $o->channel->value,
            'customer_ref' => $o->customerRef,
            'status' => $o->status->value,
            'total_kopecks' => $o->totalKopecks,
            'items' => $this->itemsData($o),
        ];
    }

    /**
     * Items on every status-change event so downstream services (production at
     * confirm, inventory at fulfilled) can act without a callback.
     *
     * @return array<string, mixed>
     */
    private function statusChangeEventData(Order $o): array
    {
        return [
            'order_id' => $o->id,
            'status' => $o->status->value,
            'customer_ref' => $o->customerRef,
            'channel' => $o->channel->value,
            'total_kopecks' => $o->totalKopecks,
            'items' => $this->itemsData($o),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function itemsData(Order $o): array
    {
        $items = [];
        foreach ($o->items as $it) {
            $items[] = [
                'line_no' => $it->lineNo,
                'product_id' => $it->productId,
                'name' => $it->nameSnapshot,
                'sku' => $it->skuSnapshot,
                'price_kopecks' => $it->priceKopecksSnapshot,
                'qty' => $it->qty,
            ];
        }

        return $items;
    }
}