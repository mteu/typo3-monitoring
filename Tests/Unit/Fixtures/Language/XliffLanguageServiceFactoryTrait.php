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

namespace mteu\Monitoring\Tests\Unit\Fixtures\Language;

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * Builds a {@see LanguageServiceFactory} stub whose `sL()` resolves the
 * extension's `locallang.be.xlf` keys to their English `<source>` values.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
trait XliffLanguageServiceFactoryTrait
{
    private const string XLIFF_KEY_PREFIX = 'LLL:EXT:monitoring/Resources/Private/Language/locallang.be.xlf:';

    /**
     * @return LanguageServiceFactory&\PHPUnit\Framework\MockObject\Stub
     */
    private function createEnglishLanguageServiceFactory(): LanguageServiceFactory
    {
        $sources = self::loadEnglishSources();

        $languageService = self::createStub(LanguageService::class);
        $languageService
            ->method('sL')
            ->willReturnCallback(static function (string $key) use ($sources): string {
                if (!str_starts_with($key, self::XLIFF_KEY_PREFIX)) {
                    return $key;
                }

                $id = substr($key, strlen(self::XLIFF_KEY_PREFIX));

                return $sources[$id] ?? $key;
            });

        $factory = self::createStub(LanguageServiceFactory::class);
        $factory->method('create')->willReturn($languageService);
        $factory->method('createFromUserPreferences')->willReturn($languageService);

        return $factory;
    }

    /**
     * @return array<string, string>
     */
    private static function loadEnglishSources(): array
    {
        $path = dirname(__DIR__, 4) . '/Resources/Private/Language/locallang.be.xlf';

        $xml = simplexml_load_file($path);

        if ($xml === false) {
            throw new \RuntimeException(sprintf('Unable to read language file "%s".', $path), 1750000000);
        }

        $sources = [];

        foreach ($xml->file->body->{'trans-unit'} as $unit) {
            $id = (string)$unit['id'];
            $sources[$id] = (string)$unit->source;
        }

        return $sources;
    }
}
