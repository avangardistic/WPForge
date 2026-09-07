<?php

namespace WPForge\Admin;

use WPForge\Auth\TokenManager;
use WPForge\Core\Config;

/**
 * WPForge admin dashboard — site overview + "Connect to AI" wizard.
 *
 * Provides a user-facing page in wp-admin where the site owner can:
 *   - see the WPForge / REST API status at a glance,
 *   - generate AI credentials (WordPress Application Password or WPForge token),
 *   - copy ready-made MCP client configuration (Claude Desktop, Cursor, CLI),
 *   - test the connection with generated credentials,
 *   - manage (list / revoke) WPForge API tokens.
 */
class AdminUI
{
    private const MENU_SLUG    = 'wpforge';
    private const CONNECT_SLUG = 'wpforge-connect';
    private const NONCE_ACTION = 'wpforge_admin_action';

    private ?TokenManager $tokenManager = null;
    private array $pageHooks = [];

    public function register(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_post_wpforge_create_token', [$this, 'handleCreateToken']);
        add_action('admin_post_wpforge_revoke_token', [$this, 'handleRevokeToken']);
        add_action('admin_post_wpforge_create_app_password', [$this, 'handleCreateAppPassword']);
        add_action('admin_post_wpforge_test_connection', [$this, 'handleTestConnection']);
    }

    /* ------------------------------------------------------------------ */
    /*  Hooks                                                              */
    /* ------------------------------------------------------------------ */

    public function addMenu(): void
    {
        $hook = add_menu_page(
            'WPForge',
            'WPForge',
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderDashboard'],
            'dashicons-admin-generic',
            80
        );

        add_submenu_page(
            self::MENU_SLUG,
            'WPForge Dashboard',
            'Dashboard',
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderDashboard']
        );

        $connectHook = add_submenu_page(
            self::MENU_SLUG,
            'Connect WPForge to AI',
            'Connect to AI',
            'manage_options',
            self::CONNECT_SLUG,
            [$this, 'renderConnect']
        );

        $this->pageHooks = [
            $hook,
            $connectHook,
            'toplevel_page_wpforge',
            'wpforge_page_wpforge-connect',
        ];
    }

    public function enqueueAssets(string $hook): void
    {
        if (!in_array($hook, $this->pageHooks, true)) {
            return;
        }

        wp_enqueue_style(
            'wpforge-admin',
            WPFORGE_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WPFORGE_VERSION
        );

        wp_enqueue_script(
            'wpforge-admin',
            WPFORGE_PLUGIN_URL . 'assets/js/admin.js',
            [],
            WPFORGE_VERSION,
            true
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Handlers (admin-post)                                              */
    /* ------------------------------------------------------------------ */

    public function handleCreateToken(): void
    {
        $this->guardNonce('create_token');

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- see note above.
        $description = sanitize_text_field(wp_unslash($_POST['description'] ?? ''));
        if ($description === '') {
            $description = 'WPForge AI token';
        }

        try {
            $token = $this->tokens()->createToken(get_current_user_id(), $description);
            // One-time display on the Connect page (never in a URL).
            set_transient('wpforge_new_token_' . get_current_user_id(), $token['full_token'], 300);
            $type = 'created_token';
        } catch (\Throwable $e) {
            $type = 'error';
            set_transient('wpforge_flash_' . get_current_user_id(), esc_html($e->getMessage()), 60);
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::CONNECT_SLUG . '&wpforge_created=' . $type));
        exit;
    }

    public function handleRevokeToken(): void
    {
        $this->guardNonce('revoke_token');

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- see note above.
        $tokenId = sanitize_text_field(wp_unslash($_POST['token_id'] ?? ''));
        if ($tokenId !== '' && $this->tokens()->revokeToken($tokenId)) {
            set_transient('wpforge_flash_' . get_current_user_id(), 'Token revoked.', 60);
        } else {
            set_transient('wpforge_flash_' . get_current_user_id(), 'Could not revoke token.', 60);
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::CONNECT_SLUG));
        exit;
    }

    public function handleCreateAppPassword(): void
    {
        $this->guardNonce('create_app_password');

        if (
            !function_exists('WP_Application_Passwords')
            || !\WP_Application_Passwords::is_available()
        ) {
            set_transient('wpforge_flash_' . get_current_user_id(), 'Application Passwords are not available on this site.', 60);
            wp_safe_redirect(admin_url('admin.php?page=' . self::CONNECT_SLUG));
            exit;
        }

        $userId = get_current_user_id();
        $result = \WP_Application_Passwords::create_new_application_password(
            $userId,
            ['name' => 'WPForge AI (MCP)']
        );

        if (is_wp_error($result)) {
            set_transient('wpforge_flash_' . $userId, implode(' ', $result->get_error_messages()), 60);
            wp_safe_redirect(admin_url('admin.php?page=' . self::CONNECT_SLUG));
            exit;
        }

        [$password] = $result;
        // One-time display — store raw (never shown again).
        set_transient('wpforge_new_apppass_' . $userId, $password, 300);

        wp_safe_redirect(admin_url('admin.php?page=' . self::CONNECT_SLUG . '&wpforge_created=created_app'));
        exit;
    }

    public function handleTestConnection(): void
    {
        $this->guardNonce('test_connection');

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- see note above.
        $username = sanitize_user(wp_unslash($_POST['username'] ?? ''), true);
        // Not sanitised on purpose: this is a password being tested verbatim.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $password = wp_unslash($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            set_transient('wpforge_test_result_' . get_current_user_id(), [
                'status'  => 'fail',
                'message' => 'Enter both username and password.',
            ], 60);
        } else {
            set_transient('wpforge_test_result_' . get_current_user_id(), $this->probeCredentials($username, $password), 60);
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::CONNECT_SLUG . '&wpforge_tested=1'));
        exit;
    }

    /* ------------------------------------------------------------------ */
    /*  Dashboard page                                                     */
    /* ------------------------------------------------------------------ */

    public function renderDashboard(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'wpforge'), 403);
        }

        $version  = defined('WPFORGE_VERSION') ? WPFORGE_VERSION : 'unknown';
        $wpVer    = get_bloginfo('version');
        $phpVer   = PHP_VERSION;
        $restBase = esc_url(rest_url('wpforge/v1'));
        $health   = $this->probeEndpoint('wpforge/v1/health', null);

        $user   = wp_get_current_user();
        $tokens = $this->tokens()->listTokens((int) $user->ID);
        $devMode = (new Config())->isDeveloperMode();
        $appPassCount = $this->countActiveAppPasswords((int) $user->ID);

        $this->renderHeader('Dashboard', 'Site overview &amp; API status');

        echo '<div class="wpforge-cards">';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see note above.
        echo $this->card(
            'Plugin',
            '<span class="wpforge-badge wpforge-badge--green">v' . esc_html($version) . ' active</span>',
            'WordPress ' . esc_html($wpVer) . ' · PHP ' . esc_html($phpVer) . ' · ' . esc_html(php_sapi_name())
        );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see note above.
        echo $this->card(
            'REST API',
            $health['status'] === 'ok'
                ? '<span class="wpforge-badge wpforge-badge--green">Online</span>'
                : '<span class="wpforge-badge wpforge-badge--red">Offline</span>',
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see note above.
            '<code>' . $restBase . '</code><br>' . esc_html($health['message'])
        );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see note above.
        echo $this->card(
            'Developer mode',
            $devMode
                ? '<span class="wpforge-badge wpforge-badge--yellow">Enabled</span>'
                : '<span class="wpforge-badge wpforge-badge--grey">Disabled</span>',
            'Write endpoints (filesystem, database) stay disabled until this is on.'
        );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see note above.
        echo $this->card(
            'AI credentials',
            '<span class="wpforge-badge wpforge-badge--blue">' . (int) (count($tokens) + $appPassCount) . '</span>',
            'Active tokens + application passwords for <strong>' . esc_html($user->user_login) . '</strong>. Manage them on the Connect page.'
        );

        echo '</div>';

        echo '<div class="wpforge-strip">';
        printf(
            '<strong>Next step:</strong> connect an AI assistant to this site
             <a class="button button-primary wpforge-strip-cta" href="%s">Open “Connect to AI”</a>',
            esc_url(admin_url('admin.php?page=' . self::CONNECT_SLUG))
        );
        echo '</div>';

        $this->renderFooter();
    }

    /* ------------------------------------------------------------------ */
    /*  Connect to AI page                                                 */
    /* ------------------------------------------------------------------ */

    public function renderConnect(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'wpforge'), 403);
        }

        $userId   = get_current_user_id();
        $user     = wp_get_current_user();
        $userName = $user->user_login;
        $siteUrl  = get_site_url();

        // One-time secrets created by the forms above.
        $newToken   = get_transient('wpforge_new_token_' . $userId);
        $newAppPass = get_transient('wpforge_new_apppass_' . $userId);
        $flash      = get_transient('wpforge_flash_' . $userId);
        $testResult = get_transient('wpforge_test_result_' . $userId);

        $this->consumeTransient('wpforge_new_token_' . $userId);
        $this->consumeTransient('wpforge_new_apppass_' . $userId);
        $this->consumeTransient('wpforge_flash_' . $userId);
        $this->consumeTransient('wpforge_test_result_' . $userId);

        $tokens        = $this->tokens()->listTokens($userId);
        $appPassCount  = $this->countActiveAppPasswords($userId);

        $this->renderHeader('Connect to AI', 'Give an AI assistant secure access to this site');

        // Flash / notices
        if ($flash) {
            echo '<div class="notice notice-error"><p>' . esc_html($flash) . '</p></div>';
        }
        // phpcs:ignore WordPress.Security.NonceVerification -- see note above.
        if (isset($_GET['wpforge_created'])) {
            // phpcs:ignore WordPress.Security.NonceVerification -- see note above.
            $kind = sanitize_key($_GET['wpforge_created']);
            $msg  = $kind === 'created_app'
                ? 'Application password created. It is displayed once below — copy it now.'
                : ($kind === 'error' ? 'There was a problem creating the credential.' : 'API token created. It is displayed once below — copy it now.');
            $class = $kind === 'error' ? 'notice-error' : 'notice-success';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see note above.
            echo '<div class="notice ' . $class . '"><p>' . esc_html($msg) . '</p></div>';
        }

        // ── Step 1: how it works ────────────────────────────────────────
        echo '<div class="wpforge-panel">';
        echo '<h2>How it works</h2>';
        echo '<p>An AI assistant (Claude, Cursor, or any MCP client) talks to the '
             . '<strong>WPForge MCP server</strong>, which calls this site&rsquo;s REST API. '
             . 'The MCP server authenticates with <strong>your WordPress username + an Application Password</strong> '
             . '&mdash; never your real login password.</p>';
        echo '<ol class="wpforge-steps">'
           . '<li>Create a credential below (recommended: Application Password).</li>'
           . '<li>Copy the configuration snippet for the AI tool you use.</li>'
           . '<li>Click <em>Test connection</em> to confirm everything works.</li>'
           . '</ol>';
        echo '</div>';

        // ── Step 2: create a credential ─────────────────────────────────
        echo '<div class="wpforge-panel">';
        echo '<h2>Step 1 — Create AI credentials</h2>';

        // Application Password box
        echo '<div class="wpforge-box">';
        echo '<h3>Option A — WordPress Application Password <span class="wpforge-badge wpforge-badge--green">Recommended</span></h3>';
        echo '<p>Works out of the box with the MCP server&rsquo;s Basic auth. '
             . 'You can also create/revoke these anytime under '
             . '<strong>Users &rarr; Profile &rarr; Application Passwords</strong>.</p>';
        if ($newAppPass) {
            echo '<p><strong>New application password &mdash; copy it now:</strong></p>';
            echo '<div class="wpforge-code-row"><pre class="wpforge-code" id="wpforge-app-pass">'
                 . esc_html($newAppPass) . '</pre>'
                 . '<button type="button" class="button wpforge-copy" data-target="wpforge-app-pass">Copy</button></div>';
            echo '<p class="wpforge-hint">Use it as the password in the MCP configuration below. '
                 . 'It cannot be shown again.</p>';
        } elseif ($appPassCount > 0) {
            echo '<p class="wpforge-muted">' . (int) $appPassCount . ' active application password(s) exist '
                 . 'for <strong>' . esc_html($userName) . '</strong>. You can reuse one or create a fresh one.</p>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        $this->nonceField('create_app_password');
        echo '<input type="hidden" name="action" value="wpforge_create_app_password">';
        echo '<button type="submit" class="button button-primary">Generate Application Password</button>';
        echo '</form>';
        echo '</div>';

        // WPForge token box
        echo '<div class="wpforge-box">';
        echo '<h3>Option B — WPForge API token</h3>';
        echo '<p>A plugin-native token sent as <code>Authorization: Bearer &lt;token&gt;</code>. '
             . 'Use this with the MCP server or curl directly.</p>';
        if ($newToken) {
            echo '<p><strong>New API token &mdash; copy it now:</strong></p>';
            echo '<div class="wpforge-code-row"><pre class="wpforge-code" id="wpforge-api-token">'
                 . esc_html($newToken) . '</pre>'
                 . '<button type="button" class="button wpforge-copy" data-target="wpforge-api-token">Copy</button></div>';
            echo '<p class="wpforge-hint">Stored hashed on the server; it cannot be shown again.</p>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        $this->nonceField('create_token');
        echo '<input type="hidden" name="action" value="wpforge_create_token">';
        echo '<label for="wpforge-token-desc">Description</label> ';
        echo '<input type="text" id="wpforge-token-desc" name="description" value="' . esc_attr('WPForge AI token') . '"> ';
        echo '<button type="submit" class="button button-primary">Generate API token</button>';
        echo '</form>';
        echo '</div>';

        echo '</div>'; // panel

        // ── Step 3: MCP configuration snippets ─────────────────────────
        $passwordForSnippet = $newAppPass ?: ($newToken ?: '');

        echo '<div class="wpforge-panel">';
        echo '<h2>Step 2 — Copy the MCP configuration</h2>';

        if ($passwordForSnippet === '') {
            echo '<p class="wpforge-muted">Generate a credential above first, then the snippets below will '
                 . 'be pre-filled for you. You can also paste an existing Application Password into the '
                 . 'test form below to pre-fill the snippets.</p>';
        }

        // The codeBlock() helper escapes its arguments with esc_html()/esc_attr();
        // the sniff cannot follow the method call, so it is disabled for this run.
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $this->codeBlock(
            'claude',
            'Claude Desktop — <code>claude_desktop_config.json</code>',
            $this->mcpClaudeSnippet($siteUrl, $userName, $passwordForSnippet)
        );
        echo $this->codeBlock('cursor', 'Cursor — <code>~/.cursor/mcp.json</code>', $this->mcpCursorSnippet($siteUrl, $userName, $passwordForSnippet));
        echo $this->codeBlock('cli', 'Any MCP client (CLI)', $this->mcpCliSnippet($siteUrl, $userName, $passwordForSnippet));
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

        echo '<div class="wpforge-box">';
        echo '<h3>Direct API access (curl)</h3>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see note above.
        echo $this->codeBlock('curl', '', $this->curlSnippet($siteUrl, $userName, $passwordForSnippet));
        echo '</div>';

        echo '</div>'; // panel

        // ── Step 4: test connection ────────────────────────────────────
        echo '<div class="wpforge-panel">';
        echo '<h2>Step 3 — Test the connection</h2>';
        if ($testResult) {
            $ok = ($testResult['status'] ?? '') === 'ok';
            echo '<div class="notice ' . ($ok ? 'notice-success' : 'notice-error') . '">'
               . '<p><strong>' . ($ok ? 'Connection OK' : 'Connection failed') . ':</strong> '
               . esc_html($testResult['message'] ?? '') . '</p>'
               . '</div>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="wpforge-test-form">';
        $this->nonceField('test_connection');
        echo '<input type="hidden" name="action" value="wpforge_test_connection">';
        echo '<label for="wpforge-test-user">Username</label> ';
        echo '<input type="text" id="wpforge-test-user" name="username" value="' . esc_attr($userName) . '"> ';
        echo '<label for="wpforge-test-pass">Password</label> ';
        echo '<input type="password" id="wpforge-test-pass" name="password" value="' . esc_attr($newAppPass ?: '') . '"> ';
        echo '<button type="submit" class="button button-primary">Test connection</button>';
        echo '</form>';
        echo '</div>';

        // ── Token management ─
        echo '<div class="wpforge-panel">';
        echo '<h2>Manage API tokens</h2>';
        if (empty($tokens)) {
            echo '<p class="wpforge-muted">No tokens yet.</p>';
        } else {
            echo '<table class="widefat striped">'
               . '<thead><tr><th>Token</th><th>Description</th><th>Created</th><th>Last used</th><th>Expires</th><th>Status</th><th></th></tr></thead><tbody>';
            foreach ($tokens as $t) {
                $status = !empty($t['revoked']) ? 'revoked' : 'active';
                echo '<tr>'
                   . '<td><code>' . esc_html(substr((string) $t['token_id'], 0, 14)) . '&hellip;</code></td>'
                   . '<td>' . esc_html($t['description'] ?? '') . '</td>'
                   . '<td>' . esc_html($t['created_at'] ?? '') . '</td>'
                   . '<td>' . esc_html($t['last_used'] ?? '—') . '</td>'
                   . '<td>' . esc_html($t['expires_at'] ?? '') . '</td>'
                   . '<td>' . ($status === 'active'
                       ? '<span class="wpforge-badge wpforge-badge--green">active</span>'
                       : '<span class="wpforge-badge wpforge-badge--grey">revoked</span>') . '</td>'
                   . '<td>';
                if ($status === 'active') {
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">';
                    $this->nonceField('revoke_token');
                    echo '<input type="hidden" name="action" value="wpforge_revoke_token">';
                    echo '<input type="hidden" name="token_id" value="' . esc_attr($t['token_id']) . '">';
                    echo '<button type="submit" class="button button-link-delete">Revoke</button>';
                    echo '</form>';
                }
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';

        $this->renderFooter();
    }

    /* ------------------------------------------------------------------ */
    /*  Snippet builders                                                   */
    /* ------------------------------------------------------------------ */

    private function mcpClaudeSnippet(string $siteUrl, string $username, string $password): string
    {
        return (string) wp_json_encode([
            'mcpServers' => [
                'wpforge' => [
                    'command' => 'npx',
                    'args'    => ['-y', '@wpforge/mcp-server'],
                    'env'     => [
                        'WPFORGE_BASE_URL' => $siteUrl,
                        'WPFORGE_USERNAME' => $username,
                        'WPFORGE_PASSWORD' => $password,
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function mcpCursorSnippet(string $siteUrl, string $username, string $password): string
    {
        return (string) wp_json_encode([
            'mcpServers' => [
                'wpforge' => [
                    'type'    => 'stdio',
                    'command' => 'npx',
                    'args'    => ['-y', '@wpforge/mcp-server'],
                    'env'     => [
                        'WPFORGE_BASE_URL' => $siteUrl,
                        'WPFORGE_USERNAME' => $username,
                        'WPFORGE_PASSWORD' => $password,
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function mcpCliSnippet(string $siteUrl, string $username, string $password): string
    {
        return sprintf(
            "npx -y @wpforge/mcp-server \\\n  --base-url=%s \\\n  --username=%s \\\n  --password=%s\n\n"
            . "# Local development (from this repo):\n"
            . "cd wpforge/mcp && npm install && npx tsx src/index.ts --base-url=%s --username=%s --password=%s",
            $siteUrl,
            $username,
            $password,
            $siteUrl,
            $username,
            $password
        );
    }

    private function curlSnippet(string $siteUrl, string $username, string $password): string
    {
        return sprintf(
            "curl -u '%s:%s' %s/wp-json/wpforge/v1/capabilities\n"
            . "curl -u '%s:%s' %s/wp-json/wpforge/v1/posts?per_page=5",
            $username,
            $password,
            $siteUrl,
            $username,
            $password,
            $siteUrl
        );
    }

    private function codeBlock(string $id, string $label, string $content): string
    {
        $out = '<div class="wpforge-snippet">';
        if ($label !== '') {
            $out .= '<div class="wpforge-snippet-head"><span>' . wp_kses_post($label) . '</span>'
                  . '<button type="button" class="button wpforge-copy" data-target="wpforge-code-' . esc_attr($id) . '">Copy</button></div>';
        }
        $out .= '<pre class="wpforge-code" id="wpforge-code-' . esc_attr($id) . '">' . esc_html($content) . '</pre>';
        $out .= '</div>';
        return $out;
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function tokens(): TokenManager
    {
        if ($this->tokenManager === null) {
            $this->tokenManager = new TokenManager();
        }
        return $this->tokenManager;
    }

    private function countActiveAppPasswords(int $userId): int
    {
        if (!function_exists('WP_Application_Passwords')) {
            return 0;
        }
        $passwords = \WP_Application_Passwords::get_user_application_passwords($userId);
        return count(array_filter($passwords, static fn($p) => empty($p['revoked'])));
    }

    private function guardNonce(string $action): void
    {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_key($_POST['_wpnonce']), self::NONCE_ACTION . ':' . $action)) {
            wp_die(esc_html__('Security check failed.', 'wpforge'), 403);
        }
    }

    private function nonceField(string $action): void
    {
        wp_nonce_field(self::NONCE_ACTION . ':' . $action, '_wpnonce');
    }

    private function consumeTransient(string $key): void
    {
        delete_transient($key);
    }

    /**
     * Probe a local REST endpoint, optionally with Basic auth.
     */
    private function probeEndpoint(string $route, ?string $basicAuth): array
    {
        $args = ['timeout' => 15, 'redirection' => 2];
        if ($basicAuth !== null) {
            $args['headers'] = ['Authorization' => 'Basic ' . base64_encode($basicAuth)];
        }

        $response = wp_remote_get(rest_url($route), $args);
        if (is_wp_error($response)) {
            return ['status' => 'error', 'message' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300 && isset($body['success']) && $body['success']) {
            return ['status' => 'ok', 'message' => 'HTTP ' . $code];
        }

        return [
            'status'  => 'error',
            'message' => 'HTTP ' . $code . ' — ' . (string) ($body['code'] ?? 'unexpected response') . ' ' . (string) ($body['message'] ?? ''),
        ];
    }

    private function probeCredentials(string $username, string $password): array
    {
        $result = $this->probeEndpoint('wpforge/v1/capabilities', $username . ':' . $password);

        if ($result['status'] === 'ok') {
            return ['status' => 'ok', 'message' => 'Authentication accepted. The AI bridge can talk to this site.'];
        }

        return [
            'status'  => 'fail',
            'message' => 'Authentication rejected. Check the username/password (use an Application Password, not your login password). ' . $result['message'],
        ];
    }

    private function card(string $title, string $badge, string $body): string
    {
        return '<div class="wpforge-card">'
             . '<h3>' . esc_html($title) . ' ' . $badge . '</h3>'
             . '<p>' . $body . '</p>'
             . '</div>';
    }

    private function renderHeader(string $title, string $subtitle): void
    {
        echo '<div class="wrap wpforge-wrap">';
        echo '<div class="wpforge-header">';
        echo '<div class="wpforge-header-logo">WF</div>';
        echo '<div><h1>WPForge &mdash; ' . esc_html($title) . '</h1>'
           . '<p class="wpforge-subtitle">' . wp_kses_post($subtitle) . '</p></div>';
        echo '</div>';
    }

    private function renderFooter(): void
    {
        echo '<hr class="wpforge-hr"><p class="wpforge-footer">WPForge v' . esc_html(defined('WPFORGE_VERSION') ? WPFORGE_VERSION : '')
           . ' &middot; REST API: <code>' . esc_html(rest_url('wpforge/v1')) . '</code>'
           . ' &middot; <a href="https://github.com/avangardistic/WPForge">Documentation</a></p>';
        echo '</div>';
    }
}
