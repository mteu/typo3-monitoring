<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "monitoring".
 *
 * Copyright (C) 2025 Martin Adler <mteu@mailbox.org>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
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

    #[\Override]
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
