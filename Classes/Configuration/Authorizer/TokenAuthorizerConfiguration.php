<?php

declare(strict_types=1);

namespace mteu\Monitoring\Configuration\Authorizer;

/**
 * TokenAuthorizerConfiguration.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class TokenAuthorizerConfiguration implements AuthorizerConfiguration
{
    public function __construct(
        private ?bool $enabled = false,
        private ?int $priority = 10,
        public ?string $secret = '',
        public ?string $authHeaderName = '',
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
