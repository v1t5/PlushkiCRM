<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Plushki\Identity\App\AuthService;
use Plushki\Identity\App\IntrospectService;
use Plushki\Identity\App\JwtIssuer;
use Plushki\Identity\Adapters\Http\Dto\IntrospectReq;
use Plushki\Identity\Adapters\Http\Dto\LoginReq;
use Plushki\Identity\Adapters\Http\Dto\RefreshReq;
use Plushki\Identity\Adapters\Http\Dto\RegisterReq;
use Plushki\Identity\Domain\DomainException;

/** AuthController maps the public auth routes: /auth/*, JWKS, and introspect. */
final class AuthController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly IntrospectService $introspect,
        private readonly JwtIssuer $jwt,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/auth/register', methods: ['POST'])]
    public function register(Request $request): Response
    {
        $req = Api::decode($request, $this->validator, RegisterReq::class);
        [$user, $pair] = $this->auth->register($req->email, $req->password, $req->display_name);

        return Api::json(['user' => Api::userResp($user), 'tokens' => Api::tokenPair($pair)], 201);
    }

    #[Route('/auth/login', methods: ['POST'])]
    public function login(Request $request): Response
    {
        $req = Api::decode($request, $this->validator, LoginReq::class);
        [$user, $pair] = $this->auth->login($req->email, $req->password);

        return Api::json(['user' => Api::userResp($user), 'tokens' => Api::tokenPair($pair)]);
    }

    #[Route('/auth/refresh', methods: ['POST'])]
    public function refresh(Request $request): Response
    {
        $req = Api::decode($request, $this->validator, RefreshReq::class);
        [$user, $pair] = $this->auth->refresh($req->refresh_token);

        return Api::json(['user' => Api::userResp($user), 'tokens' => Api::tokenPair($pair)]);
    }

    /**
     * Gateway-facing service-token introspection. A bad/unknown token is not an
     * error — it returns {active:false}.
     */
    #[Route('/auth/introspect', methods: ['POST'])]
    public function introspectHandler(Request $request): Response
    {
        $req = Api::decode($request, $this->validator, IntrospectReq::class);
        try {
            $res = $this->introspect->introspect($req->token);
        } catch (DomainException) {
            return Api::json(['active' => false]);
        }

        return Api::json([
            'active' => true,
            'actor_type' => $res->actorType,
            'actor_id' => $res->actorId,
            'tenant_id' => $res->tenantId,
            'scopes' => $res->scopes,
        ]);
    }

    /** Public JWKS — the gateway fetches this on boot and refreshes on a TTL. */
    #[Route('/.well-known/jwks.json', methods: ['GET'])]
    public function jwks(): JsonResponse
    {
        $resp = new JsonResponse($this->jwt->jwks());
        $resp->headers->set('Content-Type', 'application/jwk-set+json');
        $resp->headers->set('Cache-Control', 'public, max-age=300');

        return $resp;
    }
}
