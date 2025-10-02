<?php

declare(strict_types=1);

namespace Plushki\Orders\Adapters\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Plushki\Orders\Adapters\Http\Dto\PlaceOrderReq;
use Plushki\Orders\App\OrderService;
use Plushki\Orders\App\PlaceItem;
use Plushki\Orders\Domain\Channel;
use Plushki\Orders\Domain\Status;
use Plushki\Orders\Ports\ListFilter;

/**
 * OrderController maps /v1/orders. Phase 1 has no auth: admin and the telegram
 * bot both call directly.
 */
#[Route('/v1/orders')]
final class OrderController
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', methods: ['POST'])]
    public function place(Request $request): Response
    {
        $req = Api::decode($request, $this->validator, PlaceOrderReq::class);
        $channel = Channel::parse($req->channel);

        $items = [];
        foreach ($req->items as $i => $it) {
            if (!\is_array($it)) {
                throw Api::validationFailed("items[$i] must be an object");
            }
            $pid = Api::validUuid((string) ($it['product_id'] ?? ''), 'items[].product_id');
            $qty = $it['qty'] ?? null;
            if (!\is_int($qty) || $qty <= 0) {
                throw Api::validationFailed("items[$i].qty must be a positive integer");
            }
            $items[] = new PlaceItem($pid, $qty);
        }

        $o = $this->orders->place($channel, $req->customer_ref, $items);

        return Api::json(Resp::order($o), 201);
    }

    /**
     * GET /v1/orders supports two modes against the same endpoint, picked by the
     * query string:
     *   - ?customer_ref=<ref>  — single-customer history (the original mode)
     *   - any of ?status=&channel=&from=&to=&limit= — generic admin-side list.
     */
    #[Route('', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $q = $request->query;
        $tenant = Api::tenantFrom($request);

        $limit = 0;
        if (($s = (string) $q->get('limit', '')) !== '') {
            if (!ctype_digit($s) && !(str_starts_with($s, '-') && ctype_digit(substr($s, 1)))) {
                throw Api::validationFailed('limit must be an integer');
            }
            $limit = (int) $s;
        }

        $customer = (string) $q->get('customer_ref', '');

        // No generic filters present → keep the original by-customer path
        // (still 400s if customer_ref is also empty, like Phase 1 clients saw).
        if ((string) $q->get('status', '') === ''
            && (string) $q->get('channel', '') === ''
            && (string) $q->get('from', '') === ''
            && (string) $q->get('to', '') === ''
        ) {
            if ($customer === '') {
                throw Api::validationFailed('provide customer_ref or one of status/channel/from/to');
            }
            $orders = $this->orders->listByCustomer($tenant, $customer, $limit);

            return Api::json(Resp::list($orders));
        }

        $filter = new ListFilter(tenantId: $tenant, limit: $limit);
        if ($customer !== '') {
            $filter->customerRef = $customer;
        }
        if (($s = (string) $q->get('status', '')) !== '') {
            if (!Status::isValid($s)) {
                throw Api::validationFailed('status must be one of placed/confirmed/fulfilled/cancelled');
            }
            $filter->status = Status::from($s);
        }
        if (($s = (string) $q->get('channel', '')) !== '') {
            $filter->channel = Channel::parse($s);
        }
        if (($s = (string) $q->get('from', '')) !== '') {
            $filter->from = self::parseDate($s, 'from');
        }
        if (($s = (string) $q->get('to', '')) !== '') {
            // Half-open: include all of the to-date.
            $filter->to = self::parseDate($s, 'to')->modify('+1 day');
        }

        $orders = $this->orders->list($filter);

        return Api::json(Resp::list($orders));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(string $id): Response
    {
        $o = $this->orders->get(Api::validUuid($id, 'id'));

        return Api::json(Resp::order($o));
    }

    #[Route('/{id}/confirm', methods: ['POST'])]
    public function confirm(string $id): Response
    {
        $o = $this->orders->confirm(Api::validUuid($id, 'id'));

        return Api::json(Resp::order($o));
    }

    #[Route('/{id}/cancel', methods: ['POST'])]
    public function cancel(string $id): Response
    {
        $o = $this->orders->cancel(Api::validUuid($id, 'id'));

        return Api::json(Resp::order($o));
    }

    #[Route('/{id}/fulfill', methods: ['POST'])]
    public function fulfill(string $id): Response
    {
        $o = $this->orders->fulfill(Api::validUuid($id, 'id'));

        return Api::json(Resp::order($o));
    }

    /**
     * Parse a YYYY-MM-DD query bound as a UTC midnight. Rejects anything else
     * with validation-failed.
     */
    private static function parseDate(string $s, string $field): \DateTimeImmutable
    {
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $s, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if ($dt === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw Api::validationFailed("$field must be YYYY-MM-DD");
        }

        return $dt;
    }
}
