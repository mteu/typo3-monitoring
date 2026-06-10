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

namespace mteu\Monitoring\Status;

/**
 * ServiceType.
 *
 * The three pluggable "service" kinds the extension exposes. The backing value
 * doubles as the section key used in the monitoring extension configuration
 * (e.g. `provider.<FQCN>.enabled`), so it drives the enablement lookup in
 * {@see ServiceStatusRegistry}.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
enum ServiceType: string
{
    case Provider = 'provider';
    case Authorizer = 'authorizer';
    case Reporter = 'reporter';
}
