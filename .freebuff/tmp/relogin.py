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
print(f"Loaded credentials for: {USER} (password {len(PASS)} chars)")

COOKIES = '/tmp/wpcookies.txt'
if os.path.exists(COOKIES):
    os.remove(COOKIES)

def sh(cmd):
    r = subprocess.run(cmd, shell=True, capture_output=True, text=True, encoding='utf-8', errors='replace')
    return r.stdout, r.stderr, r.returncode

# 1. Get login page + nonce + cookies
page, _, _ = sh('curl -s -c /tmp/wpcookies.txt --max-time 20 "https://neginhafari.ir/wp-login.php"')
m = re.search(r'name="_wpnonce" value="([a-f0-9]+)"', page)
nonce = m.group(1) if m else ''
print(f"Login nonce: {nonce[:12]}...")

# 2. POST credentials (URL-encoded properly)
data = urllib.parse.urlencode({
    'log': USER,
    'pwd': PASS,
    'wp-submit': 'Log In',
    'redirect_to': 'https://neginhafari.ir/wp-admin/',
    'testcookie': '1',
})
out, _, _ = sh(
    f'curl -s -b {COOKIES} -c {COOKIES} --max-time 30 '
    f'--data "{data}" -o /dev/null -w "%{{http_code}}" '
    f'"https://neginhafari.ir/wp-login.php"'
)
print(f"Login POST: HTTP {out.strip()}")

# 3. Verify session
admin, _, _ = sh(f'curl -s -b {COOKIES} -c {COOKIES} --max-time 20 "https://neginhafari.ir/wp-admin/"')
if 'Dashboard' in admin:
    print("SESSION OK: logged in as admin")
else:
    print("SESSION FAIL: not logged in")
    raise SystemExit(1)