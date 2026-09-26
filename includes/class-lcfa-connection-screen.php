<?php

defined('ABSPATH') || exit;

final class LCFA_Connection_Screen {
    public static function instructions(array $attempt): array {
        $archive = 'assets/runtime/livecanvas-ai-bridge-mcp-' . LCFA_MCP_PACKAGE_VERSION . '.tgz';
        if (!is_file(LCFA_DIR . $archive)) return [];
        $site = home_url('/');
        $descriptor = [
            'schema' => 'forge.connect.v1',
            'client' => $attempt['client'],
            'attempt' => $attempt['id'],
            'fingerprint' => $attempt['site_fingerprint'],
            'package_version' => LCFA_MCP_PACKAGE_VERSION,
            'site_url' => $site,
            'rest_base' => rest_url('lcfa/v1/'),
            'runtime_package' => LCFA_URL . $archive,
            'allow_local_http' => wp_parse_url($site, PHP_URL_SCHEME) === 'http' && self::is_local_host((string) wp_parse_url($site, PHP_URL_HOST)),
        ];
        $encoded = rtrim(strtr(base64_encode((string) wp_json_encode($descriptor)), '+/', '-_'), '=');
        // These single-quoted literals work in POSIX shells and PowerShell. Apostrophes are URL-encoded.
        $package = str_replace("'", '%27', $descriptor['runtime_package']);
        $command = "npx --yes --package='" . $package . "' livecanvas-forge-connect --descriptor '" . $encoded . "'";
        $label = LCFA_Agent_Registry::label($attempt['client']);
        return [
            'command' => $command,
            'prompt' => sprintf(
                "Connect %s to my WordPress site %s using the Forge installer below. Run it in this project's terminal. It preserves other MCP servers and requests Full Access for this connection only. Show me the WordPress verification URL and code and wait while I approve. If Node.js or npm is unavailable, explain the prerequisite. Do not disable TLS verification or client approval settings. After authorization, reload the MCP connection if needed and call get_connection_handoff from this client to verify the site. Do not modify site content during setup.\n\n%s",
                $label, $site, $command
            ),
        ];
    }

    public static function labels(): array {
        return [
            'notConnected' => __('Not connected', 'livecanvas-forge-ai'),
            'checking' => __('Checking connection…', 'livecanvas-forge-ai'),
            'preparing' => __('Preparing prompt…', 'livecanvas-forge-ai'),
            'waiting_for_client' => __('Waiting for your agent', 'livecanvas-forge-ai'),
            'authorization_required' => __('Approve connection', 'livecanvas-forge-ai'),
            'verifying' => __('Waiting for verification', 'livecanvas-forge-ai'),
            'ready' => __('Connected · verified', 'livecanvas-forge-ai'),
            'expired' => __('Setup expired', 'livecanvas-forge-ai'),
            'reconnect_required' => __('Connection needs attention', 'livecanvas-forge-ai'),
            'runtime_update_required' => __('Connection update required', 'livecanvas-forge-ai'),
            'access_revoked' => __('Access removed', 'livecanvas-forge-ai'),
            'access_changed' => __('Access permissions changed', 'livecanvas-forge-ai'),
            'session_expired' => __('Session expired', 'livecanvas-forge-ai'),
            'session_missing' => __('Connection no longer available', 'livecanvas-forge-ai'),
            'session_mismatch' => __('Connection does not match', 'livecanvas-forge-ai'),
            'site_changed' => __('Site identity changed', 'livecanvas-forge-ai'),
            'unavailable' => __('Connection status unavailable', 'livecanvas-forge-ai'),
            'failed' => __('Could not load the connection. Try again or use manual setup.', 'livecanvas-forge-ai'),
            'copyPrompt' => __('Copy prompt', 'livecanvas-forge-ai'),
            'copyCommand' => __('Copy command', 'livecanvas-forge-ai'),
            'copying' => __('Copying…', 'livecanvas-forge-ai'),
            'copied' => __('Copied!', 'livecanvas-forge-ai'),
            'copyManually' => __('Copy unavailable. Press %s to copy the selected text.', 'livecanvas-forge-ai'),
            'setupPrompt' => __('Setup prompt', 'livecanvas-forge-ai'),
            'setupCommand' => __('Setup command', 'livecanvas-forge-ai'),
            'verificationPromptLabel' => __('Verification prompt', 'livecanvas-forge-ai'),
            'firstTask' => __('First task', 'livecanvas-forge-ai'),
            'project' => __('Paste into your %s chat.', 'livecanvas-forge-ai'),
            'desktop' => __('Run in Terminal or PowerShell.', 'livecanvas-forge-ai'),
            'restartAgent' => __('Reload %s, then paste into its chat.', 'livecanvas-forge-ai'),
            'retry' => __('Try again', 'livecanvas-forge-ai'),
            'restart' => __('Start setup again', 'livecanvas-forge-ai'),
            'update' => __('Update connection', 'livecanvas-forge-ai'),
            'notVerified' => __('Not verified yet', 'livecanvas-forge-ai'),
            'proofNote' => __('Green confirms the last authenticated check, not continuous online status.', 'livecanvas-forge-ai'),
            'verificationPrompt' => sprintf(
                __('Use AI Bridge to call get_connection_handoff for %s. Verify site identity and runtime compatibility. Report any failed check. Do not edit the site.', 'livecanvas-forge-ai'),
                home_url('/')
            ),
            'testPrompt' => sprintf(
                __('Inspect %s with AI Bridge. Confirm the active parent and child themes, framework and asset pipeline. Get the write context and resolve the effective template before proposing changes. Do not edit or publish anything.', 'livecanvas-forge-ai'),
                home_url('/')
            ),
        ];
    }

    private static function logo(string $client): string {
        $logos = ['codex' => 'codex-color.svg', 'opencode' => 'opencode.svg', 'cursor' => 'cursor.svg', 'claude-code' => 'claude-color.svg', 'claude-desktop' => 'claude-color.svg'];
        return isset($logos[$client]) ? LCFA_URL . 'assets/agent-icons/' . $logos[$client] : '';
    }

    public static function render(array $connections): void {
        $user_id = get_current_user_id();
        $client = LCFA_Agent_Registry::from_connections($connections);
        $ids = LCFA_Connection_Attempt::recent_ids($user_id);
        $attempt_id = isset($_GET['connection_attempt']) && is_string($_GET['connection_attempt'])
            ? $_GET['connection_attempt']
            : (string) get_user_meta($user_id, 'lcfa_connection_attempt', true);
        $attempt = LCFA_Connection_Attempt::public_status($attempt_id, $user_id);
        if ($attempt) { $client = $attempt['client']; $ids[$client] = $attempt_id; }
        $core = ['codex', 'opencode', 'cursor', 'claude-code', 'claude-desktop'];
        $labels = self::labels();
        echo '<section class="lcfa-main lcfa-connect" id="lcfa-connect" data-attempt="' . esc_attr($attempt ? $attempt_id : '') . '" data-attempts="' . esc_attr(wp_json_encode($ids)) . '" data-tone="amber">';
        echo '<div class="lcfa-connect__context"><div class="lcfa-connect__site"><span class="lcfa-connect__label">' . esc_html__('WordPress site', 'livecanvas-forge-ai') . '</span><strong>' . esc_html(home_url('/')) . '</strong></div>';
        echo '<div class="lcfa-connect__agent"><label for="lcfa-connect-client">' . esc_html__('Coding agent', 'livecanvas-forge-ai') . '</label><div class="lcfa-connect__select">';
        $logo = self::logo($client);
        echo '<img data-connect-logo' . ($logo ? ' src="' . esc_url($logo) . '"' : ' hidden') . ' width="24" height="24" alt="" aria-hidden="true"><select id="lcfa-connect-client">';
        foreach ($core as $id) echo '<option value="' . esc_attr($id) . '" data-logo="' . esc_url(self::logo($id)) . '"' . selected($client, $id, false) . '>' . esc_html(LCFA_Agent_Registry::label($id)) . '</option>';
        echo '<optgroup label="' . esc_attr__('Additional clients (configuration preview)', 'livecanvas-forge-ai') . '">';
        foreach (LCFA_Agent_Registry::all() as $id => $agent) {
            if (in_array($id, $core, true) || $id === 'generic' || ($agent['globalTarget'] ?? '') === 'windsurf') continue;
            echo '<option value="' . esc_attr($id) . '"' . selected($client, $id, false) . '>' . esc_html($agent['label']) . '</option>';
        }
        echo '</optgroup></select></div></div></div>';
        echo '<div class="lcfa-connect__status"><span class="lcfa-connect__light" aria-hidden="true"></span><h2 role="status" aria-live="polite" data-connect-status>' . esc_html($labels['checking']) . '</h2></div>';
        echo '<div class="lcfa-connect__task">';
        echo '<div data-connect-approval hidden><p>' . esc_html__('Match this code in your agent.', 'livecanvas-forge-ai') . '</p><strong data-connect-code class="lcfa-connect__code"></strong>';
        echo '<label class="lcfa-connect__confirm"><input type="checkbox" data-connect-match><span>' . esc_html__('The code and site match.', 'livecanvas-forge-ai') . '</span></label>';
        echo '<p class="lcfa-connect__permissions"><strong>' . esc_html__('Full Access:', 'livecanvas-forge-ai') . '</strong> ' . esc_html__('Edit content, media and theme files; run builds, debugging, cache and SEO tools. Approve only an agent you trust.', 'livecanvas-forge-ai') . '</p></div>';
        echo '<div class="lcfa-connect__prompt-header"><label for="lcfa-connect-prompt" data-connect-prompt-label>' . esc_html($labels['setupPrompt']) . '</label>';
        echo '<button type="button" class="button button-primary lcfa-connect__copy" data-connect-copy disabled><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="8" y="8" width="12" height="13" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/></svg><span data-connect-copy-label>' . esc_html($labels['copyPrompt']) . '</span></button>';
        echo '<button type="button" class="button button-primary" data-connect-approve hidden disabled>' . esc_html__('Approve Full Access', 'livecanvas-forge-ai') . '</button>';
        echo '<button type="button" class="button" data-connect-retry hidden>' . esc_html($labels['retry']) . '</button></div>';
        echo '<textarea id="lcfa-connect-prompt" rows="5" readonly spellcheck="false" placeholder="' . esc_attr($labels['preparing']) . '" aria-describedby="lcfa-connect-destination lcfa-connect-copy-feedback"></textarea>';
        echo '<p id="lcfa-connect-destination" data-connect-instruction></p><p id="lcfa-connect-copy-feedback" role="status" aria-live="polite" data-connect-copy-feedback hidden></p>';
        echo '<p role="alert" data-connect-error hidden></p></div>';
        echo '<details data-connect-details><summary>' . esc_html__('Details & help', 'livecanvas-forge-ai') . '</summary><div class="lcfa-connect__details-body"><dl>';
        echo '<dt>' . esc_html__('Last verified', 'livecanvas-forge-ai') . '</dt><dd data-connect-verified>' . esc_html($labels['notVerified']) . '</dd>';
        echo '<dt>' . esc_html__('Setup runtime', 'livecanvas-forge-ai') . '</dt><dd data-connect-version></dd><dt>' . esc_html__('Required runtime', 'livecanvas-forge-ai') . '</dt><dd>' . esc_html(LCFA_MCP_PACKAGE_VERSION) . '</dd></dl>';
        echo '<p>' . esc_html($labels['proofNote']) . '</p><p>' . esc_html__('Setup requires approval in WordPress. Keep a backup before editing. Starting setup does not revoke an existing connection.', 'livecanvas-forge-ai') . '</p>';
        if (wp_parse_url(home_url('/'), PHP_URL_SCHEME) === 'http') echo '<p>' . esc_html__('Use HTTP only on a trusted local network. Public sites need HTTPS.', 'livecanvas-forge-ai') . '</p>';
        echo '<p><button type="button" class="button" data-connect-reset>' . esc_html($labels['restart']) . '</button></p>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=lcfa-dashboard&tab=connections&connection_ui=manual')) . '">' . esc_html__('Manual setup & connection management', 'livecanvas-forge-ai') . '</a>';
        echo '</div></details><noscript><p>' . esc_html__('Enable JavaScript or use manual setup under Details & help.', 'livecanvas-forge-ai') . '</p></noscript></section>';
    }

    private static function is_local_host(string $host): bool {
        return in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true) || (bool) preg_match('/\\.(local|test|localhost)$/i', $host);
    }
}
