<?php

declare(strict_types=1);

namespace SplitProps;

/**
 * Stateful lexer-style splitter.
 * Features: explicit string literal state, escape consumption, typed bracket matching, CRLF handling.
 */
class LexerSplitter
{
    public static function splitProps(string $input): array
    {
        $result = [];
        $current = '';
        $length = strlen($input);
        $i = 0;

        // Stack to track nested structures
        $nestingStack = [];

        // Mapping of opening to closing brackets
        $brackets = [
            '{' => '}',
            '[' => ']',
            '(' => ')',
            '<' => '>',
        ];

        $closingBrackets = array_flip($brackets);

        while ($i < $length) {
            $char = $input[$i];

            // Handle string literals (single, double, and template)
            if ($char === '"' || $char === "'" || $char === '`') {
                $quote = $char;
                $current .= $char;
                $i++;

                while ($i < $length) {
                    $char = $input[$i];
                    $current .= $char;

                    // Handle escape sequences
                    if ($char === '\\' && $i + 1 < $length) {
                        $i++;
                        $current .= $input[$i];
                        $i++;
                        continue;
                    }

                    // End of string
                    if ($char === $quote) {
                        $i++;
                        break;
                    }

                    $i++;
                }

                continue;
            }

            // Handle opening brackets
            if (isset($brackets[$char])) {
                $nestingStack[] = $brackets[$char];
                $current .= $char;
                $i++;
                continue;
            }

            // Handle closing brackets
            if (isset($closingBrackets[$char])) {
                // Pop from stack if it matches
                if (!empty($nestingStack) && end($nestingStack) === $char) {
                    array_pop($nestingStack);
                }
                $current .= $char;
                $i++;
                continue;
            }

            // Check for separators only when not nested
            if (empty($nestingStack)) {
                // Handle comma, semicolon, and newline separators
                if ($char === ',' || $char === ';' || $char === "\n") {
                    // Skip \n if followed by \r
                    if ($char === "\n" && $i > 0 && $input[$i - 1] === "\r") {
                        // Already handled with \r, skip
                    }

                    $trimmed = trim($current);
                    if ($trimmed !== '') {
                        $result[] = $trimmed;
                    }
                    $current = '';
                    $i++;

                    // Skip any additional whitespace/newlines after separator
                    while ($i < $length && ($input[$i] === ' ' || $input[$i] === "\t" || $input[$i] === "\r" || $input[$i] === "\n")) {
                        $i++;
                    }

                    continue;
                }

                // Handle \r (for Windows line endings)
                if ($char === "\r") {
                    $trimmed = trim($current);
                    if ($trimmed !== '') {
                        $result[] = $trimmed;
                    }
                    $current = '';
                    $i++;

                    // Skip \n if it follows \r
                    if ($i < $length && $input[$i] === "\n") {
                        $i++;
                    }

                    // Skip any additional whitespace/newlines after separator
                    while ($i < $length && ($input[$i] === ' ' || $input[$i] === "\t" || $input[$i] === "\r" || $input[$i] === "\n")) {
                        $i++;
                    }

                    continue;
                }
            }

            // Regular characters
            $current .= $char;
            $i++;
        }

        // Don't forget the last property
        $trimmed = trim($current);
        if ($trimmed !== '') {
            $result[] = $trimmed;
        }

        return $result;
    }
}
