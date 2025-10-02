<?php

declare(strict_types=1);

namespace Plushki\Catalog\Adapters\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Plushki\Catalog\Adapters\Http\Dto\CreateProductReq;
use Plushki\Catalog\Adapters\Http\Dto\SetRecipeReq;
use Plushki\Catalog\App\ProductService;
use Plushki\Catalog\App\RecipeLineInput;
use Plushki\Catalog\App\RecipeService;
use Plushki\Catalog\Platform\Problem;
use Plushki\Catalog\Platform\ProblemException;

/** Maps /v1/products and the nested /recipe endpoints. */
#[Route('/v1/products')]
final class ProductController
{
    public function __construct(
        private readonly ProductService $products,
        private readonly RecipeService $recipes,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $req = Api::decode($request, $this->validator, CreateProductReq::class);
        $catId = $req->category_id !== null ? Api::validUuid($req->category_id, 'category_id') : null;
        $p = $this->products->create($req->sku, $req->name, $req->description, $req->price_kopecks, $catId);

        return Api::json(Resp::product($p), 201);
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $raw = (string) $request->query->get('category_id', '');
        $catId = $raw !== '' ? Api::validUuid($raw, 'category_id') : null;
        $prods = $this->products->list(Api::tenantFrom($request), $catId);

        return Api::json(['items' => array_map(Resp::product(...), $prods)]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(string $id): Response
    {
        $p = $this->products->get(Api::validUuid($id, 'id'));

        return Api::json(Resp::product($p));
    }

    #[Route('/{id}/recipe', methods: ['GET'])]
    public function getRecipe(string $id): Response
    {
        $productId = Api::validUuid($id, 'id');
        $lines = $this->recipes->getByProductId($productId);

        return Api::json(Resp::recipe($productId, $lines));
    }

    #[Route('/{id}/recipe', methods: ['PUT'])]
    public function setRecipe(string $id, Request $request): Response
    {
        $productId = Api::validUuid($id, 'id');
        $req = Api::decode($request, $this->validator, SetRecipeReq::class);

        $inputs = [];
        foreach ($req->lines as $idx => $line) {
            if (!\is_array($line)) {
                throw $this->lineProblem("lines[{$idx}]");
            }
            $qty = $line['qty'] ?? null;
            if (!\is_int($qty) || $qty <= 0) {
                throw $this->lineProblem("lines[{$idx}].qty");
            }
            $ingId = Api::validUuid((string) ($line['ingredient_id'] ?? ''), "lines[{$idx}].ingredient_id");
            $unitId = Api::validUuid((string) ($line['unit_id'] ?? ''), "lines[{$idx}].unit_id");
            $inputs[] = new RecipeLineInput($ingId, $unitId, $qty);
        }

        $lines = $this->recipes->set($productId, $inputs);

        return Api::json(Resp::recipe($productId, $lines));
    }

    private function lineProblem(string $field): ProblemException
    {
        return new ProblemException(Problem::new(Api::BASE . 'validation-failed', 'Validation Failed', 400, $field));
    }
}