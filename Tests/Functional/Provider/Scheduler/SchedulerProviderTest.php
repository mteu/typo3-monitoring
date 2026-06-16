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

namespace mteu\Monitoring\Tests\Functional\Provider\Scheduler;

use mteu\Monitoring\Provider\Scheduler\SchedulerProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * SchedulerProviderTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(SchedulerProvider::class)]
final class SchedulerProviderTest extends SchedulerProviderFunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler'];

    #[Test]
    public function isActiveReturnsTrueWhenEnabledAndSchedulerExtensionIsLoaded(): void
    {
        self::assertTrue($this->createSchedulerProvider()->isActive());
    }
}
