import io, re, subprocess, os, json, time, urllib.parse, urllib.request

# Parse .env
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
BASE = 'https://neginhafari.ir'
REST = BASE + '/wp-json/wpforge/v1'
COOKIES = '.freebuff/tmp/wpcookies.txt'

def sh(cmd):
    r = subprocess.run(cmd, shell=True, capture_output=True, text=True, encoding='utf-8', errors='replace')
    return r.stdout, r.stderr, r.returncode

results = []
def check(name, cond, detail=''):
    results.append((cond, name))
    print(f"[{'PASS' if cond else 'FAIL'}] {name}" + (f" :: {detail}" if detail else ""))

def get(url, cookies=True, headers=None):
    cmd = f'curl -s --max-time 25 '
    if cookies:
        cmd += f'-b {COOKIES} -c {COOKIES} '
    if headers:
        for h in headers:
            cmd += f'-H "{h}" '
    cmd += f'"{url}"'
    out, _, _ = sh(cmd)
    return out

def post(url, data, cookies=True, headers=None, follow=False):
    data_str = urllib.parse.urlencode(data)
    cmd = f'curl -s --max-time 25 '
    if cookies:
        cmd += f'-b {COOKIES} -c {COOKIES} '
    if headers:
        for h in headers:
            cmd += f'-H "{h}" '
    if follow:
        cmd += '-L '
    cmd += f'--data "{data_str}" "{url}"'
    out, _, _ = sh(cmd)
    return out

# ═══ 1. Admin dashboard renders ═══
print("--- 1. Admin pages render ---")
dash = get(BASE + '/wp-admin/admin.php?page=wpforge')
check('Dashboard page loads (HTTP body has title)', 'WPForge &mdash; Dashboard' in dash or 'WPForge — Dashboard' in dash or 'Connect to AI' in dash)
check('Dashboard shows REST API card', 'REST API' in dash)

conn = get(BASE + '/wp-admin/admin.php?page=wpforge-connect')
has_connect = 'Connect to AI' in conn or 'Connect WPForge to AI' in conn or 'Connect</h1>' in conn or 'mcpServers' in conn
check('Connect page loads', has_connect, f"len={len(conn)}")

# ═══ 2. Create WPForge token via admin form ═══
print("--- 2. Token lifecycle ---")
# pull nonces from the connect page
m = re.search(r'name="_wpnonce" value="([a-f0-9]+)"[^>]*>\s*<input type="hidden" name="action" value="wpforge_create_token"', conn)
if not m:
    # nonce may come after action input; search all nonce fields then map by following content
    # simpler: the token form has description field before submit; find nonce near 'wpforge_create_token'
    idx = conn.find('wpforge_create_token')
    seg = conn[max(0, idx-400):idx+200]
    m = re.search(r'name="_wpnonce" value="([a-f0-9]+)"', seg)
token_nonce = m.group(1) if m else None
check('Extracted token form nonce', token_nonce is not None)

if token_nonce:
    resp = post(BASE + '/wp-admin/admin-post.php', {
        'action': 'wpforge_create_token',
        '_wpnonce': token_nonce,
        'description': 'BEHAVE-TEST token',
    }, follow=True)
    # After redirect, the connect page shows the new token once (transient)
    conn2 = get(BASE + '/wp-admin/admin.php?page=wpforge-connect')
    m2 = re.search(r'id="wpforge-api-token">([^<]+)</pre>', conn2)
    check('New token displayed once on connect page', m2 is not None)
    full_token = m2.group(1).strip() if m2 else None
    if full_token:
        print(f"   token={full_token[:20]}...({len(full_token)} chars)")
        # sanity: format id.secret
        check('Token format id.secret', '.' in full_token and full_token.startswith('wf_'))
    else:
        full_token = None

# ═══ 3. Bearer token authenticates REST (the key behavior) ═══
print("--- 3. Bearer auth on REST ---")
if full_token:
    out = get(REST + '/capabilities', cookies=False, headers=[f'Authorization: Bearer {full_token}'])
    try:
        j = json.loads(out)
        check('Bearer token authenticates /capabilities', j.get('success') is True, f"keys={list(j.get('data', {}).keys())[:3]}")
    except Exception:
        check('Bearer token authenticates /capabilities', False, out[:200])

    # Wrong token must fail
    out = get(REST + '/capabilities', cookies=False, headers=['Authorization: Bearer wf_deadbeef000000000000000000000000.deadbeef'])
    try:
        j = json.loads(out)
        check('Bogus token rejected (401)', j.get('success') is not True and (j.get('data', {}).get('status') == 401 or 'invalid' in str(j.get('code', ''))), f"code={j.get('code')}")
    except Exception:
        check('Bogus token rejected (401)', False, out[:200])

    # No auth must fail
    out = get(REST + '/capabilities', cookies=False)
    check('No auth rejected', '"success":true' not in out)

# ═══ 4. Token appears in manage table ═══
print("--- 4. Token management table ---")
conn3 = get(BASE + '/wp-admin/admin.php?page=wpforge-connect')
m3 = re.search(r'id="wpforge-api-token">([^<]+)</pre>', conn3)
check('Token NOT shown again after page reload (one-time)', m3 is None)
check('Manage table lists token', 'BEHAVE-TEST token' in conn3)

# capture token_id for revocation (from table)
m4 = re.search(r'name="token_id" value="([^"]+)"', conn3)
token_id = m4.group(1) if m4 else None
check('Token ID present in table', token_id is not None)

# ═══ 5. Test connection form ═══
print("--- 5. Test connection flow ---")
# Application Password may not exist yet; test with a known-bad password should fail gracefully
idx = conn3.find('wpforge_test_connection')
seg = conn3[max(0, idx-400):idx+200]
m5 = re.search(r'name="_wpnonce" value="([a-f0-9]+)"', seg)
test_nonce = m5.group(1) if m5 else None
check('Extracted test-connection nonce', test_nonce is not None)
if test_nonce:
    resp = post(BASE + '/wp-admin/admin-post.php', {
        'action': 'wpforge_test_connection',
        '_wpnonce': test_nonce,
        'username': USER,
        'password': 'wrong-password-for-test',
    }, follow=True)
    conn4 = get(BASE + '/wp-admin/admin.php?page=wpforge-connect')
    check('Test connection reports failure for bad credentials', 'Connection failed' in conn4 or 'Authentication rejected' in conn4)

# ═══ 6. Revoke token → Bearer stops working ═══
print("--- 6. Revocation ---")
if token_id:
    seg = conn3
    idx = seg.find('wpforge_revoke_token')
    seg2 = seg[max(0, idx-400):idx+200]
    m6 = re.search(r'name="_wpnonce" value="([a-f0-9]+)"', seg2)
    revoke_nonce = m6.group(1) if m6 else None
    check('Extracted revoke nonce', revoke_nonce is not None)
    if revoke_nonce:
        post(BASE + '/wp-admin/admin-post.php', {
            'action': 'wpforge_revoke_token',
            '_wpnonce': revoke_nonce,
            'token_id': token_id,
        }, follow=True)
        conn5 = get(BASE + '/wp-admin/admin.php?page=wpforge-connect')
        check('Token marked revoked in table', 'revoked' in conn5)
        # now try the bearer token again — must be rejected
        if full_token:
            out = get(REST + '/capabilities', cookies=False, headers=[f'Authorization: Bearer {full_token}'])
            check('Revoked token rejected', '"success":true' not in out)

# ═══ 7. Application Password (Option A) ═══
print("--- 7. Application Password flow ---")
idx = conn2 if 'conn2' in dir() else conn
seg = conn
idx2 = seg.find('wpforge_create_app_password')
seg2b = seg[max(0, idx2-400):idx2+200]
m7 = re.search(r'name="_wpnonce" value="([a-f0-9]+)"', seg2b)
app_nonce = m7.group(1) if m7 else None
if app_nonce:
    resp = post(BASE + '/wp-admin/admin-post.php', {
        'action': 'wpforge_create_app_password',
        '_wpnonce': app_nonce,
    }, follow=True)
    conn6 = get(BASE + '/wp-admin/admin.php?page=wpforge-connect')
    m8 = re.search(r'id="wpforge-app-pass">([^<]+)</pre>', conn6)
    if m8:
        app_pass = m8.group(1).strip()
        check('Application password created and shown once', len(app_pass) > 10)
        # Try Basic auth with app password against REST
        out = get(REST + '/capabilities', cookies=False, headers=[f'Authorization: Basic {urllib.parse.quote(USER)}:{urllib.parse.quote(app_pass)}'])
        # curl handles base64: easier to pass -u via curl directly
        cmd = f'curl -s --max-time 25 -u "{USER}:{app_pass}" "{REST}/capabilities"'
        out2, _, _ = sh(cmd)
        try:
            j = json.loads(out2)
            check('Application Password authenticates REST (Basic auth)', j.get('success') is True, f"keys={list(j.get('data', {}).keys())[:3]}")
        except Exception:
            check('Application Password authenticates REST (Basic auth)', False, out2[:200])
    else:
        check('Application password created and shown once', False, 'no app-pass block found')
        # maybe app passwords not available
        check('Application Password authenticates REST (Basic auth)', False, 'creation failed')
else:
    check('Application Password flow', False, 'no create-app-pass nonce found')

# ═══ Summary ═══
print()
passed = sum(1 for c, _ in results if c)
failed = sum(1 for c, _ in results if not c)
print(f"=== SUMMARY: {passed} passed, {failed} failed, {len(results)} total ===")
for c, name in results:
    if not c:
        print(f"  FAILED: {name}")