import os

BACKSLASH = chr(92)

# Check every PHP file for references to WPForge\Api (lowercase) — must be WPForge\API
issues = []
for root, dirs, files in os.walk('wordpress/wpforge'):
    if '.git' in root:
        continue
    for f in files:
        if not f.endswith('.php'):
            continue
        p = os.path.join(root, f)
        with open(p, encoding='utf-8', errors='replace') as fh:
            for i, line in enumerate(fh, 1):
                if f'WPForge{BACKSLASH}Api{BACKSLASH}' in line:
                    issues.append(f"LOWERCASE: {p}:{i}: {line.strip()[:90]}")

if issues:
    print(f"Found {len(issues)} lowercase 'Api' references:")
    for x in issues:
        print(" ", x)
else:
    print("OK: no lowercase WPForge\\Api references remain")

# Also verify all referenced WPForge namespaces have matching directories
import re
missing = set()
for root, dirs, files in os.walk('wordpress/wpforge'):
    if '.git' in root:
        continue
    for f in files:
        if not f.endswith('.php'):
            continue
        p = os.path.join(root, f)
        with open(p, encoding='utf-8', errors='replace') as fh:
            for line in fh:
                m = re.search(r'WPForge\\([A-Za-z]+)\\([A-Za-z]+)', line)
                if m:
                    ns, cls = m.group(1), m.group(2)
                    expected = f'wordpress/wpforge/src/{ns}/{cls}.php'
                    # only flag when referencing our own src classes (skip vendor-style)
                    if not os.path.exists(expected) and ns in ('API', 'Core', 'Auth', 'Security', 'WordPress', 'Elementor', 'Filesystem', 'Database', 'Backup', 'Diagnostics', 'Logging', 'Media', 'Plugins', 'Themes'):
                        missing.add(f"{ns}\\{cls} -> {expected}")

if missing:
    print(f"\n{len(missing)} references with no matching src file:")
    for x in sorted(missing):
        print(" ", x)
else:
    print("OK: all WPForge class references resolve to existing src files")