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

namespace mteu\Monitoring\Tests\Unit\Provider\Scheduler;

use mteu\Monitoring\Provider\Scheduler\SchedulerTask;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;

/**
 * SchedulerTaskTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(SchedulerTask::class)]
final class SchedulerTaskTest extends Framework\TestCase
{
    #[Test]
    public function getLabelCombinesUidAndLabel(): void
    {
        self::assertSame('#42 (Import task)', (new SchedulerTask(42, 'Import task'))->getLabel());
    }

    #[Test]
    public function getLabelFallsBackToPlaceholderForEmptyLabel(): void
    {
        self::assertSame('#5 (no description)', (new SchedulerTask(5, ''))->getLabel());
    }
}
