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

namespace mteu\Monitoring\Configuration\Provider;

use mteu\TypedExtConf\Attribute\ExtConfProperty;
use mteu\TypedExtConf\Attribute\ExtensionConfig;

/**
 * MiddlewareStatusProviderConfiguration.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[ExtensionConfig(extensionKey: 'monitoring')]
final readonly class MiddlewareStatusProviderConfiguration implements ProviderConfiguration
{
    public function __construct(
        #[ExtConfProperty(path: 'provider.mteu\\Monitoring\\Provider\\MiddlewareStatusProvider.enabled')]
        private bool $enabled = true,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
