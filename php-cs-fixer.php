<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__.'/src/Controller',
        __DIR__.'/src/Service',
        __DIR__.'/src/Mapper',
        __DIR__.'/src/Http',
        __DIR__.'/src/Middleware',
        __DIR__.'/src/Dto/Chat',
        __DIR__.'/src/Infrastructure/Search',
        __DIR__.'/src/Infrastructure/Database',
    ])
    ->append([
        __DIR__.'/public/index.php',
        __DIR__.'/bin/migrate.php',
        __DIR__.'/src/routes.php',
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
