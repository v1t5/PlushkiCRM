<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Plushki\Identity\App\TokenPair;
use Plushki\Identity\Domain\User;
use Plushki\Identity\Platform\Problem;
use Plushki\Identity\Platform\ProblemException;

/**
 * Api collects the small HTTP helpers the controllers share (decode-and-validate,
 * respond, user-response mapping). Request DTOs use snake_case public properties
 * so they map 1:1 to the JSON body.
 */
final class Api
{
    private const BASE = IdentityExceptionSubscriber::ERROR_BASE;

    /**
     * Decode the JSON body into an instance of $dtoClass and validate it.
     * Throws a problem+json on bad JSON / validation failure.
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

    /** @return array<string, mixed> */
    public static function userResp(User $u): array
    {
        return [
            'id' => $u->id,
            'tenant_id' => $u->tenantId,
            'email' => $u->email,
            'display_name' => $u->displayName,
            'roles' => $u->roles,
        ];
    }

    /** @return array<string, mixed> */
    public static function adminUserResp(User $u): array
    {
        $resp = self::userResp($u);
        $resp['created_at'] = $u->createdAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP');
        if ($u->archivedAt !== null) {
            $resp['archived_at'] = $u->archivedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP');
        }

        return $resp;
    }

    /** @return array<string, mixed> */
    public static function tokenPair(TokenPair $p): array
    {
        return [
            'access_token' => $p->accessToken,
            'access_expiry' => $p->accessExpiry->format('Y-m-d\TH:i:s.uP'),
            'refresh_token' => $p->refreshToken,
            'refresh_expiry' => $p->refreshExpiry->format('Y-m-d\TH:i:s.uP'),
            'token_type' => 'Bearer',
        ];
    }

    public static function validUuid(string $raw): string
    {
        if (!\Symfony\Component\Uid\Uuid::isValid($raw)) {
            throw new ProblemException(Problem::new(self::BASE . 'invalid-user-id', 'Invalid User ID', 400, $raw));
        }

        return $raw;
    }
}
