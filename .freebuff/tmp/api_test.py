import io, re, subprocess, os, urllib.parse, json, sys

# Parse .env
env = {}
with io.open('.env', 'r', encoding='utf-8') as f:
    for line in f:
        line = line.strip()
        if not line or line.startswith('#') or '=' not in line:
            continue
        k, v = line.split('=', 1)
        env[k.strip()] = v.strip()

BASE = 'https://neginhafari.ir/wp-json/wpforge/v1'
USER = env['WP_ADMIN_USER']
PASS = env['WP_ADMIN_PASSWORD']
COOKIES = '.freebuff/tmp/wpcookies.txt'

def sh(cmd):
    r = subprocess.run(cmd, shell=True, capture_output=True, text=True, encoding='utf-8', errors='replace')
    return r.stdout, r.stderr, r.returncode

def api(method, path, data=None, auth='basic'):
    url = BASE + path
    cmd = f'curl -s --max-time 20 -X {method} '
    if auth == 'basic':
        cmd += f'-u "{USER}:{PASS}" '
    elif auth == 'cookie':
        cmd += f'-b {COOKIES} -c {COOKIES} '
    if data is not None:
        cmd += f'-H "Content-Type: application/json" --data "{json.dumps(data)}" '
    cmd += f'"{url}"'
    out, _, _ = sh(cmd)
    try:
        return json.loads(out) if out else {}
    except Exception:
        return {'raw': out[:300]}

results = []
def run_test(name, method, path, expect_success=True, data=None, auth='basic'):
    resp = api(method, path, data, auth)
    ok = resp.get('success') is expect_success
    status = 'PASS' if ok else 'FAIL'
    results.append((status, name, resp))
    detail = ''
    if 'data' in resp and isinstance(resp['data'], dict):
        keys = list(resp['data'].keys())[:4]
        detail = f" keys={keys}"
    elif 'raw' in resp:
        detail = f" raw={resp['raw'][:80]}"
    print(f"[{status}] {name} -> success={resp.get('success')}{detail}")

# === 1. System endpoints ===
run_test('GET /status', 'GET', '/status')
run_test('GET /', 'GET', '/')
run_test('GET /capabilities', 'GET', '/capabilities')
run_test('GET /environment', 'GET', '/environment')
run_test('GET /health', 'GET', '/health')

# === 2. Site endpoints ===
run_test('GET /site', 'GET', '/site')
run_test('GET /site/structure', 'GET', '/site/structure')

# === 3. Content endpoints ===
run_test('GET /posts', 'GET', '/posts')
run_test('GET /pages', 'GET', '/pages')
run_test('GET /posts?per_page=3', 'GET', '/posts?per_page=3')

# === 4. Media ===
run_test('GET /media', 'GET', '/media')

# === 5. Taxonomies ===
run_test('GET /taxonomies', 'GET', '/taxonomies')

# === 6. Users ===
run_test('GET /users', 'GET', '/users')

# === 7. Menus ===
run_test('GET /menus', 'GET', '/menus')

# === 8. Themes ===
run_test('GET /themes', 'GET', '/themes')

# === 9. Plugins ===
run_test('GET /plugins', 'GET', '/plugins')

# === 10. Elementor ===
run_test('GET /elementor/documents', 'GET', '/elementor/documents')
run_test('GET /elementor/templates', 'GET', '/elementor/templates')

# === 11. Filesystem (read-only) ===
run_test('GET /files/list', 'GET', '/files/list?path=wp-content')
run_test('GET /files/read', 'GET', '/files/read?path=wp-content%2Findex.php')

# === 12. Database (read-only) ===
run_test('GET /database/tables', 'GET', '/database/tables')
run_test('GET /database/query', 'GET', '/database/query?query=SELECT%20COUNT(*)%20AS%20total%20FROM%20wp_posts')

# === 13. Cache ===
run_test('GET /cache/status', 'GET', '/cache/status')

# === 14. Diagnostics ===
run_test('GET /diagnostics', 'GET', '/diagnostics')

# === 15. Logs ===
run_test('GET /logs', 'GET', '/logs')

# === 16. Backup list ===
run_test('GET /backup', 'GET', '/backup')

# === 17. Auth tests ===
run_test('UNAUTHENTICATED /status (should fail)', 'GET', '/status', expect_success=False, auth='none')

# === Summary ===
print()
passed = sum(1 for s, _, _ in results if s == 'PASS')
failed = sum(1 for s, _, _ in results if s == 'FAIL')
print(f"=== SUMMARY: {passed} passed, {failed} failed, {len(results)} total ===")
for s, name, resp in results:
    if s == 'FAIL':
        print(f"  FAILED: {name} -> {str(resp)[:200]}")