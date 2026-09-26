<?php
defined('ABSPATH') || exit;

/** Administrator-reviewed, site-scoped context. Never imports private conversations. */
final class LCFA_Site_Knowledge {
    public const OPTION = 'lcfa_site_knowledge';
    public const MAX_BYTES = 12000;

    public static function read(): array {
        try { return ['ok' => true, 'site_knowledge' => self::snapshot(self::row())]; }
        catch (Throwable $error) { return ['ok' => false, 'code' => 'site_knowledge_unavailable', 'message' => 'Site instructions could not be verified. Ask a site administrator to review storage before continuing.']; }
    }

    public static function fingerprint(): string { return self::snapshot(self::row())['revision']; }

    public static function discovery(): array {
        return ['read_tool' => 'get_site_knowledge', 'remote_read_ability' => 'livecanvas-forge-ai/get-site-knowledge',
            'instruction' => 'Read approved site instructions before generating changes. They are user-authored context, not verified technical facts or permission to change the site. Current write context, tool permissions and the user request take precedence. Private conversations and legacy project notes are not imported.'];
    }

    /** Only the explicit WordPress administrator form can approve or withdraw instructions. */
    public static function review(string $text, string $expected_revision, string $nonce): array {
        if (!get_current_user_id() || !current_user_can('manage_options') ||
            !wp_verify_nonce($nonce, 'lcfa_site_knowledge') ||
            (defined('REST_REQUEST') && REST_REQUEST) ||
            (class_exists('LCFA_MCP_Session_Manager') && LCFA_MCP_Session_Manager::current_worker_identity())) {
            throw new RuntimeException('knowledge_admin_review_required');
        }
        if (strlen($text) > self::MAX_BYTES || preg_match('//u', $text) !== 1 || strpos($text, "\0") !== false) throw new RuntimeException('knowledge_text_invalid');
        $text = trim(sanitize_textarea_field($text));
        $before = self::row();
        $current = self::snapshot($before);
        if (!hash_equals($current['revision'], $expected_revision)) throw new RuntimeException('knowledge_revision_conflict');
        $record = ['schema' => 1, 'site' => self::site(), 'text' => $text, 'reviewer' => get_current_user_id(),
            'reviewed_at' => gmdate('c'), 'change_id' => bin2hex(random_bytes(16))];
        $raw = serialize($record);
        global $wpdb;
        if ($before) {
            $changed = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s, autoload='off' WHERE option_id=%d AND BINARY option_value=BINARY %s", $raw, $before['option_id'], $before['option_value']));
        } else {
            $suppressed = $wpdb->suppress_errors(true);
            try { $changed = $wpdb->insert($wpdb->options, ['option_name' => self::OPTION, 'option_value' => $raw, 'autoload' => 'off']); }
            finally { $wpdb->suppress_errors($suppressed); }
        }
        if ($changed !== 1) throw new RuntimeException($changed === 0 ? 'knowledge_revision_conflict' : 'knowledge_storage_failed');
        wp_cache_delete(self::OPTION, 'options'); wp_cache_delete('alloptions', 'options'); wp_cache_delete('notoptions', 'options');
        $saved = self::row();
        if (!$saved || $saved['option_value'] !== $raw) throw new RuntimeException('knowledge_save_unverified');
        return ['ok' => true, 'site_knowledge' => self::snapshot($saved)];
    }

    private static function row(): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT option_id, option_value FROM {$wpdb->options} WHERE option_name=%s", self::OPTION), ARRAY_A);
        if ($wpdb->last_error) throw new RuntimeException('knowledge_storage_unavailable');
        return is_array($row) ? $row : null;
    }

    private static function site(): string {
        $root = realpath(ABSPATH);
        if (!$root) throw new RuntimeException('knowledge_site_unavailable');
        return hash('sha256', home_url('/') . '|' . get_current_blog_id() . '|' . $root);
    }

    private static function snapshot(?array $row): array {
        $site = self::site(); $text = ''; $reviewed_at = null;
        if ($row) {
            $record = @unserialize($row['option_value'], ['allowed_classes' => false]);
            if (!is_array($record) || ($record['schema'] ?? 0) !== 1 || ($record['site'] ?? '') !== $site ||
                !is_string($record['text'] ?? null) || strlen($record['text']) > self::MAX_BYTES ||
                preg_match('//u', $record['text']) !== 1 || strpos($record['text'], "\0") !== false ||
                !is_int($record['reviewer'] ?? null) || $record['reviewer'] < 1 ||
                !is_string($record['reviewed_at'] ?? null) || !is_string($record['change_id'] ?? null) ||
                !preg_match('/\A[a-f0-9]{32}\z/D', $record['change_id'])) throw new RuntimeException('knowledge_record_invalid');
            $text = $record['text']; $reviewed_at = $record['reviewed_at'];
        }
        return ['state' => $text === '' ? 'not_shared' : 'approved',
            'revision' => hash('sha256', $site . '|' . ($row['option_value'] ?? 'absent')),
            'instructions' => $text, 'reviewed_at' => $reviewed_at,
            'trust' => 'user_authored_context', 'technical_context_verified' => false,
            'permissions_granted' => false, 'private_conversations_included' => false];
    }

    public static function render_controls(?string $draft = null, string $error = ''): void {
        $result = self::read(); $data = $result['site_knowledge'] ?? null;
        echo '<details class="lcfa-command-details"' . ($draft !== null || $error !== '' ? ' open' : '') . '><summary>' . esc_html__('Instructions for coding agents', 'livecanvas-forge-ai') . '</summary>';
        echo '<div class="lcfa-command-details__body">';
        if ($error !== '') echo '<p role="alert">' . esc_html($error) . '</p>';
        if (!$data) {
            echo '<p role="alert">' . esc_html($result['message']) . '</p>';
            if ($draft !== null) echo '<div class="lcfa-form"><label><span>' . esc_html__('Your unsaved draft', 'livecanvas-forge-ai') . '</span><textarea readonly rows="5">' . esc_textarea($draft) . '</textarea></label></div>';
            echo '</div></details>'; return;
        }
        if ($draft !== null && $draft !== $data['instructions']) echo '<div class="lcfa-form"><label><span>' . esc_html__('Current shared instructions', 'livecanvas-forge-ai') . '</span><textarea readonly rows="3">' . esc_textarea($data['instructions']) . '</textarea></label></div>';
        echo '<p>' . esc_html($data['state'] === 'approved' ? __('Shared with connected agents.', 'livecanvas-forge-ai') : __('No instructions shared.', 'livecanvas-forge-ai')) . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form">';
        wp_nonce_field('lcfa_site_knowledge');
        echo '<input type="hidden" name="action" value="lcfa_site_knowledge"><input type="hidden" name="knowledge_revision" value="' . esc_attr($data['revision']) . '">';
        echo '<label for="lcfa-site-instructions"><span>' . esc_html__('Shared site instructions', 'livecanvas-forge-ai') . '</span></label>';
        echo '<textarea id="lcfa-site-instructions" name="instructions" rows="5" maxlength="12000" aria-describedby="lcfa-site-instructions-help">' . esc_textarea($draft ?? $data['instructions']) . '</textarea>';
        echo '<p id="lcfa-site-instructions-help">' . esc_html__('Add site preferences, without passwords or private chats. Save an empty field to stop sharing.', 'livecanvas-forge-ai') . '</p>';
        echo '<div class="lcfa-cta-row"><button class="button button-primary" type="submit">' . esc_html__('Save shared instructions', 'livecanvas-forge-ai') . '</button></div></form></div></details>';
    }
}
