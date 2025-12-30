<?php

declare(strict_types=1);

namespace SplitProps;

/**
 * Heuristic splitter - stack-based quote toggle with trim() normalization.
 * Known limitations: escape handling, quote state management.
 */
class HeuristicSplitter
{
    public static function splitProps(string $input): array
    {
        $quotes = ['"', "'", '`'];
        $opens = ['{', '[', '(', '<'];
        $closes = ['}', ']', ')', '>'];
        $separators = [',', ';', "\n", "\r"];

        $stack = [];
        $position = 0;
        $buffer = '';
        $parts = [];

        while ($position < strlen($input)) {
            $char = $input[$position];

            // Quote handling (heuristic)
            if (in_array($char, $quotes, true)) {
                if ($position === 0 || $input[$position - 1] !== '\\') {
                    if (!empty($stack) && end($stack) === $char) {
                        array_pop($stack);
                    } else {
                        $stack[] = $char;
                    }
                }

                $buffer .= $char;
                $position++;
                continue;
            }

            // Opening brackets
            if (($index = array_search($char, $opens, true)) !== false) {
                $stack[] = $closes[$index];
                $buffer .= $char;
                $position++;
                continue;
            }

            // Closing brackets
            if (!empty($stack) && $char === end($stack)) {
                array_pop($stack);
                $buffer .= $char;
                $position++;
                continue;
            }

            // Separator (only if not nested)
            if (empty($stack) && in_array($char, $separators, true)) {
                $part = trim($buffer);
                if ($part !== '') {
                    $parts[] = $part;
                }
                $buffer = '';
                $position++;
                continue;
            }

            $buffer .= $char;
            $position++;
        }

        // Final token
        $part = trim($buffer);
        if ($part !== '') {
            $parts[] = $part;
        }

        return $parts;
    }
}
