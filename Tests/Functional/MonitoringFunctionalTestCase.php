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

namespace mteu\Monitoring\Tests\Functional;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * MonitoringFunctionalTestCase.
 *
 * Shared base for all functional tests: loads the extension under test and
 * configures both extension caches with a database backend so cached entries
 * actually persist across operations within a test.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
abstract class MonitoringFunctionalTestCase extends FunctionalTestCase
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
                    'typo3_monitoring_notification' => [
                        'frontend' => 'TYPO3\\CMS\\Core\\Cache\\Frontend\\VariableFrontend',
                        'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend',
                        'options' => [],
                        'groups' => [],
                    ],
                ],
            ],
        ],
    ];
}
