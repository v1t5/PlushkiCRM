<?php

declare(strict_types=1);

namespace Plushki\Catalog\Adapters\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Plushki\Catalog\Platform\Problem;
use Plushki\Catalog\Platform\ProblemException;

/**
 * Small HTTP helpers the controllers share (decode/validate, json, uuid, tenant).
 * Request DTOs use snake_case public properties so they map directly to the JSON body.
 */
final class Api
{
    public const BASE = 'https://errors.plushki/catalog/';

    /**
     * Decode the JSON body into an instance of $dtoClass and validate it.
     * Throws problem+json on bad JSON / validation failure.
     *
     * @template T of object
     * @param class-string<T> $dtoClass
     * @return T
     */
    public static function decode(Request $request, ValidatorInterface $validator, string $dtoClass): object
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ProblemException(Problem::new(self::BASE . 'invalid-json', 'Invalid JSON', 400, $e->getMessage()));
        }
        if (!\is_array($data)) {
            throw new ProblemException(Problem::new(self::BASE . 'invalid-json', 'Invalid JSON', 400, 'expected an object'));
        }

        $dto = new $dtoClass();
        foreach (array_keys(get_object_vars($dto)) as $prop) {
            if (\array_key_exists($prop, $data)) {
                $dto->{$prop} = $data[$prop];
            }
        }

        $violations = $validator->validate($dto);
        if (\count($violations) > 0) {
            $parts = [];
            foreach ($violations as $v) {
                $parts[] = $v->getPropertyPath() . ': ' . $v->getMessage();
            }
            throw new ProblemException(Problem::new(
                self::BASE . 'validation-failed',
                'Validation Failed',
                400,
                implode('; ', $parts),
            ));
        }

        return $dto;
    }

    public static function json(mixed $body, int $status = 200): JsonResponse
    {
        return new JsonResponse($body, $status);
    }

    /** Parse a UUID path/field or throw an invalid-uuid problem (detail = $field). */
    public static function validUuid(string $raw, string $field): string
    {
        if (!Uuid::isValid($raw)) {
            throw new ProblemException(Problem::new(self::BASE . 'invalid-uuid', 'Invalid UUID', 400, $field));
        }

        return $raw;
    }

    /**
     * Resolve the tenant for the request. Phase 1 is single-tenant; every
     * request resolves to "default" unless an explicit X-Tenant-ID is present.
     */
    public static function tenantFrom(Request $request): string
    {
        $t = $request->headers->get('X-Tenant-ID', '');

        return $t !== '' ? $t : 'default';
    }
}