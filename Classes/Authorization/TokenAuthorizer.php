<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "mteu/typo3-monitoring".
 *
 * Copyright (C) 2025 Martin Adler <mteu@mailbox.org>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace mteu\Monitoring\Authorization;

use mteu\Monitoring\Configuration\Extension;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Crypto\HashService;

/**
 * TokenAuthorizer.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class TokenAuthorizer implements Authorizer
{
    public const string AUTH_HEADER_NAME = 'X-TYPO3-MONITORING-AUTH';
    private string $endpoint;
    private string $secret;

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public function __construct(
        private Extension $extensionConfiguration,
        private HashService $hashService,
    ) {
        $this->endpoint = $this->extensionConfiguration->getEndpointFromConfiguration();
        $this->secret = $this->extensionConfiguration->getSecretFromConfiguration();
    }

    public function isAuthorized(ServerRequestInterface $request): bool
    {
        $authToken = $request->getHeaderLine(self::AUTH_HEADER_NAME);

        if ($authToken === '') {
            return false;
        }

        // safely assert that the secret is not empty after being evaluated in the process() method
        assert($this->secret !== '');

        return $this->hashService->validateHmac($this->endpoint, $this->secret, $authToken);
    }

    public static function getPriority(): int
    {
        return 10;
    }

    public static function getAuthHeaderName(): string
    {
        return self::AUTH_HEADER_NAME;
    }
}
