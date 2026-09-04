import io

BS = chr(92)  # backslash character

path = 'wordpress/wpforge/wpforge.php'
with io.open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# Fix line 31: $prefix = 'WPForge\';  ->  $prefix = 'WPForge\\';
old31 = "$prefix = 'WPForge" + BS + "';"
new31 = "$prefix = 'WPForge" + BS + BS + "';"

# Fix line 37: str_replace('\', '/', ...) -> str_replace('\\', '/', ...)
old37 = "str_replace('" + BS + "', '/', $relative_class)"
new37 = "str_replace('" + BS + BS + "', '/', $relative_class)"

assert old31 in content, "line 31 pattern NOT found: " + repr(old31)
assert old37 in content, "line 37 pattern NOT found: " + repr(old37)

content = content.replace(old31, new31).replace(old37, new37)

with io.open(path, 'w', encoding='utf-8', newline='') as f:
    f.write(content)

lines = content.split('\n')
print("FIXED. Verifying:")
print("  Line 31:", repr(lines[30]))
print("  Line 37:", repr(lines[36]))

# Sanity: count backslashes
assert lines[30].count(BS) == 2, "line 31 should have 2 backslashes"
assert lines[36].count(BS) == 2, "line 37 should have 2 backslashes"
print("  Both lines now contain 2 backslashes. OK")