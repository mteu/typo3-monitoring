<?php

declare(strict_types=1);

namespace mteu\Monitoring\Tests\Unit\Configuration\Authorizer;

use mteu\Monitoring as Src;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;

/**
 * AdminUserAuthorizerConfigurationTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Configuration\Authorizer\AdminUserAuthorizerConfiguration::class)]
final class AdminUserAuthorizerConfigurationTest extends Framework\TestCase
{
    #[Test]
    public function testConstructorWithDefaultValues(): void
    {
        $subject = new Src\Configuration\Authorizer\AdminUserAuthorizerConfiguration();

        self::assertFalse($subject->isEnabled(), 'Default enabled should be false');
        self::assertSame(-10, $subject->getPriority(), 'Default priority should be -10');
    }

    #[Test]
    #[Framework\Attributes\DataProvider('provideConstructorValues')]
    public function testConstructorWithCustomValues(bool $enabled, int $priority): void
    {
        $subject = new Src\Configuration\Authorizer\AdminUserAuthorizerConfiguration(
            enabled: $enabled,
            priority: $priority,
        );

        self::assertSame($enabled, $subject->isEnabled(), 'Enabled value should match constructor parameter');
        self::assertSame($priority, $subject->getPriority(), 'Priority value should match constructor parameter');
    }

    #[Test]
    public function testImplementsAuthorizerConfigurationInterface(): void
    {
        $subject = new Src\Configuration\Authorizer\AdminUserAuthorizerConfiguration();

        self::assertInstanceOf(
            Src\Configuration\Authorizer\AuthorizerConfiguration::class,
            $subject,
            'AdminUserAuthorizerConfiguration should implement AuthorizerConfiguration interface'
        );
    }

    #[Test]
    public function testIsEnabledMethod(): void
    {
        $enabledSubject = new Src\Configuration\Authorizer\AdminUserAuthorizerConfiguration(enabled: true);
        $disabledSubject = new Src\Configuration\Authorizer\AdminUserAuthorizerConfiguration(enabled: false);

        self::assertTrue($enabledSubject->isEnabled(), 'isEnabled should return true when enabled is true');
        self::assertFalse($disabledSubject->isEnabled(), 'isEnabled should return false when enabled is false');
    }

    #[Test]
    public function testGetPriorityMethod(): void
    {
        $highPrioritySubject = new Src\Configuration\Authorizer\AdminUserAuthorizerConfiguration(priority: 100);
        $lowPrioritySubject = new Src\Configuration\Authorizer\AdminUserAuthorizerConfiguration(priority: -100);

        self::assertSame(100, $highPrioritySubject->getPriority(), 'getPriority should return the configured priority');
        self::assertSame(-100, $lowPrioritySubject->getPriority(), 'getPriority should return negative priorities correctly');
    }

    #[Test]
    public function testPublicPropertiesAreAccessible(): void
    {
        $subject = new Src\Configuration\Authorizer\AdminUserAuthorizerConfiguration(
            enabled: true,
            priority: 42,
        );

        self::assertTrue($subject->enabled, 'enabled property should be publicly accessible');
        self::assertSame(42, $subject->priority, 'priority property should be publicly accessible');
    }

    #[Test]
    public function testDefaultPriorityIsNegative(): void
    {
        $subject = new Src\Configuration\Authorizer\AdminUserAuthorizerConfiguration();

        self::assertTrue($subject->getPriority() < 0, 'Default priority should be negative (lower than TokenAuthorizer)');
    }

    /**
     * @return \Generator<string, array{enabled: bool, priority: int}>
     */
    public static function provideConstructorValues(): \Generator
    {
        yield 'enabled with positive priority' => [
            'enabled' => true,
            'priority' => 25,
        ];

        yield 'disabled with negative priority' => [
            'enabled' => false,
            'priority' => -50,
        ];

        yield 'enabled with zero priority' => [
            'enabled' => true,
            'priority' => 0,
        ];

        yield 'disabled with high positive priority' => [
            'enabled' => false,
            'priority' => 999,
        ];

        yield 'enabled with very low priority' => [
            'enabled' => true,
            'priority' => -999,
        ];

        yield 'enabled with default token authorizer priority' => [
            'enabled' => true,
            'priority' => 10,
        ];

        yield 'disabled with default admin user priority' => [
            'enabled' => false,
            'priority' => -10,
        ];
    }
}
