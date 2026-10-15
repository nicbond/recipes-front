<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'migrations'])
    ->notPath('config/reference.php')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'no_unused_imports' => true,
        'single_quote' => true,
        'binary_operator_spaces' => ['default' => 'single_space'],
        'no_extra_blank_lines' => ['tokens' => ['extra']],
    ])
    ->setFinder($finder)
    ;
