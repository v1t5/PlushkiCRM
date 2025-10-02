<?php

declare(strict_types=1);

namespace Plushki\Inventory\Platform;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Migrator applies the goose-annotated *.sql files under migrations/. The files
 * keep the `-- +goose Up / -- +goose Down / StatementBegin / StatementEnd`
 * markers; we parse out the Up section and run it.
 *
 * Applied files are tracked in plushki_migrations (one row per filename), so a
 * second run is a no-op (pending up-migrations are applied on startup).
 */
final class Migrator
{
    public function __construct(
        private readonly Connection $conn,
        private readonly string $migrationsDir,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function up(): void
    {
        $this->conn->executeStatement(
            'CREATE TABLE IF NOT EXISTS plushki_migrations (
                version    TEXT PRIMARY KEY,
                applied_at TIMESTAMPTZ NOT NULL DEFAULT now()
            )'
        );

        $files = glob(rtrim($this->migrationsDir, '/') . '/*.sql') ?: [];
        sort($files); // 0001_*, 0002_*, ... lexical == numeric here

        foreach ($files as $file) {
            $version = basename($file);
            $already = (int) $this->conn->fetchOne(
                'SELECT count(*) FROM plushki_migrations WHERE version = ?',
                [$version]
            );
            if ($already > 0) {
                continue;
            }

            $sql = self::extractUp((string) file_get_contents($file));
            $this->conn->transactional(function (Connection $tx) use ($sql, $version): void {
                if (trim($sql) !== '') {
                    $tx->executeStatement($sql);
                }
                $tx->insert('plushki_migrations', ['version' => $version]);
            });
            $this->logger->info('migration applied', ['version' => $version]);
        }
    }

    /**
     * extractUp returns the SQL between `-- +goose Up` and `-- +goose Down`,
     * with the StatementBegin/End and comment markers stripped.
     */
    public static function extractUp(string $content): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $inUp = false;
        $out = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '-- +goose Up')) {
                $inUp = true;
                continue;
            }
            if (str_starts_with($trimmed, '-- +goose Down')) {
                break;
            }
            if (!$inUp) {
                continue;
            }
            if (str_starts_with($trimmed, '-- +goose')) {
                // StatementBegin / StatementEnd / NO TRANSACTION markers
                continue;
            }
            $out[] = $line;
        }

        return implode("\n", $out);
    }
}
