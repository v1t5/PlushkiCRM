<?php

declare(strict_types=1);

namespace Plushki\Crm\Adapters\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Plushki\Crm\Platform\Problem;
use Plushki\Crm\Platform\ProblemException;

/**
 * Api collects the small HTTP helpers the controllers share (decode + validate,
 * respond).
 */
final class Api
{
    public const BASE = 'https://errors.plushki/crm/';

    /**
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
            throw new ProblemException(Problem::new(self::BASE . 'validation-failed', 'Validation Failed', 400, implode('; ', $parts)));
        }

        return $dto;
    }

    public static function json(mixed $body, int $status = 200): JsonResponse
    {
        return new JsonResponse($body, $status);
    }

    public static function validUuid(string $raw, string $field): string
    {
        if (!Uuid::isValid($raw)) {
            throw new ProblemException(Problem::new(self::BASE . 'invalid-uuid', 'Invalid UUID', 400, $field));
        }

        return $raw;
    }

    public static function validationFailed(string $detail): ProblemException
    {
        return new ProblemException(Problem::new(self::BASE . 'validation-failed', 'Validation Failed', 400, $detail));
    }
}
