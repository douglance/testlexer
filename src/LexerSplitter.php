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

        // Stack for nested structures
        $nestingStack = [];

        // Opening → closing bracket map
        $brackets = [
            '{' => '}',
            '[' => ']',
            '(' => ')',
            '<' => '>',
        ];
        $closingBrackets = array_flip($brackets);

        while ($i < $length) {
            $char = $input[$i];

            // Handle string literals
            if ($char === '"' || $char === "'" || $char === '`') {
                $quote = $char;
                $current .= $char;
                $i++;

                while ($i < $length) {
                    $char = $input[$i];
                    $current .= $char;

                    // Escape sequence
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

            // Opening bracket
            if (isset($brackets[$char])) {
                $nestingStack[] = $brackets[$char];
                $current .= $char;
                $i++;
                continue;
            }

            // Closing bracket
            if (isset($closingBrackets[$char])) {
                if (!empty($nestingStack) && end($nestingStack) === $char) {
                    array_pop($nestingStack);
                }
                $current .= $char;
                $i++;
                continue;
            }

            // Separator (only at top level)
            if (empty($nestingStack) && ($char === ',' || $char === ';' || $char === "\n")) {
                $trimmed = trim($current);
                if ($trimmed !== '') {
                    $result[] = $trimmed;
                }
                $current = '';
                $i++;

                // Skip whitespace and CRLF
                while ($i < $length && ($input[$i] === ' ' || $input[$i] === "\t" || $input[$i] === "\r" || $input[$i] === "\n")) {
                    $i++;
                }

                continue;
            }

            // Windows CRLF handling
            if ($char === "\r") {
                $trimmed = trim($current);
                if ($trimmed !== '') {
                    $result[] = $trimmed;
                }
                $current = '';
                $i++;

                if ($i < $length && $input[$i] === "\n") {
                    $i++;
                }

                while ($i < $length && ($input[$i] === ' ' || $input[$i] === "\t" || $input[$i] === "\r" || $input[$i] === "\n")) {
                    $i++;
                }

                continue;
            }

            $current .= $char;
            $i++;
        }

        // Final token
        $trimmed = trim($current);
        if ($trimmed !== '') {
            $result[] = $trimmed;
        }

        return $result;
    }
}
