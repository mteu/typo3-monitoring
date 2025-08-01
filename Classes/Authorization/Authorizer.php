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

namespace mteu\Monitoring\Authorization;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Authorizer
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[AutoconfigureTag(name: 'monitoring.authorizer')]
interface Authorizer
{
    public function isActive(): bool;
    public function isAuthorized(ServerRequestInterface $request): bool;

    /**
     * Static ::getPriority() used in the DI autowiring for sorting.
     */
    public static function getPriority(): int;
}
