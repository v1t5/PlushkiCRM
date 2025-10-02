<?php

declare(strict_types=1);

namespace Plushki\Crm\Adapters\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Plushki\Crm\Adapters\Http\Dto\RegisterCustomerReq;
use Plushki\Crm\App\CustomerService;
use Plushki\Crm\App\LoyaltyService;
use Plushki\Crm\App\RegisterIdentity;
use Plushki\Crm\Domain\IdentityType;

/**
 * CustomerController maps the /v1/customers HTTP routes.
 */
#[Route('/v1/customers')]
final class CustomerController
{
    public function __construct(
        private readonly CustomerService $customers,
        private readonly LoyaltyService $loyalty,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $tenantId = (string) $request->query->get('tenant_id', '');
        $q = (string) $request->query->get('q', '');
        $limit = 0;
        if (($s = (string) $request->query->get('limit', '')) !== '') {
            if (!ctype_digit($s)) {
                throw Api::validationFailed('limit must be an integer');
            }
            $limit = (int) $s;
        }
        $rows = $this->customers->list($tenantId, $q, $limit);

        return Api::json(['items' => array_map(Resp::listCustomer(...), $rows)]);
    }

    #[Route('', methods: ['POST'])]
    public function register(Request $request): Response
    {
        $req = Api::decode($request, $this->validator, RegisterCustomerReq::class);
        $identities = [];
        foreach ($req->identities as $ri) {
            if (!\is_array($ri)) {
                throw Api::validationFailed('identities[] must be objects');
            }
            $type = IdentityType::tryFrom((string) ($ri['type'] ?? ''));
            if ($type === null) {
                throw Api::validationFailed('invalid identity type: ' . (string) ($ri['type'] ?? ''));
            }
            $value = (string) ($ri['value'] ?? '');
            if ($value === '') {
                throw Api::validationFailed('identity value is required');
            }
            $identities[] = new RegisterIdentity($type, $value);
        }
        [$c, $ids] = $this->customers->register($req->tenant_id, $req->display_name, $identities);

        return Api::json(Resp::customer($c, $ids), 201);
    }

    #[Route('/by-identity/{type}/{value}', methods: ['GET'])]
    public function byIdentity(string $type, string $value, Request $request): Response
    {
        if ($value === '') {
            throw Api::validationFailed('value is required');
        }
        $t = IdentityType::tryFrom($type);
        if ($t === null) {
            throw Api::validationFailed('invalid identity type: ' . $type);
        }
        $tenantId = (string) $request->query->get('tenant_id', '');
        [$customer] = $this->customers->resolveByIdentity($tenantId, $t, $value);
        [$c, $ids] = $this->customers->get($customer->id);

        return Api::json(Resp::customer($c, $ids));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(string $id): Response
    {
        [$c, $ids] = $this->customers->get(Api::validUuid($id, 'id'));

        return Api::json(Resp::customer($c, $ids));
    }

    #[Route('/{id}/loyalty', methods: ['GET'])]
    public function getLoyalty(string $id): Response
    {
        $l = $this->loyalty->get(Api::validUuid($id, 'id'));

        return Api::json(Resp::loyalty($l));
    }
}
