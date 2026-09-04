import io

BS = chr(92)

path = '.freebuff/tmp/editor_content.php'
with io.open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# Fix $prefix = 'WPForge\';  ->  $prefix = 'WPForge\\';
old31 = "$prefix = 'WPForge" + BS + "';"
new31 = "$prefix = 'WPForge" + BS + BS + "';"

# Fix str_replace('\', '/', ...) -> str_replace('\\', '/', ...)
old37 = "str_replace('" + BS + "', '/', $relative_class)"
new37 = "str_replace('" + BS + BS + "', '/', $relative_class)"

assert old31 in content, "L31 pattern NOT found"
assert old37 in content, "L37 pattern NOT found"

content = content.replace(old31, new31).replace(old37, new37)

with io.open(path, 'w', encoding='utf-8', newline='') as f:
    f.write(content)

lines = content.split('\n')
print("L31:", repr(lines[30]))
print("L37:", repr(lines[36]))
assert lines[30].count(BS) == 2
assert lines[36].count(BS) == 2
print("Server content fixed. Ready to POST.")