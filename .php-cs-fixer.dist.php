<?php

declare(strict_types=1);

$header = <<<'HEADER'
This file is part of the PMU project.

(c) Antoine Bluchet <soyuka@pm.me>

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
HEADER;

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        'header_comment' => [
            'header' => $header,
            'location' => 'after_open',
        ],
        'ordered_imports' => [
            'imports_order' => [
                'class',
                'function',
                'const',
            ],
            'sort_algorithm' => 'alpha',
        ],
        'no_unused_imports' => true,
        'declare_strict_types' => true,
    ])->setFinder($finder);
