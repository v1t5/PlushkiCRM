<?php

declare(strict_types=1);

namespace Plushki\Inventory\Platform;

/**
 * ProblemException carries an RFC 7807 Problem up through the HTTP stack. App
 * and HTTP code throw it; ProblemSubscriber renders it.
 */
final class ProblemException extends \RuntimeException
{
    public function __construct(public readonly Problem $problem)
    {
        parent::__construct($problem->title);
    }
}
