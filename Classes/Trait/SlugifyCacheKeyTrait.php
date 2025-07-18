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

namespace mteu\Monitoring\Trait;

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
        // We're not going to bother with transliteration too much here since this only meant to cache key generation
        if (function_exists('iconv')) {
            $value = iconv('UTF-8', 'ISO-8859-1//IGNORE', $value) ?: $value;
        }

        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($value)) ?? $value;

        return trim($slug, '-');
    }
}
