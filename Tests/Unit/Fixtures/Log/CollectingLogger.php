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

namespace mteu\Monitoring\Tests\Unit\Fixtures\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * CollectingLogger.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class CollectingLogger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * @var list<array{level: mixed, message: string, context: array<array-key, mixed>}>
     */
    private array $records = [];

    /**
     * @param mixed $level PSR-3 declares the level untyped
     * @param array<array-key, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string)$message,
            'context' => $context,
        ];
    }

    /**
     * @return list<array{level: mixed, message: string, context: array<array-key, mixed>}>
     */
    public function records(): array
    {
        return $this->records;
    }

    /**
     * @return list<array{level: mixed, message: string, context: array<array-key, mixed>}>
     */
    public function recordsWithLevel(string $level): array
    {
        return array_values(
            array_filter($this->records, static fn(array $record): bool => $record['level'] === $level),
        );
    }
}
