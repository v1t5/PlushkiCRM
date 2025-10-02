<?php

declare(strict_types=1);

namespace Plushki\Template\Platform;

use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Amqp dials RabbitMQ.
 *
 * php-amqplib channels are not shared across processes; each worker/relay owns
 * its own connection + channel.
 */
final class Amqp
{
    public static function connect(string $url, string $clientName): AMQPStreamConnection
    {
        $u = parse_url($url);
        if ($u === false || !isset($u['host'])) {
            throw new \RuntimeException("invalid AMQP url: {$url}");
        }

        return new AMQPStreamConnection(
            host: $u['host'],
            port: $u['port'] ?? 5672,
            user: isset($u['user']) ? rawurldecode($u['user']) : 'guest',
            password: isset($u['pass']) ? rawurldecode($u['pass']) : 'guest',
            vhost: isset($u['path']) && $u['path'] !== '/' ? rawurldecode(ltrim($u['path'], '/')) : '/',
            insist: false,
            login_method: 'AMQPLAIN',
            login_response: null,
            locale: 'en_US',
            connection_timeout: 5.0,
            read_write_timeout: 12.0,
            context: null,
            keepalive: true,
            // heartbeat 0: PHP workers have no background heartbeat thread. A
            // publish-only polling worker (the relay) never blocks on a read, so
            // it would miss heartbeats and the broker would drop the connection
            // after ~2x the interval. We rely on TCP keepalive (above) for
            // dead-peer detection instead.
            heartbeat: 0,
            channel_rpc_timeout: 5.0,
        );
    }
}
