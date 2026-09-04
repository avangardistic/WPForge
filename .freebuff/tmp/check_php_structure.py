import io, os, re

root = 'wordpress/wpforge'
problems = []

for dirpath, _, files in os.walk(root):
    for f in files:
        if not f.endswith('.php'):
            continue
        path = os.path.join(dirpath, f)
        rel = os.path.relpath(path, root)
        with io.open(path, 'r', encoding='utf-8', errors='replace') as fh:
            content = fh.read()

        # Strip comments (rough) and string literals, then count braces
        # Remove // and # comments
        text = re.sub(r'//[^\n]*', '', content)
        text = re.sub(r'#[^\n]*', '', text)
        # Remove /* */ comments
        text = re.sub(r'/\*.*?\*/', '', text, flags=re.S)
        # Remove single-quoted strings
        text = re.sub(r"'(\\.|[^'\\])*'", "''", text)
        # Remove double-quoted strings
        text = re.sub(r'"(\\.|[^"\\])*"', '""', text)

        # Also detect if file appears truncated: doesn't end with ; or } or )
        stripped_end = content.rstrip()
        if not re.search(r'[;}\])]$', stripped_end):
            # Files ending with a closing PHP tag or just ?> are fine; flag others
            if not stripped_end.endswith('?>') and not stripped_end.endswith("'") and not stripped_end.endswith('"'):
                problems.append(f"{rel}: maybe truncated - ends with: ...{stripped_end[-40:]!r}")

        opens = {'{': 0, '(': 0, '[': 0}
        closes = {'}': 0, ')': 0, ']': 0}
        for ch in text:
            if ch in opens: opens[ch] += 1
            if ch in closes: closes[ch] += 1

        bad = []
        if opens['{'] != closes['}']: bad.append(f"{{}} {opens['{']}!={closes['}']}")
        if opens['('] != closes[')']: bad.append(f"() {opens['(']}!={closes[')']}")
        if opens['['] != closes[']']: bad.append(f"[] {opens['[']}!={closes[']']}")
        if bad:
            problems.append(f"{rel}: UNBALANCED - " + ', '.join(bad))
        else:
            print(f"OK  {rel}")

print()
if problems:
    print(f"=== {len(problems)} PROBLEM FILE(S) ===")
    for p in problems:
        print("  " + p)
else:
    print("All PHP files structurally balanced.")