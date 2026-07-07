<?php

defined('ABSPATH') || exit;

final class LCFA_Page_Runtime {
    public const META_PAGE_CSS = '_lcfa_page_css';
    public const META_PAGE_JS = '_lcfa_page_js';
    public const META_NO_THEME_EDITS = '_lcfa_no_theme_edits';
    public const META_PREVIEW_TOKEN_HASH = '_lcfa_public_preview_token_hash';
    public const META_PREVIEW_EXPIRES = '_lcfa_public_preview_expires';
    public const META_PREVIEW_CREATED = '_lcfa_public_preview_created_at';
    public const META_SEO_NOINDEX = '_lcfa_seo_noindex';
    public const META_SEO_CANONICAL = '_lcfa_seo_canonical';
    public const META_SEO_TITLE = '_lcfa_seo_title';
    public const META_SEO_DESCRIPTION = '_lcfa_seo_description';

    public function hooks(): void {
        add_action('pre_get_posts', [$this, 'allow_public_preview_query']);
        add_filter('redirect_canonical', [$this, 'disable_preview_canonical_redirect'], 10, 2);
        add_action('wp_head', [$this, 'render_head_assets'], 20);
        add_action('wp_footer', [$this, 'render_footer_assets'], 20);
    }

    public static function normalize_multiline(array $payload, string $string_key, string $lines_key): string {
        if (isset($payload[$string_key]) && trim((string) $payload[$string_key]) !== '') {
            $value = (string) $payload[$string_key];

            return function_exists('wp_unslash') ? wp_unslash($value) : stripslashes($value);
        }

        if (!is_array($payload[$lines_key] ?? null)) {
            return '';
        }

        return implode("\n", array_map('strval', (array) $payload[$lines_key]));
    }

    public static function persist_page_runtime(int $post_id, array $payload): array {
        $has_css = array_key_exists('page_css', $payload) || array_key_exists('page_css_lines', $payload);
        $has_js = array_key_exists('page_js', $payload) || array_key_exists('page_js_lines', $payload);
        $has_no_theme_edits = array_key_exists('no_theme_edits', $payload);
        $css = $has_css ? self::normalize_multiline($payload, 'page_css', 'page_css_lines') : (string) get_post_meta($post_id, self::META_PAGE_CSS, true);
        $js = $has_js ? self::normalize_multiline($payload, 'page_js', 'page_js_lines') : (string) get_post_meta($post_id, self::META_PAGE_JS, true);
        $no_theme_edits = $has_no_theme_edits
            ? !empty($payload['no_theme_edits'])
            : (string) get_post_meta($post_id, self::META_NO_THEME_EDITS, true) === '1';
        $seo = is_array($payload['seo'] ?? null) ? $payload['seo'] : [];

        if ($has_css) {
            self::update_or_delete_meta($post_id, self::META_PAGE_CSS, $css);
        }
        if ($has_js) {
            self::update_or_delete_meta($post_id, self::META_PAGE_JS, $js);
        }
        if ($has_no_theme_edits) {
            self::update_or_delete_meta($post_id, self::META_NO_THEME_EDITS, $no_theme_edits ? '1' : '');
        }

        if ($seo !== []) {
            self::persist_seo($post_id, $seo);
        }

        $result = [
            'page_css_lines'  => $css !== '' ? substr_count($css, "\n") + 1 : 0,
            'page_js_lines'   => $js !== '' ? substr_count($js, "\n") + 1 : 0,
            'no_theme_edits'  => $no_theme_edits,
            'seo'             => self::read_seo($post_id),
        ];

        $post_status = function_exists('get_post_status')
            ? (string) get_post_status($post_id)
            : sanitize_key((string) ($payload['status'] ?? 'publish'));
        if (in_array($post_status, ['draft', 'pending', 'private'], true)) {
            $preview = self::create_public_preview($post_id);
            $result['public_preview'] = $preview;
        }

        return $result;
    }

    public static function create_public_preview(int $post_id, int $ttl = 3600): array {
        $token = self::generate_token();
        $expires = time() + max(300, $ttl);

        update_post_meta($post_id, self::META_PREVIEW_TOKEN_HASH, self::hash_token($token));
        update_post_meta($post_id, self::META_PREVIEW_EXPIRES, (string) $expires);
        update_post_meta($post_id, self::META_PREVIEW_CREATED, current_time('mysql', true));

        return [
            'url'        => self::build_public_preview_url($post_id, $token),
            'expires_at' => gmdate('c', $expires),
            'ttl'        => max(300, $ttl),
        ];
    }

    public static function build_public_preview_url(int $post_id, string $token): string {
        $args = [
            'page_id'            => $post_id,
            'lcfa_preview_token' => rawurlencode($token),
        ];

        if (function_exists('add_query_arg') && function_exists('home_url')) {
            return add_query_arg($args, home_url('/'));
        }

        $base = function_exists('home_url') ? home_url('/') : '/';

        return rtrim($base, '/') . '/?' . http_build_query($args);
    }

    public static function validate_public_preview_token(int $post_id, string $token): bool {
        if ($post_id < 1 || $token === '') {
            return false;
        }

        $stored_hash = (string) get_post_meta($post_id, self::META_PREVIEW_TOKEN_HASH, true);
        $expires = (int) get_post_meta($post_id, self::META_PREVIEW_EXPIRES, true);

        return $stored_hash !== ''
            && $expires >= time()
            && hash_equals($stored_hash, self::hash_token($token));
    }

    public function allow_public_preview_query($query): void {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        $post_id = absint($query->get('page_id') ?: $query->get('p'));
        $token = isset($_GET['lcfa_preview_token']) ? sanitize_text_field(wp_unslash((string) $_GET['lcfa_preview_token'])) : '';

        if (!self::validate_public_preview_token($post_id, $token)) {
            return;
        }

        $query->set('post_type', ['page']);
        $query->set('post_status', ['publish', 'draft', 'pending', 'private']);
        $query->set('page_id', $post_id);
    }

    public function disable_preview_canonical_redirect($redirect_url, $requested_url) {
        if (!empty($_GET['lcfa_preview_token'])) {
            return false;
        }

        return $redirect_url;
    }

    public function render_head_assets(): void {
        $post_id = $this->current_post_id();
        if ($post_id < 1) {
            return;
        }

        if ($this->should_noindex($post_id)) {
            echo "\n<meta name=\"robots\" content=\"noindex,nofollow\">\n";
        }

        $canonical = (string) get_post_meta($post_id, self::META_SEO_CANONICAL, true);
        if ($canonical !== '') {
            echo '<link rel="canonical" href="' . esc_url($canonical) . "\">\n";
        }

        $css = (string) get_post_meta($post_id, self::META_PAGE_CSS, true);
        if (trim($css) !== '') {
            echo "\n<style id=\"lcfa-page-css\">\n" . self::safe_style($css) . "\n</style>\n";
        }
    }

    public function render_footer_assets(): void {
        $post_id = $this->current_post_id();
        if ($post_id < 1) {
            return;
        }

        $js = (string) get_post_meta($post_id, self::META_PAGE_JS, true);
        if (trim($js) !== '') {
            echo "\n<script id=\"lcfa-page-js\">\n" . self::safe_script($js) . "\n</script>\n";
        }
    }

    private function current_post_id(): int {
        if (is_admin() || !function_exists('is_singular') || !is_singular()) {
            return 0;
        }

        return absint(get_queried_object_id());
    }

    private function should_noindex(int $post_id): bool {
        if (!empty($_GET['lcfa_preview_token'])) {
            return true;
        }

        return (string) get_post_meta($post_id, self::META_SEO_NOINDEX, true) === '1';
    }

    private static function persist_seo(int $post_id, array $seo): void {
        $title = sanitize_text_field((string) ($seo['title'] ?? ''));
        $description = sanitize_text_field((string) ($seo['description'] ?? ''));
        $canonical = esc_url_raw((string) ($seo['canonical'] ?? ''));
        $noindex = !empty($seo['noindex']);

        self::update_or_delete_meta($post_id, self::META_SEO_TITLE, $title);
        self::update_or_delete_meta($post_id, self::META_SEO_DESCRIPTION, $description);
        self::update_or_delete_meta($post_id, self::META_SEO_CANONICAL, $canonical);
        self::update_or_delete_meta($post_id, self::META_SEO_NOINDEX, $noindex ? '1' : '');

        $plugin = self::detect_seo_plugin();
        if ($plugin === 'yoast') {
            self::update_or_delete_meta($post_id, '_yoast_wpseo_title', $title);
            self::update_or_delete_meta($post_id, '_yoast_wpseo_metadesc', $description);
            self::update_or_delete_meta($post_id, '_yoast_wpseo_canonical', $canonical);
            self::update_or_delete_meta($post_id, '_yoast_wpseo_meta-robots-noindex', $noindex ? '1' : '');
        } elseif ($plugin === 'seopress') {
            self::update_or_delete_meta($post_id, '_seopress_titles_title', $title);
            self::update_or_delete_meta($post_id, '_seopress_titles_desc', $description);
            self::update_or_delete_meta($post_id, '_seopress_robots_canonical', $canonical);
            self::update_or_delete_meta($post_id, '_seopress_robots_index', $noindex ? 'yes' : '');
        }
    }

    private static function read_seo(int $post_id): array {
        return [
            'title'       => (string) get_post_meta($post_id, self::META_SEO_TITLE, true),
            'description' => (string) get_post_meta($post_id, self::META_SEO_DESCRIPTION, true),
            'canonical'   => (string) get_post_meta($post_id, self::META_SEO_CANONICAL, true),
            'noindex'     => (string) get_post_meta($post_id, self::META_SEO_NOINDEX, true) === '1',
            'provider'    => self::detect_seo_plugin(),
        ];
    }

    private static function detect_seo_plugin(): string {
        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Options')) {
            return 'yoast';
        }

        if (defined('SEOPRESS_VERSION') || function_exists('seopress_activation')) {
            return 'seopress';
        }

        $plugin_file = defined('ABSPATH') ? ABSPATH . 'wp-admin/includes/plugin.php' : '';
        if ($plugin_file !== '' && is_readable($plugin_file)) {
            require_once $plugin_file;
        }

        if (function_exists('is_plugin_active')) {
            if (is_plugin_active('wordpress-seo/wp-seo.php')) {
                return 'yoast';
            }
            if (is_plugin_active('wp-seopress/seopress.php')) {
                return 'seopress';
            }
        }

        return 'fallback';
    }

    private static function update_or_delete_meta(int $post_id, string $key, string $value): void {
        if ($value === '') {
            delete_post_meta($post_id, $key);
            return;
        }

        update_post_meta($post_id, $key, $value);
    }

    private static function generate_token(): string {
        if (function_exists('wp_generate_password')) {
            return wp_generate_password(32, false, false);
        }

        return bin2hex(random_bytes(16));
    }

    private static function hash_token(string $token): string {
        $salt = function_exists('wp_salt') ? wp_salt('auth') : (defined('AUTH_KEY') ? AUTH_KEY : 'lcfa-public-preview');

        return hash_hmac('sha256', $token, $salt);
    }

    private static function safe_style(string $css): string {
        return str_ireplace('</style', '<\/style', $css);
    }

    private static function safe_script(string $js): string {
        return str_ireplace('</script', '<\/script', $js);
    }
}
