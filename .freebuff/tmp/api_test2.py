import io, re, subprocess, os, urllib.parse, json

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
COOKIES = '.freebuff/tmp/wpcookies.txt'
NONCE = '36fdd37038'

def sh(cmd):
    r = subprocess.run(cmd, shell=True, capture_output=True, text=True, encoding='utf-8', errors='replace')
    return r.stdout, r.stderr, r.returncode

def api(method, path, data=None, auth='cookie'):
    url = BASE + path
    cmd = f'curl -s --max-time 25 -X {method} '
    if auth == 'cookie':
        cmd += f'-b {COOKIES} -c {COOKIES} -H "X-WP-Nonce: {NONCE}" '
    elif auth == 'basic':
        cmd += f'-u "{env["WP_ADMIN_USER"]}:{env["WP_ADMIN_PASSWORD"]}" '
    if data is not None:
        # Write JSON to a temp file to avoid shell quoting issues
        with io.open('.freebuff/tmp/req_body.json', 'w', encoding='utf-8') as f:
            json.dump(data, f)
        cmd += '-H "Content-Type: application/json" --data-binary @.freebuff/tmp/req_body.json '
    cmd += f'"{url}"'
    out, _, _ = sh(cmd)
    try:
        return json.loads(out) if out else {}
    except Exception:
        return {'raw': out[:300]}

results = []
def run_test(name, method, path, expect_success=True, data=None, auth='cookie'):
    resp = api(method, path, data, auth)
    ok = resp.get('success') is expect_success
    status = 'PASS' if ok else 'FAIL'
    results.append((status, name, resp))
    detail = ''
    if 'data' in resp and isinstance(resp['data'], dict):
        keys = list(resp['data'].keys())[:5]
        detail = f" keys={keys}"
    elif 'data' in resp and isinstance(resp['data'], list):
        detail = f" list[{len(resp['data'])}]"
    elif 'raw' in resp:
        detail = f" raw={resp['raw'][:80]}"
    elif 'code' in resp:
        detail = f" code={resp.get('code')} status={resp.get('data', {}).get('status') if isinstance(resp.get('data'), dict) else ''}"
    print(f"[{status}] {name} -> {detail}")

# === 1. System ===
run_test('GET /status', 'GET', '/status')
run_test('GET /capabilities', 'GET', '/capabilities')
run_test('GET /environment', 'GET', '/environment')
run_test('GET /health', 'GET', '/health')

# === 2. Site ===
run_test('GET /site', 'GET', '/site')
run_test('GET /site/structure', 'GET', '/site/structure')
run_test('GET /site/routes', 'GET', '/site/routes')

# === 3. Content ===
run_test('GET /posts', 'GET', '/posts')
run_test('GET /posts?per_page=2', 'GET', '/posts?per_page=2')
run_test('GET /pages', 'GET', '/pages')

# === 4. Media ===
run_test('GET /media', 'GET', '/media')

# === 5. Taxonomies (correct path: /taxonomies/{tax}/terms) ===
run_test('GET /taxonomies/category/terms', 'GET', '/taxonomies/category/terms')

# === 6. Users ===
run_test('GET /users', 'GET', '/users')

# === 7. Menus ===
run_test('GET /menus', 'GET', '/menus')
run_test('GET /menus/locations', 'GET', '/menus/locations')

# === 8. Themes ===
run_test('GET /themes', 'GET', '/themes')

# === 9. Plugins ===
run_test('GET /plugins', 'GET', '/plugins')

# === 10. Elementor ===
run_test('GET /elementor/status', 'GET', '/elementor/status')
run_test('GET /elementor/documents', 'GET', '/elementor/documents')
run_test('GET /elementor/templates', 'GET', '/elementor/templates')

# === 11. Filesystem ===
run_test('GET /files/list', 'GET', '/files/list?path=wp-content')
run_test('GET /files/read', 'GET', '/files/read?path=wp-content%2Findex.php')

# === 12. Database ===
run_test('GET /database/status', 'GET', '/database/status')
run_test('GET /database/tables', 'GET', '/database/tables')
run_test('POST /database/query (SELECT)', 'POST', '/database/query', data={'sql': 'SELECT COUNT(*) AS total FROM wp_posts'})

# === 13. Diagnostics ===
run_test('GET /diagnostics', 'GET', '/diagnostics')
run_test('GET /diagnostics/quick', 'GET', '/diagnostics/quick')

# === 14. Logs ===
run_test('GET /logs', 'GET', '/logs')

# === 15. Backup ===
run_test('GET /backup', 'GET', '/backup')

# === 16. Security: unauthenticated should fail ===
resp = api('GET', '/capabilities', auth='none')
ok = resp.get('success') is not True
results.append(('PASS' if ok else 'FAIL', 'UNAUTH /capabilities (401 expected)', resp))
print(f"[{'PASS' if ok else 'FAIL'}] UNAUTH /capabilities (401 expected) -> code={resp.get('code')} status={resp.get('data', {}).get('status') if isinstance(resp.get('data'), dict) else ''}")

# === 17. Write test: admin can create (then delete) a draft ===
resp = api('POST', '/posts', data={'title': 'WPForge API test draft', 'status': 'draft'})
pid = resp.get('data', {}).get('id') if isinstance(resp.get('data'), dict) else None
ok = resp.get('success') is True and pid is not None
results.append(('PASS' if ok else 'FAIL', 'POST /posts (admin creates draft)', resp))
print(f"[{'PASS' if ok else 'FAIL'}] POST /posts (admin creates draft) -> id={pid}")
if pid:
    resp2 = api('DELETE', f'/posts/{pid}')
    ok2 = resp2.get('success') is True
    results.append(('PASS' if ok2 else 'FAIL', 'DELETE /posts/{id} (cleanup)', resp2))
    print(f"[{'PASS' if ok2 else 'FAIL'}] DELETE /posts/{pid} (cleanup) -> success={resp2.get('success')}")

# === Summary ===
print()
passed = sum(1 for s, _, _ in results if s == 'PASS')
failed = sum(1 for s, _, _ in results if s == 'FAIL')
print(f"=== SUMMARY: {passed} passed, {failed} failed, {len(results)} total ===")
for s, name, resp in results:
    if s == 'FAIL':
        print(f"  FAILED: {name} -> {str(resp)[:220]}")