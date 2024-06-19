<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->name('*.php')
    ->exclude('vendor')
    ->exclude('cache')
    ->exclude('node_modules');

$rules = [
    '@PSR12' => true, // Start with PSR-12 rules
    '@Symfony' => true, // Add Symfony rules for more comprehensive formatting
    // Additional custom rules
    // https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/rules/index.rst
    'array_indentation' => true,
    'array_syntax' => ['syntax' => 'short'],
    'binary_operator_spaces' => [
        'default' => 'single_space',
    ],
    'yoda_style' => false,
    'blank_line_after_namespace' => true,
    'blank_line_after_opening_tag' => true,
    'increment_style' => ['style' => 'post'],
    'blank_line_before_statement' => [
        'statements' => ['return'],
    ],
    'cast_spaces' => ['space' => 'none'],
    'class_attributes_separation' => [
        'elements' => ['method' => 'one'],
    ],
    'concat_space' => ['spacing' => 'one'],
    'declare_equal_normalize' => ['space' => 'none'],
    'function_declaration' => ['closure_function_spacing' => 'one'],
    'include' => true,
    'indentation_type' => true,
    'lowercase_cast' => true,
    'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
    'new_with_braces' => true,
    'no_blank_lines_after_class_opening' => true,
    'no_blank_lines_after_phpdoc' => true,
    'no_empty_statement' => true,
    'no_extra_blank_lines' => [
        'tokens' => [
            'extra',
            'throw',
            'use',
            'use_trait',
            'curly_brace_block',
            'parenthesis_brace_block',
            'square_brace_block',
            'switch',
            'case',
            'default',
        ],
    ],
    'no_leading_import_slash' => true,
    'no_leading_namespace_whitespace' => true,
    'no_mixed_echo_print' => ['use' => 'echo'],
    'no_multiline_whitespace_around_double_arrow' => true,
    'no_short_bool_cast' => true,
    'no_singleline_whitespace_before_semicolons' => true,
    'no_spaces_around_offset' => ['positions' => ['inside', 'outside']],
    'no_trailing_comma_in_singleline_array' => true,
    'no_trailing_whitespace' => true,
    'no_trailing_whitespace_in_comment' => true,
    'no_unused_imports' => true,
    'no_whitespace_before_comma_in_array' => true,
    'no_whitespace_in_blank_line' => true,
    'normalize_index_brace' => true,
    'object_operator_without_whitespace' => true,
    'ordered_imports' => ['sort_algorithm' => 'alpha'],
    'semicolon_after_instruction' => true,
    'short_scalar_cast' => true,
    'single_blank_line_at_eof' => true,
    'single_class_element_per_statement' => ['elements' => ['property']],
    'single_import_per_statement' => true,
    'single_line_after_imports' => true,
    'single_quote' => true,
    'space_after_semicolon' => ['remove_in_empty_for_expressions' => true],
    'standardize_not_equals' => true,
    'switch_case_semicolon_to_colon' => true,
    'switch_case_space' => true,
    'trailing_comma_in_multiline' => ['elements' => ['arrays']],
    'trim_array_spaces' => true,
    'unary_operator_spaces' => true,
    'whitespace_after_comma_in_array' => true,
];

return (new PhpCsFixer\Config())
    ->setRules($rules)
    ->setFinder($finder)
    ->setUsingCache(false); // Adjust cache settings as needed
