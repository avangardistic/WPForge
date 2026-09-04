import io, re, subprocess, sys, os

COOKIES = '/tmp/wpcookies.txt'
EDITOR_URL = 'https://neginhafari.ir/wp-admin/plugin-editor.php'

def sh(cmd):
    r = subprocess.run(cmd, shell=True, capture_output=True, text=True)
    return r.stdout, r.stderr, r.returncode

def get_editor_page(file_esc):
    url = f"{EDITOR_URL}?plugin=wpforge%2Fwpforge.php&file={file_esc}"
    out, _, _ = sh(f"curl -s -b {COOKIES} -c {COOKIES} --max-time 20 \"{url}\"")
    return out

def extract_nonce(html):
    m = re.search(r'<input type="hidden" id="nonce" name="nonce" value="([a-f0-9]+)"', html)
    if m:
        return m.group(1)
    m = re.search(r'name="nonce" value="([a-f0-9]+)"', html)
    return m.group(1) if m else None

def upload_file(rel_path, local_path):
    file_esc = rel_path.replace('/', '%2F')
    page = get_editor_page(file_esc)
    nonce = extract_nonce(page)
    if not nonce:
        print(f"FAIL: no nonce for {rel_path}")
        return False

    # Write local content to a temp file for --data-urlencode newcontent@
    tmp = f".freebuff/tmp/upload_newcontent.php"
    with io.open(local_path, 'r', encoding='utf-8') as f:
        content = f.read()
    with io.open(tmp, 'w', encoding='utf-8', newline='\n') as f:
        f.write(content)

    cmd = (
        f'curl -s -b {COOKIES} -c {COOKIES} --max-time 30 '
        f'--data-urlencode "action=update" '
        f'--data-urlencode "file={rel_path}" '
        f'--data-urlencode "plugin=wpforge/wpforge.php" '
        f'--data-urlencode "nonce={nonce}" '
        f'--data-urlencode "newcontent@{tmp}" '
        f'--data-urlencode "submit=Update File" '
        f'"{EDITOR_URL}" -o .freebuff/tmp/post_result.html -w "%{{http_code}}"'
    )
    out, _, code = sh(cmd)
    result = out.strip()
    print(f"POST {rel_path}: HTTP {result}")
    return result == '302' or result == '200'

files = [
    ('routes/database.php', 'wordpress/wpforge/routes/database.php'),
    ('routes/themes.php', 'wordpress/wpforge/routes/themes.php'),
    ('routes/users.php', 'wordpress/wpforge/routes/users.php'),
]

for rel, local in files:
    ok = upload_file(rel, local)
    if not ok:
        print(f"  -> upload may have failed for {rel}")
print("Done.")

# Verify: fetch each and check for parse errors
print()
print("=== Verification (server-side parse check) ===")
for rel, _ in files:
    out, _, _ = sh(f'curl -s --max-time 12 "https://neginhafari.ir/wp-content/plugins/wpforge/{rel}"')
    if 'Parse error' in out:
        m = re.search(r'<b>Parse error</b>:\s*([^<]+)', out)
        print(f"  {rel}: STILL BROKEN - {m.group(1).strip() if m else 'parse error'}")
    else:
        print(f"  {rel}: OK (parses cleanly, {len(out)} bytes output)")