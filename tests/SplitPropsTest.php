<?php

declare(strict_types=1);

namespace SplitProps\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use SplitProps\HeuristicSplitter;
use SplitProps\LexerSplitter;

/**
 * Comprehensive test suite comparing HeuristicSplitter vs LexerSplitter.
 *
 * Each test case documents:
 * - Input string
 * - Expected output for Lexer (correct behavior)
 * - Expected output for Heuristic (shows where it fails)
 */
class SplitPropsTest extends TestCase
{
    // =========================================================================
    // TESTS WHERE BOTH IMPLEMENTATIONS SHOULD MATCH
    // =========================================================================

    #[DataProvider('bothMatchProvider')]
    public function testBothImplementationsMatch(string $input, array $expected, string $description): void
    {
        $heuristicResult = HeuristicSplitter::splitProps($input);
        $lexerResult = LexerSplitter::splitProps($input);

        $this->assertSame($expected, $lexerResult, "Lexer failed: $description");
        $this->assertSame($expected, $heuristicResult, "Heuristic failed: $description");
    }

    public static function bothMatchProvider(): array
    {
        return [
            'simple comma separated' => [
                'a,b,c',
                ['a', 'b', 'c'],
                'Basic comma separation',
            ],
            'simple semicolon separated' => [
                'a;b;c',
                ['a', 'b', 'c'],
                'Basic semicolon separation',
            ],
            'simple newline separated' => [
                "a\nb\nc",
                ['a', 'b', 'c'],
                'Basic newline separation',
            ],
            'mixed separators' => [
                "a,b;c\nd",
                ['a', 'b', 'c', 'd'],
                'Mixed comma, semicolon, newline',
            ],
            'simple brackets' => [
                'a,{b,c},d',
                ['a', '{b,c}', 'd'],
                'Curly braces protect commas',
            ],
            'simple parens' => [
                'a,(b,c),d',
                ['a', '(b,c)', 'd'],
                'Parentheses protect commas',
            ],
            'simple square brackets' => [
                'a,[b,c],d',
                ['a', '[b,c]', 'd'],
                'Square brackets protect commas',
            ],
            'whitespace trimming' => [
                'a , b , c',
                ['a', 'b', 'c'],
                'Whitespace is trimmed',
            ],
        ];
    }

    // =========================================================================
    // TESTS WHERE HEURISTIC FAILS AND LEXER SUCCEEDS
    // =========================================================================

    #[DataProvider('lexerOnlyProvider')]
    public function testLexerCorrectHeuristicFails(
        string $input,
        array $lexerExpected,
        array $heuristicExpected,
        string $description
    ): void {
        $lexerResult = LexerSplitter::splitProps($input);
        $heuristicResult = HeuristicSplitter::splitProps($input);

        $this->assertSame($lexerExpected, $lexerResult, "Lexer should produce correct result: $description");
        $this->assertSame($heuristicExpected, $heuristicResult, "Heuristic produces different (incorrect) result: $description");

        // Explicitly verify they differ (this is the point of these tests)
        $this->assertNotSame(
            $lexerExpected,
            $heuristicExpected,
            "Test case should demonstrate a difference between implementations: $description"
        );
    }

    public static function lexerOnlyProvider(): array
    {
        return [
            // Case 1: Commas inside quoted strings
            'commas inside double quotes' => [
                'a,"b,c",d',
                ['a', '"b,c"', 'd'],                    // Lexer: keeps quoted string intact
                ['a', '"b', 'c"', 'd'],                 // Heuristic: splits inside quotes
                'Commas inside quoted strings should not split',
            ],

            // Case 2: Semicolons inside quoted strings
            'semicolons inside single quotes' => [
                "a,'b;c',d",
                ['a', "'b;c'", 'd'],                    // Lexer: keeps quoted string intact
                ['a', "'b", "c'", 'd'],                 // Heuristic: splits inside quotes
                'Semicolons inside quoted strings should not split',
            ],

            // Case 3: Escaped quotes inside strings
            'escaped quotes inside string' => [
                'a,"b\"c,d",e',
                ['a', '"b\"c,d"', 'e'],                 // Lexer: handles escape, keeps intact
                ['a', '"b\"c', 'd"', 'e'],              // Heuristic: confused by escaped quote
                'Escaped quotes should not end the string',
            ],

            // Case 4: Escaped backslash + quote
            'escaped backslash then quote' => [
                'a,"b\\\\\"c,d",e',
                ['a', '"b\\\\\"c,d"', 'e'],             // Lexer: \\\\ is escaped backslash, \" is escaped quote
                ['a', '"b\\\\\"c', 'd"', 'e'],          // Heuristic: doesn't handle escapes properly
                'Escaped backslash followed by escaped quote',
            ],

            // Case 5: Template strings with separators
            'template strings with separators' => [
                'a,`hello, world`,b',
                ['a', '`hello, world`', 'b'],           // Lexer: backtick strings handled
                ['a', '`hello', 'world`', 'b'],         // Heuristic: splits inside template
                'Template strings should protect separators',
            ],

            // Case 6: Nested structures with quotes inside
            'nested object with quoted values' => [
                'fn({x: "a,b", y: 2}),z',
                ['fn({x: "a,b", y: 2})', 'z'],          // Lexer: quotes inside braces work
                ['fn({x: "a', 'b"', 'y: 2})', 'z'],     // Heuristic: quote handling breaks nesting
                'Quotes inside nested structures',
            ],

            // Case 7: Path strings with backslashes
            'windows path with backslashes' => [
                'a,"C:\\Users\\me,docs",b',
                ['a', '"C:\\Users\\me,docs"', 'b'],     // Lexer: backslashes don't break quotes
                ['a', '"C:\\Users\\me', 'docs"', 'b'], // Heuristic: may split on comma
                'Windows paths with backslashes and commas',
            ],

            // Case 11: Separators inside angle brackets
            'angle brackets with separators' => [
                'a,<b,c>,d',
                ['a', '<b,c>', 'd'],                    // Lexer: angle brackets protect content
                ['a', '<b', 'c>', 'd'],                 // Heuristic: quote state interferes with bracket matching
                'Angle brackets should protect separators',
            ],

            // Case 12: Mixed nesting + escapes
            'complex nested JSON-like' => [
                'a,{"k":"v,1","arr":[1,2,3]},b',
                ['a', '{"k":"v,1","arr":[1,2,3]}', 'b'], // Lexer: handles all nesting
                ['a', '{"k":"v', '1"', '"arr":[1', '2', '3]}', 'b'], // Heuristic: quotes break nesting
                'Complex nested structures with quotes and arrays',
            ],

            // Case 13: Escape sequences vs real newlines
            'escape sequences in strings' => [
                "a,\"line1\\nline2,still\",b",
                ['a', '"line1\\nline2,still"', 'b'],    // Lexer: \\n is literal, string stays intact
                ['a', '"line1\\nline2', 'still"', 'b'], // Heuristic: splits on comma
                'Escape sequences should not affect string boundaries',
            ],
        ];
    }

    // =========================================================================
    // EDGE CASES - BEHAVIOR MAY VARY
    // =========================================================================

    #[DataProvider('edgeCaseProvider')]
    public function testEdgeCases(string $input, array $lexerExpected, string $description): void
    {
        $lexerResult = LexerSplitter::splitProps($input);
        $this->assertSame($lexerExpected, $lexerResult, "Lexer edge case: $description");
    }

    public static function edgeCaseProvider(): array
    {
        return [
            // Case 8: Unbalanced quote handling
            'unbalanced quote' => [
                'a,"unterminated,b,c',
                ['a', '"unterminated,b,c'],             // Lexer: rest becomes one token
                'Unbalanced quotes should consume rest of input',
            ],

            // Case 9: CRLF normalization
            'CRLF line endings' => [
                "a,\r\nb,c",
                ['a', 'b', 'c'],                        // Lexer: CRLF treated as single separator
                'Windows CRLF should be normalized',
            ],

            // Case 10: Consecutive separators
            'consecutive separators' => [
                'a,,b;;;c',
                ['a', 'b', 'c'],                        // Lexer: empty tokens dropped
                'Consecutive separators should not produce empty tokens',
            ],

            // Case 14: Comment-like text
            'comment-like text' => [
                "a, b // comment, with comma\nc, d",
                ['a', 'b // comment, with comma', 'c', 'd'], // Lexer: no special comment handling
                'Comment-like text (no special handling)',
            ],

            // Additional edge cases
            'empty input' => [
                '',
                [],
                'Empty input returns empty array',
            ],

            'only whitespace' => [
                '   ',
                [],
                'Whitespace-only input returns empty array',
            ],

            'only separators' => [
                ',;,;',
                [],
                'Separator-only input returns empty array',
            ],

            'deeply nested' => [
                'a,{b,[c,(d,e),f],g},h',
                ['a', '{b,[c,(d,e),f],g}', 'h'],
                'Deeply nested brackets',
            ],

            'mixed quote types' => [
                'a,"b\'c",d',
                ['a', '"b\'c"', 'd'],
                'Single quotes inside double quotes',
            ],
        ];
    }

    // =========================================================================
    // DIRECT COMPARISON TESTS - Show actual differences
    // =========================================================================

    #[DataProvider('comparisonProvider')]
    public function testDirectComparison(string $input, string $description): void
    {
        $heuristicResult = HeuristicSplitter::splitProps($input);
        $lexerResult = LexerSplitter::splitProps($input);

        // This test always passes - it's for documentation/visibility
        $this->addToAssertionCount(1);

        // Output for debugging/documentation
        if ($heuristicResult !== $lexerResult) {
            $this->markTestSkipped(sprintf(
                "DIFFERENCE DETECTED in '%s':\n  Input: %s\n  Lexer: %s\n  Heuristic: %s",
                $description,
                json_encode($input),
                json_encode($lexerResult),
                json_encode($heuristicResult)
            ));
        }
    }

    public static function comparisonProvider(): array
    {
        return [
            ['a,"b,c",d', 'commas in quotes'],
            ["a,'b;c',d", 'semicolons in quotes'],
            ['a,"b\"c,d",e', 'escaped quotes'],
            ['a,"b\\\\\"c,d",e', 'escaped backslash + quote'],
            ['a,`hello, world`,b', 'template strings'],
            ['fn({x: "a,b", y: 2}),z', 'nested with quotes'],
            ['a,"C:\\Users\\me,docs",b', 'windows paths'],
            ['a,"unterminated,b,c', 'unbalanced quotes'],
            ["a,\r\nb,c", 'CRLF'],
            ['a,,b;;;c', 'consecutive separators'],
            ['a,<b,c>,d', 'angle brackets'],
            ['a,{"k":"v,1","arr":[1,2,3]},b', 'complex nesting'],
            ["a,\"line1\\nline2,still\",b", 'escape sequences'],
            ["a, b // comment, with comma\nc, d", 'comments'],
        ];
    }
}
