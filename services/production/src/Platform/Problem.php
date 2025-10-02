<?php

declare(strict_types=1);

namespace Plushki\Production\Platform;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * RFC 7807 application/problem+json response body. `type` is a stable URI that
 * frontends localise — never put human-readable text in fields a client will display.
 */
final class Problem
{
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly int $status,
        public readonly string $detail = '',
        public readonly string $instance = '',
    ) {
    }

    /** Build a problem from a stable type URI like https://errors.plushki/<svc>/<code>. */
    public static function new(string $typeURI, string $title, int $status, string $detail = ''): self
    {
        return new self($typeURI, $title, $status, $detail);
    }

    public function toResponse(): JsonResponse
    {
        $body = [
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
        ];
        if ($this->detail !== '') {
            $body['detail'] = $this->detail;
        }
        if ($this->instance !== '') {
            $body['instance'] = $this->instance;
        }

        $resp = new JsonResponse($body, $this->status);
        $resp->headers->set('Content-Type', 'application/problem+json');

        return $resp;
    }

    public static function notFound(string $path): self
    {
        return new self('https://errors.plushki/common/not-found', 'Not Found', 404, $path);
    }

    public static function methodNotAllowed(string $what): self
    {
        return new self('https://errors.plushki/common/method-not-allowed', 'Method Not Allowed', 405, $what);
    }
}
