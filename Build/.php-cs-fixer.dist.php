<?php

declare(strict_types=1);

$header = <<<'EOF'
    This file is part of the package netresearch/universal-messenger.

    For the full copyright and license information, please read the
    LICENSE file that was distributed with this source code.
    EOF;

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/..')
    ->exclude(['.Build', '.build', 'config', 'node_modules', 'var'])
    ->notPath('ext_emconf.php');

$config = new PhpCsFixer\Config();
$config
    ->setRiskyAllowed(true)
    ->setRules([
        // Base rulesets
        '@Symfony'         => true,
        '@PER-CS3x0'       => true,

        // Strict typing
        'declare_strict_types' => true,

        // Spacing
        'concat_space' => ['spacing' => 'one'],

        // Docblocks
        'phpdoc_to_comment'          => false,
        'no_superfluous_phpdoc_tags' => false,
        'phpdoc_separation'          => [
            'groups' => [['author', 'license', 'link']],
        ],

        // Aliases
        'no_alias_functions' => true,

        // Alignment
        'binary_operator_spaces' => [
            'operators' => [
                '='  => 'align_single_space_minimal',
                '=>' => 'align_single_space_minimal',
            ],
        ],

        // No Yoda
        'yoda_style' => [
            'equal'                => false,
            'identical'            => false,
            'less_and_greater'     => false,
            'always_move_variable' => false,
        ],

        // Imports
        'global_namespace_import' => [
            'import_classes'   => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'no_unused_imports' => true,
        'ordered_imports'   => ['sort_algorithm' => 'alpha'],

        // Function declarations
        'function_declaration' => [
            'closure_function_spacing' => 'one',
            'closure_fn_spacing'       => 'one',
        ],

        // Modern syntax
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters'],
        ],

        // Whitespace
        'whitespace_after_comma_in_array' => ['ensure_single_space' => true],

        // Style preferences
        'single_line_throw' => false,
        'self_accessor'     => false,

        // Header
        'header_comment' => [
            'header'       => $header,
            'comment_type' => 'comment',
            'location'     => 'after_open',
            'separate'     => 'both',
        ],
    ])
    ->setFinder($finder);

if (method_exists($config, 'setUnsupportedPhpVersionAllowed')) {
    $config->setUnsupportedPhpVersionAllowed(true);
}

return $config;
