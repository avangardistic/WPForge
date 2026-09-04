import io, re, subprocess, os

COOKIES = '.freebuff/tmp/wpcookies.txt'
UPLOAD_URL = 'https://neginhafari.ir/wp-admin/plugin-install.php?tab=upload'
ACTION_URL = 'https://neginhafari.ir/wp-admin/update.php?action=upload-plugin'
ZIP = os.path.abspath('build/wpforge-1.0.0.zip')

def sh(cmd):
    r = subprocess.run(cmd, shell=True, capture_output=True, text=True, encoding='utf-8', errors='replace')
    return r.stdout, r.stderr, r.returncode

# 1. Get the upload page + nonce
page, _, _ = sh(f'curl -s -b {COOKIES} -c {COOKIES} --max-time 20 "{UPLOAD_URL}"')
with io.open('.freebuff/tmp/upload_page2.html', 'w', encoding='utf-8') as f:
    f.write(page)

m = re.search(r'name="_wpnonce" value="([a-f0-9]+)"', page)
if not m:
    print("FAIL: no _wpnonce on upload page")
    # Maybe session expired - show page excerpt
    if 'login' in page.lower():
        print("Session expired - need to re-login")
    else:
        print(page[:500])
    raise SystemExit(1)
nonce = m.group(1)
print(f"Got nonce: {nonce}")

# Check for overwrite checkbox on the page (should appear if plugin exists)
if 'overwrite' in page.lower():
    print("Overwrite option present in form")
else:
    print("No overwrite option visible - will try with overwrite=1 anyway")

# 2. Upload with overwrite
cmd = (
    f'curl -s -b {COOKIES} -c {COOKIES} --max-time 60 '
    f'-F "_wpnonce={nonce}" '
    f'-F "_wp_http_referer=/wp-admin/plugin-install.php?tab=upload" '
    f'-F "pluginzip=@{ZIP};type=application/zip" '
    f'-F "overwrite=1" '
    f'-F "install-plugin-submit=Install Now" '
    f'-o .freebuff/tmp/upload_result2.html -w "%{{http_code}}" '
    f'"{ACTION_URL}"'
)
out, err, code = sh(cmd)
print(f"Upload POST: HTTP {out.strip()}")

with io.open('.freebuff/tmp/upload_result2.html', 'r', encoding='utf-8', errors='replace') as f:
    result = f.read()

# 3. Parse result
if 'activate-plugin' in result:
    print("SUCCESS: upload accepted, activation link present")
    m2 = re.search(r'action=activate&amp;plugin=([^&"]+)', result)
    if m2:
        print(f"Activation link found for: {m2.group(1)}")
elif 'already exists' in result.lower() or 'existing plugin' in result.lower():
    print("NOTE: plugin already exists message")
elif 'error' in result.lower():
    errs = re.findall(r'<p>(.*?)</p>', result, re.S)
    for e in errs[:5]:
        print(f"  ERR: {e.strip()[:200]}")
else:
    print("UNKNOWN result - checking for key markers")
    for marker in ['wpforge', 'activate', 'error', 'notice']:
        if marker in result.lower():
            print(f"  marker '{marker}' present")
    print(result[:800])