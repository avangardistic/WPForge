import io, os, re

root = 'wordpress/wpforge'
issues = []

for dirpath, _, files in os.walk(root):
    for f in files:
        if not f.endswith('.php'):
            continue
        path = os.path.join(dirpath, f)
        with io.open(path, 'r', encoding='utf-8', errors='replace') as fh:
            content = fh.read()

        for lineno, line in enumerate(content.split('\n'), 1):
            # Tokenize single-quoted strings on this line
            i = 0
            n = len(line)
            while i < n:
                c = line[i]
                if c == "'":
                    # find closing quote, respecting \\ and \'
                    j = i + 1
                    while j < n:
                        if line[j] == '\\':
                            # escaped char; check if it's \' (bug candidate)
                            if j + 1 < n and line[j+1] == "'":
                                # A single backslash escaping a quote INSIDE the string.
                                # In our codebase we never intend this -> report.
                                issues.append("%s:%d: %s" % (path, lineno, line.strip()[:90]))
                                break
                            j += 2
                            continue
                        if line[j] == "'":
                            break
                        j += 1
                    i = j + 1
                else:
                    i += 1

if issues:
    print("SINGLE-BACKSLASH-BEFORE-QUOTE BUGS (%d):" % len(issues))
    for x in issues:
        print("  ", x)
else:
    print("No single-backslash-before-quote bugs found.")

# Also report lines with class_exists('\\...') to sanity check they have 2 backslashes
print()
print("=== class_exists / namespace backslash strings (should show 2 backslashes) ===")
for dirpath, _, files in os.walk(root):
    for f in files:
        if not f.endswith('.php'):
            continue
        path = os.path.join(dirpath, f)
        with io.open(path, 'r', encoding='utf-8', errors='replace') as fh:
            for lineno, line in enumerate(fh, 1):
                if 'class_exists' in line or 'instanceof' in line:
                    # count backslashes in the first string literal
                    m = re.search(r"'([^']*)'", line)
                    if m and chr(92) in m.group(1):
                        print("%s:%d: %s" % (path, lineno, line.strip()[:100]))