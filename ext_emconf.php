<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project.
 * (c) 2025 12bis3
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 * The TYPO3 project - inspiring people to share!
 *
 * @copyright 2025 12bis3
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'Sitemap Crawler',
    'description' => 'Crawls sitemaps and triggers search index',
    'category' => 'services',
    'author' => 'Patricia Ottmar',
    'author_email' => 'p.ottmar@12bis3.de',
    'author_company' => '12bis3',
    'state' => 'beta',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [],
        'conflicts' => [],
        'suggests' => [],
    ],
];
