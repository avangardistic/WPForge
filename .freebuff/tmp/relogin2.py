import io, re, subprocess, os, urllib.parse

# Parse .env properly
env = {}
with io.open('.env', 'r', encoding='utf-8') as f:
    for line in f:
        line = line.strip()
        if not line or line.startswith('#') or '=' not in line:
            continue
        k, v = line.split('=', 1)
        env[k.strip()] = v.strip()

USER = env['WP_ADMIN_USER']
PASS = env['WP_ADMIN_PASSWORD']

COOKIES = '/tmp/wpcookies.txt'
if os.path.exists(COOKIES):
    os.remove(COOKIES)

def sh(cmd):
    r = subprocess.run(cmd, shell=True, capture_output=True, text=True, encoding='utf-8', errors='replace')
    return r.stdout, r.stderr, r.returncode

# 1. Get login page + nonce + cookies
page, _, _ = sh('curl -s -c /tmp/wpcookies.txt --max-time 20 "https://neginhafari.ir/wp-login.php"')
with io.open('.freebuff/tmp/login_page.html', 'w', encoding='utf-8') as f:
    f.write(page or '')
m = re.search(r'name="_wpnonce" value="([a-f0-9]+)"', page or '')
nonce = m.group(1) if m else ''
print(f"Login page: {len(page or '')} bytes, nonce: {nonce[:8] if nonce else 'NONE'}")

# 2. POST credentials
data = urllib.parse.urlencode({
    'log': USER,
    'pwd': PASS,
    'wp-submit': 'Log In',
    'redirect_to': 'https://neginhafari.ir/wp-admin/',
    'testcookie': '1',
})
if nonce:
    data += '&_wpnonce=' + nonce
out, _, _ = sh(
    f'curl -s -b /tmp/wpcookies.txt -c /tmp/wpcookies.txt --max-time 30 '
    f'--data "{data}" -o .freebuff/tmp/login_post.html -w "%{{http_code}}" '
    f'"https://neginhafari.ir/wp-login.php"'
)
print(f"Login POST: HTTP {out.strip()}")

with io.open('.freebuff/tmp/login_post.html', 'r', encoding='utf-8', errors='replace') as f:
    resp = f.read()
print(f"Response: {len(resp)} bytes")

# Look for error messages
for pat in [r'<div id="login_error">(.*?)</div>', r'<div class="message">(.*?)</div>', r'error']:
    m = re.search(pat, resp, re.S)
    if m:
        txt = re.sub(r'<[^>]+>', ' ', m.group(1) if m.groups() else m.group(0)).strip()
        print(f"  {pat[:30]}: {txt[:300]}")
        break

# Check if we landed on admin or back at login
if 'wp-admin' in resp and 'Dashboard' in resp:
    print("RESULT: logged in (admin page)")
elif 'login_error' in resp:
    print("RESULT: login FAILED - error shown")
else:
    print("RESULT: unclear")