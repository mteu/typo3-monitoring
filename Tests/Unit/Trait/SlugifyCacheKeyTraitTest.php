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

namespace mteu\Monitoring\Tests\Unit\Trait;

use mteu\Monitoring\Tests\Unit\Stub\SlugifyCacheKeyTraitStub;
use PHPUnit\Framework;

/**
 * SlugifyTraitTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversTrait(\mteu\Monitoring\Trait\SlugifyCacheKeyTrait::class)]
final class SlugifyCacheKeyTraitTest extends Framework\TestCase
{
    private SlugifyCacheKeyTraitStub $subject;

    protected function setUp(): void
    {
        $this->subject = new SlugifyCacheKeyTraitStub();
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\DataProvider('providerSlugifyData')]
    public function testSlugifyString(string $input, string $expectedPattern): void
    {
        $result = $this->subject->stubSlugifyString($input);

        if (str_starts_with($expectedPattern, '~')) {
            // If expected is a regex pattern
            self::assertMatchesRegularExpression($expectedPattern, $result);
        } else {
            self::assertEquals($expectedPattern, $result);
        }
    }

    /**
     * @return \Generator<string[]>
     */
    public static function providerSlugifyData(): \Generator
    {
        yield 'basic slug' => ['Foo Bar', 'foo-bar'];
        yield 'trimming and spaces' => ['   Hello     World   ', 'hello-world'];
        yield 'special characters' => ['Hello!@# World!!!', 'hello-world'];
        yield 'multiple dashes' => ['Foo---Bar', 'foo-bar'];
        yield 'empty string' => ['', ''];
        yield 'numbers preserved' => ['ABC 123 Test', 'abc-123-test'];
        yield 'leading and trailing junk' => ['***Hello World!!!', 'hello-world'];
        yield 'emoji or symbols' => ['🔥 Fire Test 🌟', 'fire-test'];

        // Handle iconv case for accented characters
        if (function_exists('iconv')) {
            yield 'accents with iconv' => ['Fôß Bär ümlauts and caractères spéciaux', 'f-b-r-mlauts-and-caract-res-sp-ciaux'];
        } else {
            yield 'accents without iconv' => ['Fôß Bär ümlauts and caractères spéciaux', 'f-c3-b4-c3-9f-b-c3-a4r-c3-bcmlauts-and-caract-c3-a8res-sp-c3-a9ciaux'];
        }

        if (function_exists('iconv')) {
            // Only check for valid slug structure since transliteration may vary
            yield 'unicode iconv' => ['Παράδειγμα δοκιμής', '~^[a-z0-9\-]*$~'];
        } else {
            yield 'unicode no iconv' => ['Παράδειγμα δοκιμής', ''];
        }
    }
}
