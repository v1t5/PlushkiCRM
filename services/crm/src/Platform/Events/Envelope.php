<?php

declare(strict_types=1);

namespace Plushki\Crm\Platform\Events;

use OpenTelemetry\API\Trace\Span;
use Symfony\Component\Uid\Uuid;

/**
 * Envelope is the JSON event envelope shared by every service (see
 * docs/architecture.md → Messaging):
 *
 *   { event_id, occurred_at, tenant_id, trace_id, actor:{type,id}, schema, data }
 *
 * The full envelope is what gets stored in outbox_events.payload and published
 * verbatim by the relay. The routing key is `<schema>.<tenant_id>`.
 */
final class Envelope
{
    /**
     * @param array<string, mixed> $data
     * @param array{type: string, id: string} $actor
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $occurredAt,
        public readonly string $tenantId,
        public readonly string $traceId,
        public readonly array $actor,
        public readonly string $schema,
        public readonly array $data,
    ) {
    }

    /**
     * build mints a new envelope for `schema`, capturing the current OTel
     * trace_id so the event correlates with the request that produced it.
     *
     * @param array<string, mixed> $data
     */
    public static function build(
        string $schema,
        array $data,
        string $actorType,
        string $actorId,
        string $occurredAt,
        string $tenantId = 'default',
        ?string $eventId = null,
    ): self {
        $span = Span::getCurrent()->getContext();
        $traceId = $span->isValid() ? $span->getTraceId() : '';

        return new self(
            eventId: $eventId ?? Uuid::v7()->toRfc4122(),
            occurredAt: $occurredAt,
            tenantId: $tenantId,
            traceId: $traceId,
            actor: ['type' => $actorType, 'id' => $actorId],
            schema: $schema,
            data: $data,
        );
    }

    /** Serialise the envelope to the JSON bytes stored in outbox / published. */
    public function toJson(): string
    {
        return json_encode([
            'event_id' => $this->eventId,
            'occurred_at' => $this->occurredAt,
            'tenant_id' => $this->tenantId,
            'trace_id' => $this->traceId,
            'actor' => $this->actor,
            'schema' => $this->schema,
            'data' => $this->data,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** Parse a published envelope back (consumer side). */
    public static function fromJson(string $json): self
    {
        /** @var array<string, mixed> $a */
        $a = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return new self(
            eventId: (string) ($a['event_id'] ?? ''),
            occurredAt: (string) ($a['occurred_at'] ?? ''),
            tenantId: (string) ($a['tenant_id'] ?? 'default'),
            traceId: (string) ($a['trace_id'] ?? ''),
            actor: \is_array($a['actor'] ?? null) ? $a['actor'] : ['type' => '', 'id' => ''],
            schema: (string) ($a['schema'] ?? ''),
            data: \is_array($a['data'] ?? null) ? $a['data'] : [],
        );
    }
}
