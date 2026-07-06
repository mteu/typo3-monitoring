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

namespace mteu\Monitoring\Tests\Functional\Backend;

use mteu\Monitoring\Tests\Functional\MonitoringFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Information\Typo3Version;

/**
 * ModuleRegistrationTest.
 *
 * Guards the backend submodule wiring, which intentionally differs per major:
 *
 * - v14: the sub-areas are real submodules of "monitoring". Core builds the
 *   docheader dropdown from ModuleProvider::getModuleForMenu('monitoring')
 *   and renders the overview first via showSubmoduleOverview.
 * - v13: the sub-areas must NOT be children of "monitoring", because core's
 *   BackendModuleValidator rewrites any request to a module with submodules
 *   to its last-used/first submodule — the overview would never open. The
 *   docheader dropdown is built manually in AbstractSubModuleController from
 *   the standalone module identifiers instead.
 *
 * If these assertions fail, either the docheader submodule dropdown is gone
 * (the original v13 regression) or the overview no longer opens first.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class ModuleRegistrationTest extends MonitoringFunctionalTestCase
{
    private const array SUBMODULE_IDENTIFIERS = [
        'monitoring_providers',
        'monitoring_authorizers',
        'monitoring_reporters',
    ];

    private BackendUserAuthentication $backendUser;

    protected function setUp(): void
    {
        parent::setUp();

        // The monitoring modules require access=systemMaintainer. Merge the
        // setting into the base class' SYS configuration without discarding it.
        $configuration = $this->configurationToUseInTestInstance;
        $sys = $configuration['SYS'] ?? [];
        self::assertIsArray($sys);

        $sys['systemMaintainers'] = [1];
        $configuration['SYS'] = $sys;

        $this->configurationToUseInTestInstance = $configuration;

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->backendUser = $this->setUpBackendUser(1);
    }

    #[Test]
    public function monitoringModuleAndSubAreasAreAccessibleForSystemMaintainers(): void
    {
        $moduleProvider = $this->get(ModuleProvider::class);

        foreach (['monitoring', ...self::SUBMODULE_IDENTIFIERS] as $identifier) {
            self::assertNotNull(
                $moduleProvider->getModule($identifier, $this->backendUser),
                sprintf('Module "%s" is not accessible for a system maintainer.', $identifier),
            );
        }
    }

    #[Test]
    public function submodulesFeedTheDocheaderDropdownOnV14(): void
    {
        if ((new Typo3Version())->getMajorVersion() < 14) {
            self::markTestSkipped('Submodule hierarchy applies to TYPO3 v14+ only.');
        }

        $menuModule = $this->get(ModuleProvider::class)->getModuleForMenu('monitoring', $this->backendUser);

        self::assertNotNull(
            $menuModule,
            'ModuleProvider::getModuleForMenu() yields nothing for "monitoring" — the docheader submodule dropdown would be empty.',
        );
        self::assertSame(
            self::SUBMODULE_IDENTIFIERS,
            array_keys($menuModule->getSubModules()),
            'The menu representation of the monitoring module misses submodules — the docheader submodule dropdown would be incomplete.',
        );

        foreach ($menuModule->getSubModules() as $subModule) {
            self::assertSame('monitoring', $subModule->getParentIdentifier());
        }
    }

    #[Test]
    public function monitoringModuleHasNoSubmodulesOnV13(): void
    {
        if ((new Typo3Version())->getMajorVersion() >= 14) {
            self::markTestSkipped('Submodule-free registration applies to TYPO3 v13 only.');
        }

        $module = $this->get(ModuleProvider::class)->getModule('monitoring', $this->backendUser);

        self::assertNotNull($module);
        // A submodule below "monitoring" makes v13's BackendModuleValidator
        // redirect the module request to that submodule — the overview would
        // no longer open when clicking the module menu entry.
        self::assertSame([], $module->getSubModules());
    }
}
