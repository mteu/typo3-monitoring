<?php

declare(strict_types=1);

namespace mteu\Monitoring\Configuration\Authorizer;

/**
 * AdminUserConfiguration.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class AdminUserAuthorizerConfiguration implements AuthorizerConfiguration
{
    public function __construct(
        private bool $enabled = false,
        private int $priority = -10,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}
