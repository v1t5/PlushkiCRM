<?php

declare(strict_types=1);

namespace Plushki\Identity\Adapters\Http;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Plushki\Identity\App\JwtIssuer;
use Plushki\Identity\Platform\Problem;
use Plushki\Identity\Platform\ProblemException;

/**
 * AuthSubscriber enforces the requireUser / requireAdmin gates. Routes opt in via
 * a `_auth` default ('user' or 'admin'); the subscriber validates the access JWT
 * with the issuer's public key (same key round-trips locally), then stashes the
 * subject and roles as request attributes for controllers to read.
 */
final class AuthSubscriber implements EventSubscriberInterface
{
    private const BASE = IdentityExceptionSubscriber::ERROR_BASE;

    public function __construct(private readonly JwtIssuer $jwt)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => ['onController', 16]];
    }

    public function onController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $auth = $request->attributes->get('_auth');
        if ($auth === null) {
            return; // public route
        }

        $raw = self::bearer($request);
        if ($raw === '') {
            throw new ProblemException(Problem::new(self::BASE . 'unauthorized', 'Unauthorized', 401, 'missing bearer token'));
        }

        try {
            $claims = JWT::decode($raw, new Key($this->jwt->publicPem(), 'RS256'));
        } catch (\Throwable) {
            throw new ProblemException(Problem::new(self::BASE . 'unauthorized', 'Unauthorized', 401, 'invalid token'));
        }

        $sub = (string) ($claims->sub ?? '');
        if ($sub === '') {
            throw new ProblemException(Problem::new(self::BASE . 'unauthorized', 'Unauthorized', 401, 'bad subject'));
        }
        $roles = isset($claims->roles) && \is_array($claims->roles) ? array_map('strval', $claims->roles) : [];

        $request->attributes->set('_auth_user_id', $sub);
        $request->attributes->set('_auth_roles', $roles);

        if ($auth === 'admin' && !\in_array('admin', $roles, true)) {
            throw new ProblemException(Problem::new(self::BASE . 'forbidden', 'Forbidden', 403, 'admin role required'));
        }
    }

    private static function bearer(Request $request): string
    {
        $h = (string) $request->headers->get('Authorization', '');
        if (!str_starts_with($h, 'Bearer ')) {
            return '';
        }

        return trim(substr($h, 7));
    }
}
