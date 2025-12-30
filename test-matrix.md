# SplitProps Test Matrix

## Summary

**Surprising finding:** The heuristic implementation handles most cases correctly - contrary to the initial ChatGPT analysis. Both implementations produce identical output for 21 out of 22 test cases.

| Result | Count |
|--------|-------|
| Both match | 21 |
| Differ | 1 |

---

## Cases Where Both Match ✅

| # | Input | Output |
|---|-------|--------|
| 1 | `a,b,c` | `["a", "b", "c"]` |
| 2 | `a;b;c` | `["a", "b", "c"]` |
| 3 | `a\nb\nc` | `["a", "b", "c"]` |
| 4 | `a,b;c\nd` | `["a", "b", "c", "d"]` |
| 5 | `a,{b,c},d` | `["a", "{b,c}", "d"]` |
| 6 | `a,(b,c),d` | `["a", "(b,c)", "d"]` |
| 7 | `a,[b,c],d` | `["a", "[b,c]", "d"]` |
| 8 | `a,<b,c>,d` | `["a", "<b,c>", "d"]` |
| 9 | `a , b , c` | `["a", "b", "c"]` |
| 10 | `a,"b,c",d` | `["a", "\"b,c\"", "d"]` |
| 11 | `a,'b;c',d` | `["a", "'b;c'", "d"]` |
| 12 | `` a,`hello, world`,b `` | `` ["a", "`hello, world`", "b"] `` |
| 13 | `fn({x: "a,b", y: 2}),z` | `["fn({x: \"a,b\", y: 2})", "z"]` |
| 14 | `a,{"k":"v,1","arr":[1,2,3]},b` | `["a", "{\"k\":\"v,1\",\"arr\":[1,2,3]}", "b"]` |
| 15 | `a,"unterminated,b,c` | `["a", "\"unterminated,b,c"]` |
| 16 | `a,\r\nb,c` | `["a", "b", "c"]` |
| 17 | `a,,b;;;c` | `["a", "b", "c"]` |
| 18 | `` (empty) | `[]` |
| 19 | `   ` (spaces) | `[]` |
| 20 | `,;,;` | `[]` |
| 21 | `a,{b,[c,(d,e),f],g},h` | `["a", "{b,[c,(d,e),f],g}", "h"]` |

---

## Cases Where They Differ ❌

| # | Input | Lexer ✅ | Heuristic ❌ | Reason |
|---|-------|----------|--------------|--------|
| 1 | `a,"b'c",d` | `["a", "\"b'c\"", "d"]` | `["a", "\"b'c\",d"]` | Heuristic pushes ALL quote types to stack. Single quote inside double quote breaks matching. |

---

## Why The Heuristic Works Better Than Expected

The ChatGPT analysis predicted many failures that didn't occur. Here's why:

### Quote Handling
The heuristic uses a **toggle mechanism** with the stack:
```php
$char === self::last($stack)
    ? array_pop($stack)   // Same quote = close
    : array_push($stack, $char);  // Different = open new
```

This correctly handles:
- `"b,c"` → pushes `"`, protects comma, pops `"`
- `'b;c'` → pushes `'`, protects semicolon, pops `'`

### Escape Handling
The heuristic checks `$input[$position - 1] !== '\\'` before toggling quotes. This prevents escaped quotes from closing strings:
- `"b\"c,d"` → `\"` doesn't toggle because prev char is `\`

### Where It Fails
Mixed quote types like `"b'c"`:
1. Sees `"` → pushes `"`
2. Sees `'` → stack top is `"`, not `'`, so pushes `'`
3. Sees `"` → stack top is `'`, not `"`, so pushes another `"`
4. Never properly closes, consumes rest of input

---

## Key Differences Between Implementations

| Feature | Heuristic | Lexer |
|---------|-----------|-------|
| Quote state | Stack toggle | Dedicated string-parsing loop |
| Escape handling | Check previous char | Consume backslash + next char |
| Mixed quotes | ❌ Breaks | ✅ Works |
| Bracket matching | Index-based parallel arrays | Explicit open→close map |
| CRLF | `\r` as separator | Explicit `\r\n` handling |

---

## Running Tests

```bash
# With Docker
docker run --rm -v "$(pwd):/app" -w /app composer:latest install
docker run --rm -v "$(pwd):/app" -w /app php:8.3-cli vendor/bin/phpunit --testdox

# With local PHP
composer install
composer test
```
