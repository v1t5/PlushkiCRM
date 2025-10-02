<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Plushki\Identity\App\MeService;

/**
 * MeController serves GET /me for the authenticated user. The AuthSubscriber
 * (gated by the `_auth: user` default) has already validated the JWT and stashed
 * the subject.
 */
final class MeController
{
    public function __construct(private readonly MeService $me)
    {
    }

    #[Route('/me', methods: ['GET'], defaults: ['_auth' => 'user'])]
    public function me(Request $request): Response
    {
        $id = (string) $request->attributes->get('_auth_user_id');
        $user = $this->me->get($id);

        return Api::json(Api::userResp($user));
    }
}
