# SplitProps Test Matrix

## Summary

| Result | Count |
|--------|-------|
| Both match | 21 |
| Heuristic fails | **10** |
| **Total tests** | **42** |

---

## Cases Where Both Match ✅ (21 cases)

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

## Cases Where Heuristic Fails ❌ (10 cases)

### Mixed Quote Types (6 cases)

The heuristic pushes ALL quote types to the stack. When a different quote type appears inside, it gets pushed too, breaking the matching logic.

| # | Input | Lexer ✅ | Heuristic ❌ |
|---|-------|----------|--------------|
| 1 | `a,"b'c",d` | `["a", "\"b'c\"", "d"]` | `["a", "\"b'c\",d"]` |
| 2 | `a,'b"c',d` | `["a", "'b\"c'", "d"]` | `["a", "'b\"c',d"]` |
| 3 | `a,\`b"c\`,d` | `["a", "\`b\"c\`", "d"]` | `["a", "\`b\"c\`,d"]` |
| 4 | `a,\`b'c\`,d` | `["a", "\`b'c\`", "d"]` | `["a", "\`b'c\`,d"]` |
| 5 | `a,"b\`c",d` | `["a", "\"b\`c\"", "d"]` | `["a", "\"b\`c\",d"]` |
| 6 | `a,'b\`c',d` | `["a", "'b\`c'", "d"]` | `["a", "'b\`c',d"]` |

### Mixed Quotes Inside Brackets (2 cases)

When mixed quotes appear inside brackets, the bracket matching also breaks because the quote stack interferes.

| # | Input | Lexer ✅ | Heuristic ❌ |
|---|-------|----------|--------------|
| 7 | `a,{x:"y'z"},b` | `["a", "{x:\"y'z\"}", "b"]` | `["a", "{x:\"y'z\"},b"]` |
| 8 | `a,["x'y"],b` | `["a", "[\"x'y\"]", "b"]` | `["a", "[\"x'y\"],b"]` |

### Escape Edge Cases (2 cases)

| # | Input | Lexer ✅ | Heuristic ❌ | Reason |
|---|-------|----------|--------------|--------|
| 9 | `\"a,b` | `["\\\"a,b"]` | `["\\\"a", "b"]` | Heuristic checks `position-1` which is invalid at position 0 |
| 10 | `a,"b\\\\",c` | `["a", "\"b\\\\\"", "c"]` | `["a", "\"b\\\\\",c"]` | String ending with escaped backslash confuses heuristic |

---

## Root Cause Analysis

### Why Mixed Quotes Fail

```php
// Heuristic quote handling:
if (in_array($char, $quotes)) {
    $char === self::last($stack)
        ? array_pop($stack)      // Same quote type = close
        : array_push($stack, $char);  // Different = push NEW quote
}
```

For input `"b'c"`:
1. `"` → stack: `["]`
2. `'` → stack top is `"`, not `'` → push → stack: `["`, `']`
3. `"` → stack top is `'`, not `"` → push → stack: `["`, `'`, `"]`
4. Never closes properly → consumes rest of input

### Why Lexer Works

```php
// Lexer uses dedicated string-parsing mode:
if ($char === '"' || $char === "'" || $char === '`') {
    $quote = $char;  // Remember which quote opened
    while ($i < $length) {
        if ($char === $quote) break;  // Only SAME quote closes
        // Other quote types are just characters
    }
}
```

---

## Summary

| Failure Category | Count | Root Cause |
|------------------|-------|------------|
| Mixed quote types | 6 | Stack toggle pushes all quotes |
| Mixed quotes in brackets | 2 | Quote stack breaks bracket matching |
| Escape edge cases | 2 | Position-1 check + trailing backslash |
| **Total failures** | **10** | |

The lexer's ~80 extra lines exist specifically to handle these 10 edge cases correctly.

---

## Running Tests

```bash
# With Docker
docker run --rm -v "$(pwd):/app" -w /app php:8.3-cli vendor/bin/phpunit --testdox

# With local PHP
composer test
```
