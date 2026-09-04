import io, re, subprocess, os, urllib.parse

COOKIES = '.freebuff/tmp/wpcookies.txt'
UPLOAD_URL = 'https://neginhafari.ir/wp-admin/plugin-install.php?tab=upload'
ACTION_URL = 'https://neginhafari.ir/wp-admin/update.php?action=upload-plugin'
ZIP = os.path.abspath('build/wpforge-1.0.0.zip')

def sh(cmd):
    r = subprocess.run(cmd, shell=True, capture_output=True, text=True, encoding='utf-8', errors='replace')
    return r.stdout, r.stderr, r.returncode

def save(name, content):
    with io.open(f'.freebuff/tmp/{name}', 'w', encoding='utf-8', errors='replace') as f:
        f.write(content or '')

# 1. Get upload page + nonce
page, _, _ = sh(f'curl -s -b {COOKIES} -c {COOKIES} --max-time 20 "{UPLOAD_URL}"')
save('upload_page3.html', page)
m = re.search(r'name="_wpnonce" value="([a-f0-9]+)"', page or '')
if not m:
    print("FAIL: no nonce on upload page")
    raise SystemExit(1)
nonce = m.group(1)
print(f"Upload page nonce: {nonce}")

# 2. Upload the zip
cmd = (
    f'curl -s -b {COOKIES} -c {COOKIES} --max-time 60 '
    f'-F "_wpnonce={nonce}" '
    f'-F "_wp_http_referer=/wp-admin/plugin-install.php?tab=upload" '
    f'-F "pluginzip=@{ZIP};type=application/zip" '
    f'-F "install-plugin-submit=Install Now" '
    f'-o .freebuff/tmp/upload_result3.html -w "%{{http_code}}" '
    f'"{ACTION_URL}"'
)
out, _, _ = sh(cmd)
print(f"Upload POST: HTTP {out.strip()}")
with io.open('.freebuff/tmp/upload_result3.html', 'r', encoding='utf-8', errors='replace') as f:
    result = f.read()

# 3. Check for overwrite link (update-from-upload-overwrite)
m = re.search(r'href="(update\.php\?action=upload-plugin&amp;package=\d+&amp;overwrite=update-plugin&amp;_wpnonce=[a-f0-9]+)"', result)
if m:
    link = m.group(1).replace('&amp;', '&')
    print(f"Overwrite link found: {link}")
    url = 'https://neginhafari.ir/wp-admin/' + link
    out2, _, _ = sh(f'curl -s -b {COOKIES} -c {COOKIES} --max-time 90 -L "{url}" -o .freebuff/tmp/overwrite2.html -w "%{{http_code}}"')
    print(f"Overwrite follow: HTTP {out2.strip()}")
    with io.open('.freebuff/tmp/overwrite2.html', 'r', encoding='utf-8', errors='replace') as f:
        final = f.read()
    if 'Plugin updated successfully' in final or 'Plugin installed successfully' in final:
        print("SUCCESS: plugin overwritten with fixed files")
    elif 'activate-plugin' in final:
        print("SUCCESS: overwritten, activation link present")
    else:
        msgs = [re.sub(r'<[^>]+>', '', x).strip() for x in re.findall(r'<p>(.*?)</p>', final, re.S)]
        print("Result page messages:", [m for m in msgs if m][:5])
else:
    print("No overwrite link found. Checking result...")
    if 'activate-plugin' in result:
        print("Plugin already updated (no overwrite needed)")
    else:
        msgs = [re.sub(r'<[^>]+>', '', x).strip() for x in re.findall(r'<p>(.*?)</p>', result, re.S)]
        print("Messages:", [m for m in msgs if m][:5])
        print(result[:600])