# SplitProps Test Matrix

## Legend
- ✅ = Correct behavior
- ❌ = Incorrect behavior (differs from expected)

---

## Cases Where Both Match (✅ Both Correct)

| # | Input | Lexer Output | Heuristic Output |
|---|-------|--------------|------------------|
| 1 | `a,b,c` | `["a", "b", "c"]` | `["a", "b", "c"]` |
| 2 | `a;b;c` | `["a", "b", "c"]` | `["a", "b", "c"]` |
| 3 | `a\nb\nc` | `["a", "b", "c"]` | `["a", "b", "c"]` |
| 4 | `a,b;c\nd` | `["a", "b", "c", "d"]` | `["a", "b", "c", "d"]` |
| 5 | `a,{b,c},d` | `["a", "{b,c}", "d"]` | `["a", "{b,c}", "d"]` |
| 6 | `a,(b,c),d` | `["a", "(b,c)", "d"]` | `["a", "(b,c)", "d"]` |
| 7 | `a,[b,c],d` | `["a", "[b,c]", "d"]` | `["a", "[b,c]", "d"]` |
| 8 | `a , b , c` | `["a", "b", "c"]` | `["a", "b", "c"]` |

---

## Cases Where Heuristic Fails (✅ Lexer, ❌ Heuristic)

| # | Input | Description | Lexer Output ✅ | Heuristic Output ❌ |
|---|-------|-------------|-----------------|---------------------|
| 1 | `a,"b,c",d` | Commas inside double quotes | `["a", "\"b,c\"", "d"]` | `["a", "\"b", "c\"", "d"]` |
| 2 | `a,'b;c',d` | Semicolons inside single quotes | `["a", "'b;c'", "d"]` | `["a", "'b", "c'", "d"]` |
| 3 | `a,"b\"c,d",e` | Escaped quotes inside string | `["a", "\"b\\\"c,d\"", "e"]` | `["a", "\"b\\\"c", "d\"", "e"]` |
| 4 | `a,"b\\\"c,d",e` | Escaped backslash + quote | `["a", "\"b\\\\\\\"c,d\"", "e"]` | `["a", "\"b\\\\\\\"c", "d\"", "e"]` |
| 5 | `` a,`hello, world`,b `` | Template strings with separators | ``["a", "`hello, world`", "b"]`` | ``["a", "`hello", "world`", "b"]`` |
| 6 | `fn({x: "a,b", y: 2}),z` | Nested structures with quotes | `["fn({x: \"a,b\", y: 2})", "z"]` | `["fn({x: \"a", "b\"", "y: 2})", "z"]` |
| 7 | `a,"C:\Users\me,docs",b` | Windows path with backslashes | `["a", "\"C:\\Users\\me,docs\"", "b"]` | `["a", "\"C:\\Users\\me", "docs\"", "b"]` |
| 8 | `a,<b,c>,d` | Separators inside angle brackets | `["a", "<b,c>", "d"]` | `["a", "<b", "c>", "d"]` |
| 9 | `a,{"k":"v,1","arr":[1,2,3]},b` | Mixed nesting + escapes | `["a", "{\"k\":\"v,1\",\"arr\":[1,2,3]}", "b"]` | `["a", "{\"k\":\"v", "1\"", "\"arr\":[1", "2", "3]}", "b"]` |
| 10 | `a,"line1\nline2,still",b` | Escape sequences in strings | `["a", "\"line1\\nline2,still\"", "b"]` | `["a", "\"line1\\nline2", "still\"", "b"]` |

---

## Edge Cases (Lexer Behavior)

| # | Input | Description | Lexer Output |
|---|-------|-------------|--------------|
| 1 | `a,"unterminated,b,c` | Unbalanced quote | `["a", "\"unterminated,b,c"]` |
| 2 | `a,\r\nb,c` | CRLF line endings | `["a", "b", "c"]` |
| 3 | `a,,b;;;c` | Consecutive separators | `["a", "b", "c"]` |
| 4 | `a, b // comment, with comma\nc, d` | Comment-like text | `["a", "b // comment, with comma", "c", "d"]` |
| 5 | `` (empty) | Empty input | `[]` |
| 6 | `   ` (spaces) | Only whitespace | `[]` |
| 7 | `,;,;` | Only separators | `[]` |
| 8 | `a,{b,[c,(d,e),f],g},h` | Deeply nested | `["a", "{b,[c,(d,e),f],g}", "h"]` |
| 9 | `a,"b'c",d` | Mixed quote types | `["a", "\"b'c\"", "d"]` |

---

## Detailed Breakdown: Why Heuristic Fails

### Case 1: `a,"b,c",d`
```
Lexer:     ["a", "\"b,c\"", "d"]     ← Correct: comma inside quotes preserved
Heuristic: ["a", "\"b", "c\"", "d"]  ← Wrong: splits on comma inside quotes
```
**Root cause**: Heuristic's quote toggle doesn't create proper "in-string" state that blocks separator detection.

### Case 3: `a,"b\"c,d",e`
```
Lexer:     ["a", "\"b\\\"c,d\"", "e"]  ← Correct: escaped quote doesn't end string
Heuristic: ["a", "\"b\\\"c", "d\"", "e"] ← Wrong: confused by escaped quote
```
**Root cause**: Heuristic checks `$input[$position - 1] !== '\\'` but doesn't consume the escape properly.

### Case 6: `fn({x: "a,b", y: 2}),z`
```
Lexer:     ["fn({x: \"a,b\", y: 2})", "z"]
Heuristic: ["fn({x: \"a", "b\"", "y: 2})", "z"]
```
**Root cause**: When heuristic enters quote mode, it pushes quote onto stack. This interferes with bracket matching - the closing `}` doesn't match the quote on top of stack.

### Case 8: `a,<b,c>,d`
```
Lexer:     ["a", "<b,c>", "d"]     ← Correct: angle brackets protect content
Heuristic: ["a", "<b", "c>", "d"]  ← Wrong: splits inside angle brackets
```
**Root cause**: Same quote/bracket stack interference. If any quote state is active, bracket matching breaks.

---

## Summary Table

| Category | Count | Lexer | Heuristic |
|----------|-------|-------|-----------|
| Both correct | 8 | ✅ | ✅ |
| Lexer correct, Heuristic wrong | 10 | ✅ | ❌ |
| Edge cases (lexer behavior) | 9 | ✅ | varies |
| **Total** | **27** | | |
