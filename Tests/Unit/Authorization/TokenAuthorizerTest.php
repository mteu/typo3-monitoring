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

namespace mteu\Monitoring\Tests\Unit\Authorization;

use mteu\Monitoring\Authorization\TokenAuthorizer;
use mteu\Monitoring\Configuration\Authorizer\AdminUserAuthorizerConfiguration;
use mteu\Monitoring\Configuration\Authorizer\TokenAuthorizerConfiguration;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Configuration\Provider\MiddlewareStatusProviderConfiguration;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;

/**
 * TokenAuthorizerTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(TokenAuthorizer::class)]
#[Framework\Attributes\BackupGlobals(true)]
final class TokenAuthorizerTest extends Framework\TestCase
{
    private const string DEFAULT_ENDPOINT = '/monitor/health';
    private const string DEFAULT_SECRET = 'test-secret';
    private const string DEFAULT_HEADER = 'X-AUTH';

    private HashService $hashService;

    protected function setUp(): void
    {
        // Globals will be backed up by PHPUnit for this test class
        $GLOBALS['TYPO3_CONF_VARS'] = ['SYS' => ['encryptionKey' => 'test-encryption-key-for-unit-tests']];

        $this->hashService = new HashService();
    }

    /**
     * @return \Generator<string, array{bool, string, bool}>
     */
    public static function isActiveTruthTable(): \Generator
    {
        yield 'enabled + secret -> active'         => [true, self::DEFAULT_SECRET, true];
        yield 'enabled + empty secret -> inactive' => [true, '', false];
        yield 'disabled + secret -> inactive'      => [false, self::DEFAULT_SECRET, false];
        yield 'disabled + empty secret -> inactive' => [false, '', false];
    }

    #[Test]
    #[DataProvider('isActiveTruthTable')]
    public function isActiveRequiresBothEnabledFlagAndNonEmptySecret(
        bool $enabled,
        string $secret,
        bool $expected,
    ): void {
        $authorizer = $this->createAuthorizer($enabled, $secret);

        self::assertSame($expected, $authorizer->isActive());
    }

    #[Test]
    public function isAuthorizedReturnsFalseWhenAuthorizationHeaderIsMissing(): void
    {
        $authorizer = $this->createAuthorizer();

        $request = $this->createRequest();

        self::assertFalse($authorizer->isAuthorized($request));
    }

    #[Test]
    public function isAuthorizedReturnsFalseWhenSecretIsEmptyEvenIfHeaderIsPresent(): void
    {
        // `isActive()` already gates on secret !== '', but `isAuthorized()` is callable directly.
        $authorizer = $this->createAuthorizer(enabled: true, secret: '');

        $request = $this->createRequest([self::DEFAULT_HEADER => 'any-value-here']);

        self::assertFalse($authorizer->isAuthorized($request));
    }

    #[Test]
    public function isAuthorizedAcceptsAValidHmacComputedAgainstTheConfiguredEndpoint(): void
    {
        $authorizer = $this->createAuthorizer();

        $validToken = $this->hashService->hmac(self::DEFAULT_ENDPOINT, self::DEFAULT_SECRET);

        $request = $this->createRequest([self::DEFAULT_HEADER => $validToken]);

        self::assertTrue($authorizer->isAuthorized($request));
    }

    #[Test]
    public function isAuthorizedRejectsAnArbitraryWrongToken(): void
    {
        $authorizer = $this->createAuthorizer();

        $request = $this->createRequest([self::DEFAULT_HEADER => 'not-a-real-hmac']);

        self::assertFalse($authorizer->isAuthorized($request));
    }

    #[Test]
    public function isAuthorizedRejectsTokenIssuedForADifferentEndpoint(): void
    {
        $authorizer = $this->createAuthorizer();

        $tokenForOtherEndpoint = $this->hashService->hmac('/other/path', self::DEFAULT_SECRET);

        $request = $this->createRequest([self::DEFAULT_HEADER => $tokenForOtherEndpoint]);

        self::assertFalse($authorizer->isAuthorized($request));
    }

    private function createAuthorizer(
        bool $enabled = true,
        string $secret = self::DEFAULT_SECRET,
        string $authHeaderName = self::DEFAULT_HEADER,
        string $endpoint = self::DEFAULT_ENDPOINT,
    ): TokenAuthorizer {
        $configuration = new MonitoringConfiguration(
            tokenAuthorizerConfiguration: new TokenAuthorizerConfiguration(
                enabled: $enabled,
                priority: 10,
                secret: $secret,
                authHeaderName: $authHeaderName,
            ),
            adminUserAuthorizerConfiguration: new AdminUserAuthorizerConfiguration(),
            providerConfiguration: new MiddlewareStatusProviderConfiguration(),
            endpoint: $endpoint,
        );

        return new TokenAuthorizer($configuration, $this->hashService);
    }

    /**
     * @param array<string, string> $headers
     */
    private function createRequest(array $headers = []): ServerRequestInterface
    {
        $request = new ServerRequest(new Uri('https://example.com' . self::DEFAULT_ENDPOINT), 'GET');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }
}
