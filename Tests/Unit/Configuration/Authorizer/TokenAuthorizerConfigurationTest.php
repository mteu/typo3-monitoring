<?php

declare(strict_types=1);

namespace mteu\Monitoring\Tests\Unit\Configuration\Authorizer;

use mteu\Monitoring as Src;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;

/**
 * TokenAuthorizerConfigurationTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Configuration\Authorizer\TokenAuthorizerConfiguration::class)]
final class TokenAuthorizerConfigurationTest extends Framework\TestCase
{
    #[Test]
    public function testConstructorWithDefaultValues(): void
    {
        $subject = new Src\Configuration\Authorizer\TokenAuthorizerConfiguration();

        self::assertFalse($subject->isEnabled(), 'Default enabled should be false');
        self::assertSame(10, $subject->getPriority(), 'Default priority should be 10');
        self::assertSame('', $subject->secret, 'Default secret should be empty string');
        self::assertSame('', $subject->authHeaderName, 'Default authHeaderName should be empty string');
    }

    #[Test]
    #[Framework\Attributes\DataProvider('provideConstructorValues')]
    public function testConstructorWithCustomValues(
        bool $enabled,
        int $priority,
        string $secret,
        string $authHeaderName
    ): void {
        $subject = new Src\Configuration\Authorizer\TokenAuthorizerConfiguration(
            enabled: $enabled,
            priority: $priority,
            secret: $secret,
            authHeaderName: $authHeaderName,
        );

        self::assertSame($enabled, $subject->isEnabled(), 'Enabled value should match constructor parameter');
        self::assertSame($priority, $subject->getPriority(), 'Priority value should match constructor parameter');
        self::assertSame($secret, $subject->secret, 'Secret value should match constructor parameter');
        self::assertSame($authHeaderName, $subject->authHeaderName, 'AuthHeaderName value should match constructor parameter');
    }

    #[Test]
    public function testImplementsAuthorizerConfigurationInterface(): void
    {
        $subject = new Src\Configuration\Authorizer\TokenAuthorizerConfiguration();

        self::assertInstanceOf(
            Src\Configuration\Authorizer\AuthorizerConfiguration::class,
            $subject,
            'TokenAuthorizerConfiguration should implement AuthorizerConfiguration interface'
        );
    }

    #[Test]
    public function testIsEnabledMethod(): void
    {
        $enabledSubject = new Src\Configuration\Authorizer\TokenAuthorizerConfiguration(enabled: true);
        $disabledSubject = new Src\Configuration\Authorizer\TokenAuthorizerConfiguration(enabled: false);

        self::assertTrue($enabledSubject->isEnabled(), 'isEnabled should return true when enabled is true');
        self::assertFalse($disabledSubject->isEnabled(), 'isEnabled should return false when enabled is false');
    }

    #[Test]
    public function testGetPriorityMethod(): void
    {
        $highPrioritySubject = new Src\Configuration\Authorizer\TokenAuthorizerConfiguration(priority: 100);
        $lowPrioritySubject = new Src\Configuration\Authorizer\TokenAuthorizerConfiguration(priority: -50);

        self::assertSame(100, $highPrioritySubject->getPriority(), 'getPriority should return the configured priority');
        self::assertSame(-50, $lowPrioritySubject->getPriority(), 'getPriority should return negative priorities correctly');
    }

    #[Test]
    public function testPublicPropertiesAreAccessible(): void
    {
        $subject = new Src\Configuration\Authorizer\TokenAuthorizerConfiguration(
            enabled: true,
            priority: 25,
            secret: 'test-secret-key',
            authHeaderName: 'X-Test-Auth',
        );

        self::assertTrue($subject->enabled, 'enabled property should be publicly accessible');
        self::assertSame(25, $subject->priority, 'priority property should be publicly accessible');
        self::assertSame('test-secret-key', $subject->secret, 'secret property should be publicly accessible');
        self::assertSame('X-Test-Auth', $subject->authHeaderName, 'authHeaderName property should be publicly accessible');
    }

    /**
     * @return \Generator<string, array{enabled: bool, priority: int, secret: string, authHeaderName: string}>
     */
    public static function provideConstructorValues(): \Generator
    {
        yield 'enabled with positive priority and values' => [
            'enabled' => true,
            'priority' => 50,
            'secret' => 'secure-token-123',
            'authHeaderName' => 'X-API-Token',
        ];

        yield 'disabled with negative priority' => [
            'enabled' => false,
            'priority' => -25,
            'secret' => 'another-secret',
            'authHeaderName' => 'Authorization',
        ];

        yield 'enabled with zero priority' => [
            'enabled' => true,
            'priority' => 0,
            'secret' => '',
            'authHeaderName' => 'Bearer',
        ];

        yield 'complex configuration' => [
            'enabled' => true,
            'priority' => 999,
            'secret' => 'complex-secret-with-special-chars-!@#$%',
            'authHeaderName' => 'X-Custom-Monitoring-Auth',
        ];

        yield 'minimal enabled configuration' => [
            'enabled' => true,
            'priority' => 1,
            'secret' => 'min',
            'authHeaderName' => 'X',
        ];
    }
}
