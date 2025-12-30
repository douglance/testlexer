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
 * - Expected output for Heuristic (may differ)
 */
class SplitPropsTest extends TestCase
{
    // =========================================================================
    // TESTS WHERE BOTH IMPLEMENTATIONS MATCH
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
            // Basic separators
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

            // Basic brackets
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
            'simple angle brackets' => [
                'a,<b,c>,d',
                ['a', '<b,c>', 'd'],
                'Angle brackets protect commas',
            ],

            // Whitespace handling
            'whitespace trimming' => [
                'a , b , c',
                ['a', 'b', 'c'],
                'Whitespace is trimmed',
            ],

            // Quoted strings - BOTH handle these correctly!
            'commas inside double quotes' => [
                'a,"b,c",d',
                ['a', '"b,c"', 'd'],
                'Commas inside quoted strings should not split',
            ],
            'semicolons inside single quotes' => [
                "a,'b;c',d",
                ['a', "'b;c'", 'd'],
                'Semicolons inside quoted strings should not split',
            ],
            'template strings with separators' => [
                'a,`hello, world`,b',
                ['a', '`hello, world`', 'b'],
                'Template strings should protect separators',
            ],
            'nested object with quoted values' => [
                'fn({x: "a,b", y: 2}),z',
                ['fn({x: "a,b", y: 2})', 'z'],
                'Quotes inside nested structures',
            ],
            'complex nested JSON-like' => [
                'a,{"k":"v,1","arr":[1,2,3]},b',
                ['a', '{"k":"v,1","arr":[1,2,3]}', 'b'],
                'Complex nested structures with quotes and arrays',
            ],

            // Edge cases both handle
            'unbalanced quote' => [
                'a,"unterminated,b,c',
                ['a', '"unterminated,b,c'],
                'Unbalanced quotes should consume rest of input',
            ],
            'CRLF line endings' => [
                "a,\r\nb,c",
                ['a', 'b', 'c'],
                'Windows CRLF should be normalized',
            ],
            'consecutive separators' => [
                'a,,b;;;c',
                ['a', 'b', 'c'],
                'Consecutive separators should not produce empty tokens',
            ],
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
        ];
    }

    // =========================================================================
    // TESTS WHERE IMPLEMENTATIONS DIFFER
    // =========================================================================

    #[DataProvider('differenceProvider')]
    public function testImplementationDifferences(
        string $input,
        array $lexerExpected,
        array $heuristicExpected,
        string $description
    ): void {
        $lexerResult = LexerSplitter::splitProps($input);
        $heuristicResult = HeuristicSplitter::splitProps($input);

        $this->assertSame($lexerExpected, $lexerResult, "Lexer: $description");
        $this->assertSame($heuristicExpected, $heuristicResult, "Heuristic: $description");
    }

    public static function differenceProvider(): array
    {
        return [
            // Escape handling - Lexer consumes escapes, Heuristic doesn't
            'escaped quotes inside string' => [
                'a,"b\"c,d",e',
                ['a', '"b\"c,d"', 'e'],                 // Lexer: handles escape correctly
                ['a', '"b\"c,d"', 'e'],                 // Heuristic: also works (checks previous char)
                'Escaped quotes - both handle via different mechanisms',
            ],

            'escaped backslash then quote' => [
                'a,"b\\\\\"c,d",e',
                ['a', '"b\\\\\"c,d"', 'e'],             // Lexer: consumes \\ then \"
                ['a', '"b\\\\\"c,d"', 'e'],             // Heuristic: checks prev char
                'Escaped backslash + quote',
            ],

            'windows path with backslashes' => [
                'a,"C:\\Users\\me,docs",b',
                ['a', '"C:\\Users\\me,docs"', 'b'],     // Lexer
                ['a', '"C:\\Users\\me,docs"', 'b'],     // Heuristic
                'Windows paths with backslashes and commas',
            ],

            'escape sequences in strings' => [
                "a,\"line1\\nline2,still\",b",
                ['a', '"line1\\nline2,still"', 'b'],    // Lexer
                ['a', '"line1\\nline2,still"', 'b'],    // Heuristic
                'Escape sequences in strings',
            ],

            // Comment-like text - neither has special comment handling
            // But they differ due to how commas are processed
            'comment-like text with comma' => [
                "a, b // comment, with comma\nc, d",
                ['a', 'b // comment', 'with comma', 'c', 'd'], // Lexer splits on comma
                ['a', 'b // comment', 'with comma', 'c', 'd'], // Heuristic same
                'Comment-like text (no special handling, splits on comma)',
            ],

            // Mixed quote types - Heuristic pushes ALL quotes to stack
            // so single quote inside double quote breaks matching
            'mixed quote types DIFFERS' => [
                'a,"b\'c",d',
                ['a', '"b\'c"', 'd'],           // Lexer: correctly handles nested quotes
                ['a', '"b\'c",d'],              // Heuristic: single quote breaks quote matching
                'Single quotes inside double quotes - heuristic fails',
            ],
        ];
    }

    // =========================================================================
    // INDIVIDUAL BEHAVIOR TESTS
    // =========================================================================

    public function testLexerEscapeConsumption(): void
    {
        // Lexer explicitly consumes the character after backslash
        $input = 'a,"test\\,value",b';
        $result = LexerSplitter::splitProps($input);

        // The \, is consumed as escape sequence, comma doesn't split
        $this->assertSame(['a', '"test\\,value"', 'b'], $result);
    }

    public function testHeuristicPreviousCharCheck(): void
    {
        // Heuristic checks if previous char is backslash
        $input = 'a,"test\\"end",b';
        $result = HeuristicSplitter::splitProps($input);

        // \" is not treated as closing quote because prev char is \
        $this->assertSame(['a', '"test\\"end"', 'b'], $result);
    }

    public function testBothHandleNestedBracketsWithQuotes(): void
    {
        $input = 'callback({name: "test, value", items: [1, 2, 3]}), next';

        $lexerResult = LexerSplitter::splitProps($input);
        $heuristicResult = HeuristicSplitter::splitProps($input);

        $expected = ['callback({name: "test, value", items: [1, 2, 3]})', 'next'];

        $this->assertSame($expected, $lexerResult, 'Lexer should handle nested brackets with quotes');
        $this->assertSame($expected, $heuristicResult, 'Heuristic should handle nested brackets with quotes');
    }

    // =========================================================================
    // STRESS TESTS
    // =========================================================================

    public function testDeeplyNestedStructure(): void
    {
        $input = 'a,{b:{c:{d:{e:"f,g"}}}},h';

        $lexerResult = LexerSplitter::splitProps($input);
        $heuristicResult = HeuristicSplitter::splitProps($input);

        $expected = ['a', '{b:{c:{d:{e:"f,g"}}}}', 'h'];

        $this->assertSame($expected, $lexerResult);
        $this->assertSame($expected, $heuristicResult);
    }

    public function testMultipleQuotedStrings(): void
    {
        $input = '"a,b","c,d","e,f"';

        $lexerResult = LexerSplitter::splitProps($input);
        $heuristicResult = HeuristicSplitter::splitProps($input);

        $expected = ['"a,b"', '"c,d"', '"e,f"'];

        $this->assertSame($expected, $lexerResult);
        $this->assertSame($expected, $heuristicResult);
    }

    public function testMixedBracketsAndQuotes(): void
    {
        $input = '[{a:"1,2"},(b:"3,4"),<c:"5,6">]';

        $lexerResult = LexerSplitter::splitProps($input);
        $heuristicResult = HeuristicSplitter::splitProps($input);

        // Entire thing is one token (wrapped in [])
        $expected = ['[{a:"1,2"},(b:"3,4"),<c:"5,6">]'];

        $this->assertSame($expected, $lexerResult);
        $this->assertSame($expected, $heuristicResult);
    }
}
