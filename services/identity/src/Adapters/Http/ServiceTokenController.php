<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Plushki\Identity\Adapters\Http\Dto\AdminCreateTokenReq;
use Plushki\Identity\App\CreateService;

/**
 * ServiceTokenController provisions service tokens. Currently unauthenticated
 * (admin gating belongs to the gateway in production); dev runs use it to seed a
 * token for tg-bot etc. The plaintext is returned ONCE and is not recoverable.
 */
final class ServiceTokenController
{
    public function __construct(
        private readonly CreateService $create,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/admin/service-tokens', methods: ['POST'])]
    public function createServiceToken(Request $request): Response
    {
        $req = Api::decode($request, $this->validator, AdminCreateTokenReq::class);
        [$t, $plain] = $this->create->create($req->name, $req->actor_type, array_map('strval', $req->scopes));

        return Api::json([
            'id' => $t->id,
            'name' => $t->name,
            'actor_type' => $t->actorType,
            'scopes' => $t->scopes,
            'token' => $plain,
        ], 201);
    }
}
