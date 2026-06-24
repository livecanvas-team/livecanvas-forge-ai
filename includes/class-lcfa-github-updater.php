<?php

defined('ABSPATH') || exit;

final class LCFA_GitHub_Updater {
    private const SLUG = 'livecanvas-forge-ai';
    private const ASSET_NAME = 'livecanvas-forge-ai.zip';
    private const REPO_API = 'https://api.github.com/repos/livecanvas-team/livecanvas-forge-ai/releases/latest';
    private const UPDATE_API = 'https://livecanvas.com/wp-json/livecanvas-ai-bridge/v1/update';
    private const UPDATE_URI = 'https://livecanvas.com/ai-bridge';
    private const ICON_128_URL = 'https://raw.githubusercontent.com/livecanvas-team/livecanvas-forge-ai/main/assets/plugin-icon-128.png';
    private const ICON_256_URL = 'https://raw.githubusercontent.com/livecanvas-team/livecanvas-forge-ai/main/assets/plugin-icon-256.png';
    private const CACHE_KEY = 'lcfa_livecanvas_update_release';
    private const CACHE_TTL = 21600;
    private const NO_UPDATE_CACHE_TTL = 600;
    private const CACHE_SCHEMA = 5;

    private ?LCFA_Environment $environment;

    public function __construct(?LCFA_Environment $environment = null) {
        $this->environment = $environment;
    }

    public function hooks(): void {
        add_filter('update_plugins_livecanvas.com', [$this, 'filter_update_uri_response'], 10, 4);
        add_filter('update_plugins_github.com', [$this, 'filter_update_uri_response'], 10, 4);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'filter_update_transient']);
        add_filter('plugins_api', [$this, 'filter_plugins_api'], 10, 3);
    }

    public function get_update_state(): array {
        $eligible = $this->is_livecanvas_update_eligible();
        $state = [
            'eligible'         => $eligible,
            'current_version'  => $this->get_current_version(),
            'latest_version'   => '',
            'update_available' => false,
            'blocked_reason'   => $eligible ? '' : $this->get_livecanvas_blocked_reason(),
            'release_url'      => '',
            'source'           => 'livecanvas_license_endpoint',
        ];

        if (!$eligible) {
            return $state;
        }

        $release = $this->get_latest_release($this->is_forced_update_check());
        if (empty($release['ok'])) {
            $state['blocked_reason'] = 'release_unavailable';

            return $state;
        }

        $state['latest_version'] = (string) ($release['version'] ?? '');
        $state['release_url'] = (string) ($release['release_url'] ?? '');
        $state['update_available'] = $this->is_version_newer($state['latest_version']);
        $state['message'] = (string) ($release['message'] ?? '');
        $state['source'] = (string) ($release['source'] ?? $state['source']);

        return $state;
    }

    public function filter_update_uri_response($update, array $plugin_data, string $plugin_file, array $locales = []) {
        if (!$this->is_our_plugin_file($plugin_file)) {
            return $update;
        }

        return $this->get_update_response($plugin_file) ?: false;
    }

    public function filter_update_transient($transient) {
        if (!is_object($transient)) {
            return $transient;
        }

        $plugin_file = $this->get_plugin_file();
        $update = $this->get_update_response($plugin_file);

        if ($update) {
            if (!isset($transient->response) || !is_array($transient->response)) {
                $transient->response = [];
            }

            $transient->response[$plugin_file] = $update;
        } elseif (isset($transient->response) && is_array($transient->response)) {
            unset($transient->response[$plugin_file]);
        }

        return $transient;
    }

    public function filter_plugins_api($result, string $action, $args) {
        if ($action !== 'plugin_information' || !is_object($args) || (string) ($args->slug ?? '') !== self::SLUG) {
            return $result;
        }

        if (!$this->is_livecanvas_update_eligible()) {
            return $result;
        }

        $release = $this->get_latest_release($this->is_forced_update_check());
        if (empty($release['ok'])) {
            return $result;
        }

        return (object) [
            'name'          => 'LiveCanvas AI Bridge',
            'slug'          => self::SLUG,
            'version'       => (string) ($release['version'] ?? $this->get_current_version()),
            'author'        => '<a href="https://livecanvas.com/">The LiveCanvas Team</a>',
            'author_profile'=> 'https://livecanvas.com/',
            'homepage'      => self::UPDATE_URI,
            'requires'      => (string) ($release['requires'] ?? '6.0'),
            'tested'        => (string) ($release['tested'] ?? ''),
            'requires_php'  => (string) ($release['requires_php'] ?? '7.4'),
            'download_link' => (string) ($release['download_url'] ?? ''),
            'last_updated'  => (string) ($release['published_at'] ?? ''),
            'icons'         => $this->get_plugin_icons(),
            'sections'      => [
                'description' => 'AI companion and guided setup flow for LiveCanvas, Picostrap, Picowind, and WindPress.',
                'changelog'   => $this->format_release_notes((string) ($release['body'] ?? '')),
            ],
        ];
    }

    private function get_update_response(string $plugin_file) {
        if (!$this->is_livecanvas_update_eligible()) {
            return null;
        }

        $release = $this->get_latest_release($this->is_forced_update_check());
        if (empty($release['ok']) || !$this->is_version_newer((string) ($release['version'] ?? ''))) {
            return null;
        }

        return (object) [
            'id'            => self::UPDATE_URI,
            'slug'          => self::SLUG,
            'plugin'        => $plugin_file,
            'version'       => (string) ($release['version'] ?? ''),
            'new_version'   => (string) ($release['version'] ?? ''),
            'url'           => (string) ($release['release_url'] ?? self::UPDATE_URI),
            'package'       => (string) ($release['download_url'] ?? ''),
            'tested'        => (string) ($release['tested'] ?? ''),
            'requires'      => (string) ($release['requires'] ?? '6.0'),
            'requires_php'  => (string) ($release['requires_php'] ?? '7.4'),
            'icons'         => $this->get_plugin_icons(),
            'banners'       => [],
            'compatibility' => new stdClass(),
        ];
    }

    private function get_plugin_icons(): array {
        return [
            '1x'      => self::ICON_128_URL,
            '2x'      => self::ICON_256_URL,
            'default' => self::ICON_256_URL,
        ];
    }

    private function get_latest_release(bool $force = false): array {
        $cached = get_transient(self::CACHE_KEY);
        if (!$force && $this->is_valid_cached_release($cached)) {
            return $cached;
        }

        $livecanvas_release = $this->request_livecanvas_release();
        if (!$this->allow_github_fallback($livecanvas_release)) {
            return $this->cache_release_result($livecanvas_release);
        }

        $github_release = $this->request_github_release();

        if (!empty($livecanvas_release['ok']) && !empty($github_release['ok'])) {
            return $this->cache_release_result(
                $this->pick_newer_release($livecanvas_release, $github_release)
            );
        }

        if (!empty($livecanvas_release['ok'])) {
            return $this->cache_release_result($livecanvas_release);
        }

        if (!empty($github_release['ok'])) {
            return $this->cache_release_result($github_release);
        }

        return $this->cache_release_result($livecanvas_release);
    }

    private function pick_newer_release(array $first, array $second): array {
        $first_version = (string) ($first['version'] ?? '');
        $second_version = (string) ($second['version'] ?? '');

        if ($first_version === '') {
            return $second;
        }

        if ($second_version === '') {
            return $first;
        }

        return version_compare($second_version, $first_version, '>') ? $second : $first;
    }

    private function request_livecanvas_release(): array {
        if (!function_exists('wp_remote_post')) {
            return [
                'ok'      => false,
                'message' => 'wp_remote_post unavailable',
            ];
        }

        $license_key = $this->get_livecanvas_license_key();
        if ($license_key === '') {
            return [
                'ok'      => false,
                'message' => 'LiveCanvas license unavailable',
            ];
        }

        $response = wp_remote_post($this->get_update_endpoint(), [
            'timeout' => 12,
            'headers' => [
                'Accept'     => 'application/json',
                'User-Agent' => 'LiveCanvas AI Bridge/' . $this->get_current_version(),
            ],
            'body' => $this->build_update_request_body($license_key),
        ]);

        if (function_exists('is_wp_error') && is_wp_error($response)) {
            return [
                'ok'      => false,
                'message' => 'LiveCanvas update request failed',
            ];
        }

        $status = function_exists('wp_remote_retrieve_response_code')
            ? (int) wp_remote_retrieve_response_code($response)
            : (int) ($response['response']['code'] ?? 0);
        $body = function_exists('wp_remote_retrieve_body')
            ? (string) wp_remote_retrieve_body($response)
            : (string) ($response['body'] ?? '');

        if ($status === 401 || $status === 403) {
            return [
                'ok'      => false,
                'message' => 'LiveCanvas license rejected by update endpoint',
                'code'    => 'license_rejected',
            ];
        }

        if ($status !== 200 || $body === '') {
            return [
                'ok'      => false,
                'message' => 'LiveCanvas update endpoint unavailable',
            ];
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return [
                'ok'      => false,
                'message' => 'LiveCanvas update payload invalid',
            ];
        }

        return $this->normalize_livecanvas_payload($payload);
    }

    private function request_github_release(): array {
        if (!function_exists('wp_remote_get')) {
            return [
                'ok'      => false,
                'message' => 'wp_remote_get unavailable',
            ];
        }

        $response = wp_remote_get(self::REPO_API, [
            'timeout' => 10,
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'LiveCanvas AI Bridge/' . $this->get_current_version(),
            ],
        ]);

        if (function_exists('is_wp_error') && is_wp_error($response)) {
            return [
                'ok'      => false,
                'message' => 'GitHub request failed',
            ];
        }

        $status = function_exists('wp_remote_retrieve_response_code')
            ? (int) wp_remote_retrieve_response_code($response)
            : (int) ($response['response']['code'] ?? 0);
        $body = function_exists('wp_remote_retrieve_body')
            ? (string) wp_remote_retrieve_body($response)
            : (string) ($response['body'] ?? '');

        if ($status !== 200 || $body === '') {
            return [
                'ok'      => false,
                'message' => 'GitHub release unavailable',
            ];
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return [
                'ok'      => false,
                'message' => 'GitHub release payload invalid',
            ];
        }

        return $this->normalize_github_payload($payload);
    }

    private function normalize_livecanvas_payload(array $payload): array {
        if (isset($payload['release']) && is_array($payload['release'])) {
            $payload = $payload['release'];
        }

        if (isset($payload['ok']) && empty($payload['ok'])) {
            return [
                'ok'      => false,
                'message' => (string) ($payload['message'] ?? 'LiveCanvas update unavailable'),
                'code'    => (string) ($payload['code'] ?? ''),
            ];
        }

        $version = $this->normalize_version_string((string) ($payload['version'] ?? $payload['tag_name'] ?? ''));
        if ($version === '') {
            return [
                'ok'      => false,
                'message' => 'LiveCanvas update version is invalid',
            ];
        }

        $download_url = (string) ($payload['download_url'] ?? $payload['package_url'] ?? $payload['package'] ?? '');
        if (!$this->is_valid_package_url($download_url)) {
            return [
                'ok'      => false,
                'message' => 'LiveCanvas update package URL missing',
            ];
        }

        return [
            'ok'           => true,
            'version'      => $version,
            'tag'          => (string) ($payload['tag'] ?? 'v' . $version),
            'download_url' => $download_url,
            'release_url'  => (string) ($payload['release_url'] ?? $payload['url'] ?? self::UPDATE_URI),
            'published_at' => (string) ($payload['published_at'] ?? $payload['last_updated'] ?? ''),
            'body'         => (string) ($payload['body'] ?? $payload['changelog'] ?? ''),
            'requires'     => (string) ($payload['requires'] ?? '6.0'),
            'tested'       => (string) ($payload['tested'] ?? '7.0'),
            'requires_php' => (string) ($payload['requires_php'] ?? '7.4'),
            'message'      => (string) ($payload['message'] ?? ''),
            'source'       => 'livecanvas_license_endpoint',
        ];
    }

    private function normalize_github_payload(array $payload): array {
        if (!empty($payload['draft']) || !empty($payload['prerelease'])) {
            return [
                'ok'      => false,
                'message' => 'Draft or prerelease ignored',
            ];
        }

        $tag = (string) ($payload['tag_name'] ?? '');
        $version = $this->normalize_version_string($tag);
        if ($version === '') {
            return [
                'ok'      => false,
                'message' => 'Release tag is not a stable plugin version',
            ];
        }

        $download_url = '';
        foreach ((array) ($payload['assets'] ?? []) as $asset) {
            if (!is_array($asset) || (string) ($asset['name'] ?? '') !== self::ASSET_NAME) {
                continue;
            }

            $download_url = (string) ($asset['browser_download_url'] ?? '');
            break;
        }

        if ($download_url === '') {
            return [
                'ok'      => false,
                'message' => 'Release zip asset missing',
            ];
        }

        return [
            'ok'           => true,
            'version'      => $version,
            'tag'          => $tag,
            'download_url' => $download_url,
            'release_url'  => (string) ($payload['html_url'] ?? self::UPDATE_URI),
            'published_at' => (string) ($payload['published_at'] ?? ''),
            'body'         => (string) ($payload['body'] ?? ''),
            'requires'     => '6.0',
            'tested'       => '7.0',
            'requires_php' => '7.4',
            'source'       => 'github_release',
        ];
    }

    private function cache_release_result(array $result): array {
        $result['cache_schema'] = self::CACHE_SCHEMA;
        $result['checked_plugin_version'] = $this->get_current_version();
        $result['checked_at'] = function_exists('time') ? time() : 0;

        set_transient(self::CACHE_KEY, $result, $this->get_release_cache_ttl($result));

        return $result;
    }

    private function is_valid_cached_release($cached): bool {
        return is_array($cached)
            && (int) ($cached['cache_schema'] ?? 0) === self::CACHE_SCHEMA
            && (string) ($cached['checked_plugin_version'] ?? '') === $this->get_current_version();
    }

    private function get_release_cache_ttl(array $result): int {
        if (!empty($result['ok']) && $this->is_version_newer((string) ($result['version'] ?? ''))) {
            return self::CACHE_TTL;
        }

        return self::NO_UPDATE_CACHE_TTL;
    }

    private function get_update_endpoint(): string {
        $endpoint = defined('LCFA_UPDATE_ENDPOINT') ? (string) LCFA_UPDATE_ENDPOINT : self::UPDATE_API;

        if (function_exists('apply_filters')) {
            $endpoint = (string) apply_filters('lcfa_update_endpoint', $endpoint);
        }

        return trim($endpoint);
    }

    private function build_update_request_body(string $license_key): array {
        return [
            'license_key'    => $license_key,
            'plugin_slug'    => self::SLUG,
            'plugin_version' => $this->get_current_version(),
            'site_url'       => function_exists('home_url') ? home_url('/') : '',
            'wp_version'     => function_exists('get_bloginfo') ? (string) get_bloginfo('version') : '',
            'php_version'    => PHP_VERSION,
        ];
    }

    private function allow_github_fallback(array $livecanvas_result = []): bool {
        if (($livecanvas_result['code'] ?? '') === 'license_rejected') {
            return false;
        }

        $allowed = true;
        if (defined('LCFA_DISABLE_GITHUB_UPDATE_FALLBACK') && LCFA_DISABLE_GITHUB_UPDATE_FALLBACK) {
            $allowed = false;
        } elseif (defined('LCFA_ALLOW_GITHUB_UPDATE_FALLBACK')) {
            $allowed = (bool) LCFA_ALLOW_GITHUB_UPDATE_FALLBACK;
        }

        if (function_exists('apply_filters')) {
            $allowed = (bool) apply_filters('lcfa_allow_github_update_fallback', $allowed, $livecanvas_result);
        }

        return $allowed;
    }

    private function normalize_version_string(string $version): string {
        $version = trim($version);

        return preg_match('/^v?(\d+\.\d+\.\d+)$/', $version, $matches) ? $matches[1] : '';
    }

    private function is_valid_package_url(string $url): bool {
        if ($url === '') {
            return false;
        }

        return (bool) preg_match('#^https?://#i', $url);
    }

    private function is_forced_update_check(): bool {
        if (defined('WP_CLI') && WP_CLI) {
            return true;
        }

        if (isset($_GET['force-check']) || isset($_GET['lcfa-refresh-updates'])) {
            return true;
        }

        return false;
    }

    private function is_livecanvas_update_eligible(): bool {
        return $this->is_livecanvas_active() && $this->has_livecanvas_license();
    }

    private function get_livecanvas_blocked_reason(): string {
        if (!$this->is_livecanvas_active()) {
            return 'livecanvas_inactive';
        }

        return 'livecanvas_license_inactive';
    }

    private function is_livecanvas_active(): bool {
        if ($this->environment instanceof LCFA_Environment) {
            return $this->environment->is_livecanvas_active();
        }

        if (function_exists('lc_get_apikey') || function_exists('lc_post_is_using_livecanvas')) {
            return true;
        }

        if (function_exists('is_plugin_active')) {
            return is_plugin_active('livecanvas/livecanvas-plugin-index.php') || is_plugin_active('livecanvas/livecanvas.php');
        }

        if (function_exists('get_option')) {
            $active_plugins = (array) get_option('active_plugins', []);

            return in_array('livecanvas/livecanvas-plugin-index.php', $active_plugins, true)
                || in_array('livecanvas/livecanvas.php', $active_plugins, true);
        }

        return false;
    }

    private function has_livecanvas_license(): bool {
        return $this->get_livecanvas_license_key() !== '';
    }

    private function get_livecanvas_license_key(): string {
        if (function_exists('lc_get_apikey')) {
            $api_key = lc_get_apikey();

            return is_scalar($api_key) ? trim((string) $api_key) : '';
        }

        if (function_exists('get_site_option')) {
            $api_key = get_site_option('lc_apikey');

            return is_scalar($api_key) ? trim((string) $api_key) : '';
        }

        return '';
    }

    private function is_version_newer(string $version): bool {
        return $version !== '' && version_compare($version, $this->get_current_version(), '>');
    }

    private function get_current_version(): string {
        return defined('LCFA_VERSION') ? (string) LCFA_VERSION : '0.0.0';
    }

    private function get_plugin_file(): string {
        if (defined('LCFA_FILE') && function_exists('plugin_basename')) {
            return plugin_basename(LCFA_FILE);
        }

        return self::SLUG . '/' . self::SLUG . '.php';
    }

    private function is_our_plugin_file(string $plugin_file): bool {
        return $plugin_file === $this->get_plugin_file()
            || $plugin_file === self::SLUG . '/' . self::SLUG . '.php';
    }

    private function format_release_notes(string $body): string {
        $body = trim($body);

        return $body !== '' ? nl2br(esc_html($body)) : 'See the LiveCanvas release notes for changes.';
    }
}
