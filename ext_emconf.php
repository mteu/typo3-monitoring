<?php

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

/** @noinspection PhpUndefinedVariableInspection */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Monitoring',
    'description' => 'Exposes health state information of selected components in your TYPO3 instance to be integrated in external monitoring',
    'category' => 'be',
    'version' => '0.4.1',
    'state' => 'alpha',
    'author' => 'Martin Adler',
    'author_email' => 'mteu@mailbox.org',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.31-13.4.99',
            'php' => '8.3.0-8.4.99',
            'typed_extconf' => '0.2.0-0.2.99',
        ],
    ],
];
