<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('vendor')
    ->exclude('var')
    ->ignoreDotFiles(false)
    ->ignoreVCSIgnored(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'php_unit_method_casing' => ['case' => 'snake_case'],
        'yoda_style' => false,
        'method_chaining_indentation' => true,
    ])
    ->setFinder($finder);
