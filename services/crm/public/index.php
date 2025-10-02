<?php

declare(strict_types=1);

use Plushki\Crm\Kernel;

require_once \dirname(__DIR__) . '/vendor/autoload_runtime.php';

return static fn (array $context): Kernel => new Kernel(
    (string) ($context['APP_ENV'] ?? 'dev'),
    (bool) ($context['APP_DEBUG'] ?? false),
);
