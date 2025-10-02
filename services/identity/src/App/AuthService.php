<?php

declare(strict_types=1);

namespace Plushki\Identity\App;

use Symfony\Component\Uid\Uuid;
use Plushki\Identity\Domain\DomainException;
use Plushki\Identity\Domain\ErrorCode;
use Plushki\Identity\Domain\RefreshToken;
use Plushki\Identity\Domain\User;
use Plushki\Identity\Platform\Events\Envelope;
use Plushki\Identity\Ports\OutboxEvent;
use Plushki\Identity\Ports\OutboxRepo;
use Plushki\Identity\Ports\RefreshTokenRepo;
use Plushki\Identity\Ports\UserRepo;

/**
 * AuthService orchestrates the human-auth flows: register, login, refresh. State
 * changes that publish events go through the outbox port (never a direct broker
 * publish) so a partial commit cannot drop the event.
 */
final class AuthService
{
    public function __construct(
        private readonly UserRepo $users,
        private readonly RefreshTokenRepo $refresh,
        private readonly OutboxRepo $outbox,
        private readonly JwtIssuer $jwt,
    ) {
    }

    /**
     * Register creates a user, issues an access+refresh pair, and queues the
     * `identity.v1.user_created` event in the outbox — atomically with the user
     * row so neither can outlive the other.
     *
     * @return array{0: User, 1: TokenPair}
     */
    public function register(string $email, string $password, string $displayName): array
    {
        $u = User::create($email, $password, $displayName);

        $evt = $this->userCreatedEvent($u);
        $this->outbox->insertWithUser($u, $evt);

        return [$u, $this->issuePair($u)];
    }

    /**
     * Login verifies credentials and returns a fresh token pair. Any non-success
     * path returns InvalidCredentials so we don't leak whether an email exists.
     *
     * @return array{0: User, 1: TokenPair}
     */
    public function login(string $email, string $password): array
    {
        try {
            $u = $this->users->getByEmail('default', $email);
        } catch (DomainException $e) {
            if ($e->errorCode === ErrorCode::UserNotFound) {
                throw DomainException::of(ErrorCode::InvalidCredentials);
            }
            throw $e;
        }
        if ($u->isArchived()) {
            throw DomainException::of(ErrorCode::InvalidCredentials);
        }
        try {
            $u->verifyPassword($password);
        } catch (DomainException) {
            throw DomainException::of(ErrorCode::InvalidCredentials);
        }

        return [$u, $this->issuePair($u)];
    }

    /**
     * Refresh rotates a refresh token. The old token is marked used and the new
     * pair is issued atomically. A token can only be rotated once.
     *
     * @return array{0: User, 1: TokenPair}
     */
    public function refresh(string $plaintext): array
    {
        $hash = RefreshToken::hash($plaintext);
        try {
            $old = $this->refresh->getByHash($hash);
        } catch (DomainException) {
            throw DomainException::of(ErrorCode::RefreshTokenInvalid);
        }
        $old->ensureValid(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        $u = $this->users->getById($old->userId);
        if ($u->isArchived()) {
            throw DomainException::of(ErrorCode::UserArchived);
        }

        [$next, $plain] = RefreshToken::issue($u->id);
        $this->refresh->markUsedAndInsert($old->id, new \DateTimeImmutable('now', new \DateTimeZone('UTC')), $next);

        [$access, $accessExp] = $this->jwt->issueAccess($u);

        return [$u, new TokenPair($access, $accessExp, $plain, $next->expiresAt)];
    }

    /** Create + persist a new refresh token plus a fresh access JWT. */
    private function issuePair(User $u): TokenPair
    {
        [$rt, $plain] = RefreshToken::issue($u->id);
        $this->refresh->insert($rt);
        [$access, $accessExp] = $this->jwt->issueAccess($u);

        return new TokenPair($access, $accessExp, $plain, $rt->expiresAt);
    }

    /** Build the envelope that goes into the outbox row (architecture.md → Event envelope). */
    private function userCreatedEvent(User $u): OutboxEvent
    {
        $eventId = Uuid::v7()->toRfc4122();
        $occurredAt = $u->createdAt->format('Y-m-d\TH:i:s.uP');

        $envelope = Envelope::build(
            schema: 'identity.v1.user_created',
            data: [
                'user_id' => $u->id,
                'email' => $u->email,
                'display_name' => $u->displayName,
                'roles' => $u->roles,
            ],
            actorType: 'system',
            actorId: 'identity',
            occurredAt: $occurredAt,
            tenantId: $u->tenantId,
            eventId: $eventId,
        );

        return new OutboxEvent(
            eventId: $eventId,
            aggregateId: $u->id,
            aggregateType: 'user',
            schema: 'identity.v1.user_created',
            payload: $envelope->toJson(),
            occurredAt: $u->createdAt,
            tenantId: $u->tenantId,
            traceId: $envelope->traceId,
        );
    }
}
