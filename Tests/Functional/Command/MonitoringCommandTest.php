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

namespace mteu\Monitoring\Tests\Functional\Command;

use mteu\Monitoring\Cache\MonitoringCacheManager;
use mteu\Monitoring\Command\MonitoringCommand;
use mteu\Monitoring\Handler\MonitoringExecutionHandler;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Tests\Functional\Fixtures\CacheableProvider;
use mteu\Monitoring\Tests\Functional\Fixtures\NonCacheableProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * MonitoringCommandTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(MonitoringCommand::class)]
final class MonitoringCommandTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'monitoring',
        'typed_extconf',
    ];

    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'caching' => [
                'cacheConfigurations' => [
                    'typo3_monitoring' => [
                        'frontend' => 'TYPO3\\CMS\\Core\\Cache\\Frontend\\VariableFrontend',
                        'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend',
                        'options' => [],
                        'groups' => ['system'],
                    ],
                ],
            ],
        ],
    ];

    private MonitoringExecutionHandler $executionHandler;
    private MonitoringCacheManager $cacheManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->executionHandler = $this->get(MonitoringExecutionHandler::class);
        $this->cacheManager = $this->get(MonitoringCacheManager::class);
        $this->cacheManager->flushAll();
    }

    #[Test]
    public function reportsInvalidWhenNoActiveProvidersAreRegistered(): void
    {
        $tester = $this->createTester([]);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('No active providers available', $tester->getDisplay());
    }

    #[Test]
    public function freshRunDoesNotLabelCacheableProvidersAsCached(): void
    {
        $provider = new CacheableProvider('fresh-provider');
        $tester = $this->createTester([$provider]);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(1, $provider->getExecutionCount());
        self::assertStringNotContainsString('(cached)', $tester->getDisplay());
        self::assertStringContainsString('fresh-provider', $tester->getDisplay());
    }

    #[Test]
    public function warmCacheCausesCacheableProvidersToBeLabelledAsCached(): void
    {
        $provider = new CacheableProvider('warm-provider');
        $tester = $this->createTester([$provider]);

        $tester->execute([]);
        self::assertSame(1, $provider->getExecutionCount());

        $tester->execute([]);

        self::assertSame(1, $provider->getExecutionCount(), 'Warm cache must short-circuit execute()');
        self::assertStringContainsString('(cached)', $tester->getDisplay());
    }

    #[Test]
    public function noCacheOptionForcesFreshExecutionAndSuppressesCachedLabel(): void
    {
        $provider = new CacheableProvider('no-cache-provider');
        $tester = $this->createTester([$provider]);

        $tester->execute([]);
        self::assertSame(1, $provider->getExecutionCount());

        $tester->execute(['--no-cache' => true]);

        self::assertSame(2, $provider->getExecutionCount(), '--no-cache must bypass the cache');
        self::assertStringNotContainsString('(cached)', $tester->getDisplay());
    }

    #[Test]
    public function nonCacheableProvidersAreNeverLabelledAsCached(): void
    {
        $provider = new NonCacheableProvider();
        $tester = $this->createTester([$provider]);

        $tester->execute([]);
        $tester->execute([]);

        self::assertSame(2, $provider->getExecutionCount());
        self::assertStringNotContainsString('(cached)', $tester->getDisplay());
    }

    /**
     * @param array<int, MonitoringProvider> $providers
     */
    private function createTester(array $providers): CommandTester
    {
        $command = new MonitoringCommand($providers, $this->executionHandler);

        return new CommandTester($command);
    }
}
