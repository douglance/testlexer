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

            if (in_array($char, $quotes)) {
                if ($input[$position - 1] !== '\\') {
                    $char === self::last($stack)
                        ? array_pop($stack)
                        : array_push($stack, $char);
                }

                $buffer .= $char;
                $position++;
                continue;
            }

            if (($index = array_search($char, $opens)) !== false) {
                array_push($stack, $closes[$index]);
            }

            if ($char === self::last($stack)) {
                array_pop($stack);
            }

            if (empty($stack) && in_array($char, $separators)) {
                if ($part = trim($buffer)) {
                    $parts[] = $part;
                }
                $buffer = '';
            } else {
                $buffer .= $char;
            }

            $position++;
        }

        if ($part = trim($buffer)) {
            $parts[] = $part;
        }

        return $parts;
    }

    private static function last(array $array): mixed
    {
        if (empty($array)) {
            return null;
        }
        return end($array);
    }
}
