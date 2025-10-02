<?php

declare(strict_types=1);

namespace Plushki\Production\Adapters\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Plushki\Production\Adapters\Http\Dto\StartTaskReq;
use Plushki\Production\App\TaskService;
use Plushki\Production\Domain\TaskStatus;

/**
 * Maps /v1/tasks.
 */
#[Route('/v1/tasks')]
final class TaskController
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $planRaw = (string) $request->query->get('plan_id', '');
        $planId = $planRaw !== '' ? Api::validUuid($planRaw, 'plan_id') : null;

        $statusRaw = (string) $request->query->get('status', '');
        if ($statusRaw !== '') {
            $status = TaskStatus::tryFrom($statusRaw);
            if ($status === null) {
                return Api::json(['items' => []]); // unknown status matches nothing
            }
        } else {
            $status = null;
        }

        $tasks = $this->tasks->list($planId, $status);

        return Api::json(['items' => array_map(Resp::task(...), $tasks)]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(string $id): Response
    {
        $t = $this->tasks->get(Api::validUuid($id, 'id'));

        return Api::json(Resp::task($t));
    }

    #[Route('/{id}/start', methods: ['POST'])]
    public function start(string $id, Request $request): Response
    {
        $id = Api::validUuid($id, 'id');
        $bakerId = null;
        if (trim($request->getContent()) !== '') {
            $req = Api::decode($request, $this->validator, StartTaskReq::class);
            if ($req->baker_id !== null && $req->baker_id !== '') {
                $bakerId = Api::validUuid($req->baker_id, 'baker_id');
            }
        }
        $t = $this->tasks->start($id, $bakerId);

        return Api::json(Resp::task($t));
    }

    #[Route('/{id}/complete', methods: ['POST'])]
    public function complete(string $id): Response
    {
        $t = $this->tasks->complete(Api::validUuid($id, 'id'));

        return Api::json(Resp::task($t));
    }

    #[Route('/{id}/cancel', methods: ['POST'])]
    public function cancel(string $id): Response
    {
        $t = $this->tasks->cancel(Api::validUuid($id, 'id'));

        return Api::json(Resp::task($t));
    }
}
