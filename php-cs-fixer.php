<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__.'/src',
        __DIR__.'/bin',
        __DIR__.'/tests',
    ])
    ->exclude('e2e/runtime')
    ->append([
        __DIR__.'/public/index.php',
    ]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setUsingCache(false)
    ->setRules([
        '@PSR12' => true,
        'control_structure_braces' => true,
        'braces_position' => true,
        'no_unused_imports' => true,
    ])
    ->setFinder($finder);
