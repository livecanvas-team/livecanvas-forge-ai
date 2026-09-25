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

    public static function render(array $connections): void {
        $client = LCFA_Agent_Registry::from_connections($connections);
        $attempt_id = isset($_GET['connection_attempt']) && is_string($_GET['connection_attempt'])
            ? $_GET['connection_attempt']
            : (string) get_user_meta(get_current_user_id(), 'lcfa_connection_attempt', true);
        $attempt = LCFA_Connection_Attempt::public_status($attempt_id, get_current_user_id());
        if ($attempt) $client = $attempt['client'];
        $core = ['codex', 'opencode', 'cursor', 'claude-code', 'claude-desktop'];
        echo '<section class="lcfa-main lcfa-connect" id="lcfa-connect" data-attempt="' . esc_attr($attempt ? $attempt_id : '') . '">';
        echo '<h2>' . esc_html__('Connect your coding agent', 'livecanvas-forge-ai') . '</h2>';
        echo '<p class="lcfa-connect__site">' . esc_html__('WordPress site:', 'livecanvas-forge-ai') . ' <strong>' . esc_html(home_url('/')) . '</strong></p>';
        echo '<label for="lcfa-connect-client">' . esc_html__('Coding agent', 'livecanvas-forge-ai') . '</label>';
        echo '<select id="lcfa-connect-client">';
        foreach ($core as $id) echo '<option value="' . esc_attr($id) . '"' . selected($client, $id, false) . '>' . esc_html(LCFA_Agent_Registry::label($id)) . '</option>';
        echo '<optgroup label="' . esc_attr__('Additional clients (configuration preview)', 'livecanvas-forge-ai') . '">';
        foreach (LCFA_Agent_Registry::all() as $id => $agent) {
            if (in_array($id, $core, true) || $id === 'generic' || ($agent['globalTarget'] ?? '') === 'windsurf') continue;
            echo '<option value="' . esc_attr($id) . '"' . selected($client, $id, false) . '>' . esc_html($agent['label']) . '</option>';
        }
        echo '</optgroup></select>';
        echo '<p data-connect-instruction>' . esc_html__('Open your project in the selected agent and paste the setup instructions.', 'livecanvas-forge-ai') . '</p>';
        echo '<p class="lcfa-connect__access">' . esc_html__('Full Access: content, media, theme files, builds, debugging, cache and SEO. You will approve this connection in WordPress. Keep backups, especially on production sites. Existing connections keep their permissions.', 'livecanvas-forge-ai') . '</p>';
        if (wp_parse_url(home_url('/'), PHP_URL_SCHEME) === 'http') echo '<p class="lcfa-connect__warning">' . esc_html__('This site uses HTTP. Connect only on a trusted local development network. Public sites require HTTPS.', 'livecanvas-forge-ai') . '</p>';
        echo '<div class="lcfa-connect__actions"><button type="button" class="button button-primary" data-connect-copy>' . esc_html__('Copy setup instructions', 'livecanvas-forge-ai') . '</button>';
        echo '<button type="button" class="button button-primary" data-connect-approve hidden>' . esc_html__('Authorize Full Access', 'livecanvas-forge-ai') . '</button>';
        echo '<button type="button" class="button" data-connect-retry hidden>' . esc_html__('Start a new connection', 'livecanvas-forge-ai') . '</button></div>';
        echo '<p role="status" aria-live="polite" data-connect-status></p>';
        echo '<p data-connect-code hidden></p>';
        echo '<details data-connect-details><summary>' . esc_html__('Instructions and troubleshooting', 'livecanvas-forge-ai') . '</summary>';
        echo '<label for="lcfa-connect-prompt">' . esc_html__('Setup instructions', 'livecanvas-forge-ai') . '</label><textarea id="lcfa-connect-prompt" rows="9" readonly></textarea>';
        echo '<p>' . esc_html__('Claude Desktop: run the setup command in Terminal or PowerShell, then restart the app. Claude Code uses the project terminal. The installer never changes your agent approval settings.', 'livecanvas-forge-ai') . '</p>';
        echo '<p><button type="button" class="button" data-connect-reset>' . esc_html__('Start setup again', 'livecanvas-forge-ai') . '</button></p>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=lcfa-dashboard&tab=connections&connection_ui=manual')) . '">' . esc_html__('Open manual setup and connection management', 'livecanvas-forge-ai') . '</a>';
        echo '</details></section>';
    }

    private static function is_local_host(string $host): bool {
        return in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true) || (bool) preg_match('/\.(local|test|localhost)$/i', $host);
    }
}
