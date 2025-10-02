<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Plushki\Identity\Adapters\Http\Dto\AdminCreateUserReq;
use Plushki\Identity\Adapters\Http\Dto\AdminResetPasswordReq;
use Plushki\Identity\Adapters\Http\Dto\AdminUpdateProfileReq;
use Plushki\Identity\Adapters\Http\Dto\AdminUpdateRolesReq;
use Plushki\Identity\App\AuthService;
use Plushki\Identity\App\UserAdminService;
use Plushki\Identity\Domain\User;
use Plushki\Identity\Platform\Problem;
use Plushki\Identity\Platform\ProblemException;
use Plushki\Identity\Ports\UserListParams;

/**
 * AdminUserController maps the admin-only user-management endpoints. The whole
 * class is gated by `_auth: admin` (requireUser → requireAdmin); handlers assume
 * an authed admin.
 */
#[Route('/admin/users', defaults: ['_auth' => 'admin'])]
final class AdminUserController
{
    public function __construct(
        private readonly UserAdminService $admin,
        private readonly AuthService $auth,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $q = $request->query;
        $users = $this->admin->list(new UserListParams(
            tenantId: 'default',
            q: (string) $q->get('q', ''),
            includeArchived: $q->get('include_archived') === 'true',
            limit: (int) $q->get('limit', 0),
            offset: (int) $q->get('offset', 0),
        ));

        return Api::json(array_map(Api::adminUserResp(...), $users));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(string $id): Response
    {
        return Api::json(Api::adminUserResp($this->admin->get(Api::validUuid($id))));
    }

    /**
     * Admin-only user creation: reuse AuthService::register (so user_created
     * fires), then merge in any extra requested roles.
     */
    #[Route('', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $req = Api::decode($request, $this->validator, AdminCreateUserReq::class);
        foreach ($req->roles as $role) {
            if (!User::isAllowedRole((string) $role)) {
                throw new ProblemException(Problem::new(
                    IdentityExceptionSubscriber::ERROR_BASE . 'invalid-role',
                    'Invalid Role',
                    400,
                    (string) $role,
                ));
            }
        }
        [$user] = $this->auth->register($req->email, $req->password, $req->display_name);
        if ($req->roles !== []) {
            $merged = array_merge($user->roles, array_map('strval', $req->roles));
            $user = $this->admin->updateRoles($user->id, $merged);
        }

        return Api::json(Api::adminUserResp($user), 201);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function updateProfile(string $id, Request $request): Response
    {
        $req = Api::decode($request, $this->validator, AdminUpdateProfileReq::class);
        $user = $this->admin->updateProfile(Api::validUuid($id), $req->display_name);

        return Api::json(Api::adminUserResp($user));
    }

    #[Route('/{id}/roles', methods: ['PUT'])]
    public function updateRoles(string $id, Request $request): Response
    {
        $req = Api::decode($request, $this->validator, AdminUpdateRolesReq::class);
        $user = $this->admin->updateRoles(Api::validUuid($id), array_map('strval', $req->roles));

        return Api::json(Api::adminUserResp($user));
    }

    #[Route('/{id}/password', methods: ['PUT'])]
    public function resetPassword(string $id, Request $request): Response
    {
        $req = Api::decode($request, $this->validator, AdminResetPasswordReq::class);
        $this->admin->resetPassword(Api::validUuid($id), $req->password);

        return new Response('', 204);
    }

    #[Route('/{id}/archive', methods: ['POST'])]
    public function archive(string $id, Request $request): Response
    {
        $id = Api::validUuid($id);
        // Self-archive is refused — losing the only admin's session bricks the UI.
        if ((string) $request->attributes->get('_auth_user_id') === $id) {
            throw new ProblemException(Problem::new(
                IdentityExceptionSubscriber::ERROR_BASE . 'self-archive',
                'Cannot Archive Self',
                400,
            ));
        }

        return Api::json(Api::adminUserResp($this->admin->setArchived($id, true)));
    }

    #[Route('/{id}/restore', methods: ['POST'])]
    public function restore(string $id): Response
    {
        return Api::json(Api::adminUserResp($this->admin->setArchived(Api::validUuid($id), false)));
    }
}
