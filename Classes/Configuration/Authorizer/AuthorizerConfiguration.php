<?php

declare(strict_types=1);

namespace mteu\Monitoring\Configuration\Authorizer;

interface AuthorizerConfiguration
{
    public function isEnabled(): bool;
    public function getPriority(): int;
}
