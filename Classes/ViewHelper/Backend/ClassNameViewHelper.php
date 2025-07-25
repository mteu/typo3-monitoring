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

namespace mteu\Monitoring\ViewHelper\Backend;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * ClassNameViewHelper.
 *
 * ViewHelper to extract the class name from a fully qualified class name (FQCN)
 *
 * Register in Fluid:
 * <html xmlns:monitoring="http://typo3.org/ns/mteu\Monitoring\ViewHelpers\Backend">
 *     or
 * {namespace monitoring=mteu\Monitoring\ViewHelpers\Backend}
 *
 * Tag Usage:
 * <monitoring:className>{yourFQCN}</monitoring:className>
 *
 * Inline Usage:
 * {yourFQCN -> monitoring:className()}
 *
 * Examples:
 * mteu\Monitoring\Authorization\AdminUserAuthorizer => AdminUserAuthorizer
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class ClassNameViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument(
            'fqcn',
            'string',
            'Fully qualified class name to extract the class name from'
        );
    }

    public function render(): string
    {
        $fqcn = $this->arguments['fqcn'] ?? $this->renderChildren();

        if (!is_string($fqcn)) {
            return '';
        }

        $fqcn = trim($fqcn);

        if ($fqcn === '') {
            return '';
        }

        // Remove leading backslash if present
        $fqcn = ltrim($fqcn, '\\');

        // Extract the last segment after the last backslash
        $lastBackslashPosition = strrpos($fqcn, '\\');

        if ($lastBackslashPosition === false) {
            return $fqcn;
        }

        $className = substr($fqcn, $lastBackslashPosition + 1);

        if ($className === '') {
            return $fqcn;
        }

        return $className;
    }
}
