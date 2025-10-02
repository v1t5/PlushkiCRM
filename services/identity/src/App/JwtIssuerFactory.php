<?php

declare(strict_types=1);

namespace Plushki\Identity\App;

use Psr\Log\LoggerInterface;
use Plushki\Identity\Platform\Config;

/**
 * JwtIssuerFactory builds the JwtIssuer. A configured PEM is used as-is. With no
 * key in dev, the request-per-boot model would regenerate an ephemeral key on
 * every request, so instead we persist a dev key under var/ (stable across
 * requests, regenerated if the volume is cleared). A non-dev env without a
 * configured key is a fatal misconfiguration.
 */
final class JwtIssuerFactory
{
    public function __construct(
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
    ) {
    }

    public function create(): JwtIssuer
    {
        $cfg = $this->config;

        if ($cfg->jwtPrivateKeyPath !== '') {
            $this->logger->info('jwt key loaded', [
                'path' => $cfg->jwtPrivateKeyPath,
                'kid' => $cfg->jwtKeyId,
            ]);

            return JwtIssuer::fromPem($cfg->jwtPrivateKeyPath, $cfg->jwtKeyId, $cfg->jwtIssuer);
        }

        if ($cfg->env !== 'dev') {
            throw new \RuntimeException('APP_JWT_PRIVATE_KEY_PATH is required when APP_ENV != dev');
        }

        $path = $this->projectDir . '/var/jwt/dev-key.pem';
        $pem = is_file($path) ? (string) file_get_contents($path) : null;
        if ($pem === null) {
            $pem = JwtIssuer::newPrivatePem();
            @mkdir(\dirname($path), 0o775, true);
            file_put_contents($path, $pem);
            $this->logger->warning('jwt dev key generated (persisted under var/jwt) — clearing var invalidates tokens', [
                'kid' => $cfg->jwtKeyId,
            ]);
        }

        return JwtIssuer::fromPemString($pem, $cfg->jwtKeyId, $cfg->jwtIssuer);
    }
}
