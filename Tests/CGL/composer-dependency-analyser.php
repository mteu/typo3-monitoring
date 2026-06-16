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

use Composer\Autoload;
use ShipMonk\ComposerDependencyAnalyser;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$rootPath = dirname(__DIR__, 2);

/** @var Autoload\ClassLoader $loader */
$loader = require $rootPath . '/.build/vendor/autoload.php';
$loader->register();

$configuration = new ComposerDependencyAnalyser\Config\Configuration();
$configuration
    ->addPathsToExclude([
        $rootPath . '/Tests/CGL',
    ])
    // typo3/cms-scheduler is an intentional *optional* integration: it's a
    // composer "suggest" (require-dev for CI), and MonitoringReportTask is
    // guarded by class_exists() in ext_localconf.php.
    ->ignoreErrorsOnPackage(
        'typo3/cms-scheduler',
        [ErrorType::DEV_DEPENDENCY_IN_PROD],
    )
;

return $configuration;
