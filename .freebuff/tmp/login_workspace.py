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

# Workspace-relative paths - unambiguous for both curl and Python
COOKIES = '.freebuff/tmp/wpcookies.txt'
LOGIN_PAGE = '.freebuff/tmp/login_page.html'
LOGIN_POST = '.freebuff/tmp/login_post.html'

if os.path.exists(COOKIES):
    os.remove(COOKIES)

def sh(cmd):
    r = subprocess.run(cmd, shell=True, capture_output=True, text=True, encoding='utf-8', errors='replace')
    return r.stdout, r.stderr, r.returncode

# Step 1: GET login page, save cookies
page, _, _ = sh(f'curl -s -c {COOKIES} --max-time 20 "https://neginhafari.ir/wp-login.php"')
with io.open(LOGIN_PAGE, 'w', encoding='utf-8') as f:
    f.write(page or '')
print(f"GET login: {len(page or '')} bytes")

# Verify test cookie in jar
jar = ''
if os.path.exists(COOKIES):
    with io.open(COOKIES, 'r', encoding='utf-8', errors='replace') as f:
        jar = f.read()
print(f"Jar has test cookie: {'wordpress_test_cookie' in jar}")

# Step 2: POST credentials
data = urllib.parse.urlencode({
    'log': USER,
    'pwd': PASS,
    'wp-submit': 'Log In',
    'redirect_to': 'https://neginhafari.ir/wp-admin/',
    'testcookie': '1',
})
out, _, _ = sh(
    f'curl -s -b {COOKIES} -c {COOKIES} --max-time 30 '
    f'--data "{data}" -o {LOGIN_POST} -w "%{{http_code}}" '
    f'"https://neginhafari.ir/wp-login.php"'
)
print(f"POST login: HTTP {out.strip()}")

resp = ''
if os.path.exists(LOGIN_POST):
    with io.open(LOGIN_POST, 'r', encoding='utf-8', errors='replace') as f:
        resp = f.read()
print(f"Response: {len(resp)} bytes")

if 'wp-admin' in resp and 'Dashboard' in resp:
    print("RESULT: LOGGED IN")
elif 'login_error' in resp:
    m = re.search(r'<div id="login_error"[^>]*>(.*?)</div>', resp, re.S)
    txt = re.sub(r'<[^>]+>', ' ', m.group(1)).strip() if m else 'unknown'
    print(f"RESULT: LOGIN FAILED - {txt[:200]}")
else:
    print(f"RESULT: unclear - first 200 chars: {resp[:200]}")