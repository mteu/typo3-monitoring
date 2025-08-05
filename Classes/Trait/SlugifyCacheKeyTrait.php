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

namespace mteu\Monitoring\Trait;

/**
 * SlugifyCacheKeyTrait.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
trait SlugifyCacheKeyTrait
{
    /**
     * Converts a given string into a lowercase, URL-friendly slug.
     *
     * If available, uses iconv to transliterate Unicode characters to ASCII.
     * Replaces non-alphanumeric characters with hyphens and trims hyphens from ends.
     */
    public function slugifyString(string $value): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($value)) ?? $value;

        return trim($slug, '-');
    }
}
