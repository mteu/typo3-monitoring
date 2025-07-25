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

namespace mteu\Monitoring\Configuration\Authorizer;

use mteu\TypedExtConf\Attribute\ExtConfProperty;
use mteu\TypedExtConf\Attribute\ExtensionConfig;

/**
 * AdminUserConfiguration.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[ExtensionConfig(extensionKey: 'monitoring')]
final readonly class AdminUserAuthorizerConfiguration implements AuthorizerConfiguration
{
    public function __construct(
        #[ExtConfProperty(path: 'authorizer.mteu\\Monitoring\\Authorization\\AdminUserAuthorizer.enabled')]
        public bool $enabled = false,
        #[ExtConfProperty(path: 'authorizer.mteu\\Monitoring\\Authorization\\AdminUserAuthorizer.priority')]
        public int $priority = -10,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}
