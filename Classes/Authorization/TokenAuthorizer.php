<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "monitoring".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace mteu\Monitoring\Authorization;

use mteu\Monitoring\Configuration\Authorizer\TokenAuthorizerConfiguration;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Crypto\HashService;

/**
 * TokenAuthorizer.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class TokenAuthorizer implements Authorizer
{
    private TokenAuthorizerConfiguration $tokenAuthorizerConfiguration;

    public function __construct(
        private HashService $hashService,
        private MonitoringConfiguration $configuration,
    ) {
        $this->tokenAuthorizerConfiguration = $this->configuration->tokenAuthorizerConfiguration;
    }

    public function isActive(): bool
    {
        return
            $this->tokenAuthorizerConfiguration->isEnabled() &&
            $this->tokenAuthorizerConfiguration->secret !== '';
    }

    public function isAuthorized(ServerRequestInterface $request): bool
    {
        $authToken = $request->getHeaderLine($this->tokenAuthorizerConfiguration->authHeaderName);

        if ($authToken === '') {
            return false;
        }

        if ($this->tokenAuthorizerConfiguration->secret === '') {
            return false;
        }

        return $this->hashService->validateHmac(
            $this->configuration->endpoint,
            $this->tokenAuthorizerConfiguration->secret,
            $authToken,
        );
    }

    public static function getPriority(): int
    {
        return 10;
    }
}
