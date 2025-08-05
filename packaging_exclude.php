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

return [
    'directories' => [
        '.build',
        '.git',
        '.github',
        'tailor-version-upload',
        'Tests',
    ],
    'files' => [
        'CODEOWNERS',
        'CODE_OF_CONDUCT.md',
        'CONTRIBUTING.md',
        'DS_Store',
        'SECURITY.md',
        'composer.lock',
        'editorconfig',
        'gitattributes',
        'gitignore',
        'packaging_exclude.php',
        'php-cs-fixer.php',
        'phpunit.functional.xml',
        'phpunit.result.cache',
        'phpunit.unit.xml',
        'renovate.json',
        'version-bumper.yaml',
        // 'crowdin.yaml',
    ],
];
