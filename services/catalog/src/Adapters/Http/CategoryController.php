<?php

declare(strict_types=1);

namespace Plushki\Catalog\Adapters\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Plushki\Catalog\Adapters\Http\Dto\CreateCategoryReq;
use Plushki\Catalog\App\CategoryService;

/** Maps /v1/categories. Phase 1: no auth. */
#[Route('/v1/categories')]
final class CategoryController
{
    public function __construct(
        private readonly CategoryService $categories,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $req = Api::decode($request, $this->validator, CreateCategoryReq::class);
        $c = $this->categories->create($req->name, $req->slug, $req->sort_order);

        return Api::json(Resp::category($c), 201);
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $cats = $this->categories->list(Api::tenantFrom($request));

        return Api::json(['items' => array_map(Resp::category(...), $cats)]);
    }
}