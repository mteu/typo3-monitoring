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

use mteu\Monitoring\Configuration\Authorizer\AdminUserAuthorizerConfiguration;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * AdminUserAuthorizer.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class AdminUserAuthorizer implements Authorizer
{
    private AdminUserAuthorizerConfiguration $adminUserConfiguration;

    public function __construct(
        private Context $context,
        private MonitoringConfiguration $monitoringConfiguration,
    ) {
        $this->adminUserConfiguration = $this->monitoringConfiguration->adminUserAuthorizerConfiguration;
    }

    public function isActive(): bool
    {
        return $this->adminUserConfiguration->isEnabled();
    }

    /**
     * @throws AspectNotFoundException
     */
    public function isAuthorized(ServerRequestInterface $request): bool
    {
        return
            $this->context->getPropertyFromAspect('backend.user', 'isAdmin') &&
            $this->context->getPropertyFromAspect('backend.user', 'isLoggedIn');
    }

    public static function getPriority(): int
    {
        $extConf = GeneralUtility::makeInstance(AdminUserAuthorizerConfiguration::class);

        return $extConf->getPriority();
    }
}
