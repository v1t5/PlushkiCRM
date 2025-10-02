<?php

declare(strict_types=1);

namespace Plushki\Orders\Platform;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * Db opens a Doctrine DBAL connection from a postgres:// DSN. DBAL is used
 * purely as a hand-rolled SQL driver (no ORM, no entities).
 */
final class Db
{
    /**
     * Parses the DSN, opens a lazy DBAL connection and pings it once to surface
     * bad credentials / networking before the service finishes booting.
     */
    public static function open(string $dsn): Connection
    {
        $params = self::parseDsn($dsn);
        $conn = DriverManager::getConnection($params);
        // Ping once — DriverManager is lazy, so force a real connect.
        $conn->executeQuery('SELECT 1');

        return $conn;
    }

    /**
     * Turns postgres://user:pass@host:5432/dbname?sslmode=disable into DBAL
     * connection params, translated by hand to stay robust across DBAL versions.
     *
     * @return array<string, mixed>
     */
    public static function parseDsn(string $dsn): array
    {
        $u = parse_url($dsn);
        if ($u === false || !isset($u['host'])) {
            throw new \RuntimeException("invalid database DSN: {$dsn}");
        }

        $params = [
            'driver' => 'pdo_pgsql',
            'host' => $u['host'],
            'port' => $u['port'] ?? 5432,
            'user' => isset($u['user']) ? rawurldecode($u['user']) : 'postgres',
            'password' => isset($u['pass']) ? rawurldecode($u['pass']) : '',
            'dbname' => isset($u['path']) ? ltrim($u['path'], '/') : '',
        ];

        if (isset($u['query'])) {
            parse_str($u['query'], $q);
            if (isset($q['sslmode'])) {
                $params['sslmode'] = (string) $q['sslmode'];
            }
        }

        return $params;
    }
}
