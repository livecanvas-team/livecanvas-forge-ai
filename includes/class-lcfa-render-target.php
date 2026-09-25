<?php

defined('ABSPATH') || exit;

/** Observe the normal public request, including LiveCanvas and Timber filters. */
final class LCFA_Render_Target {
    public static function hooks(): void {
        add_action('init', [self::class, 'observe_probe'], 1);
    }

    public static function observe_probe(): void {
        $token = isset($_GET['lcfa_render_probe']) ? (string) $_GET['lcfa_render_probe'] : '';
        if (!preg_match('/^[a-f0-9]{40}$/', $token) || !get_transient('lcfa_probe_' . $token)) return;
        delete_transient('lcfa_probe_' . $token);
        $trace = ['verified' => false, 'php_template' => '', 'engine_templates' => [], 'dynamic_template_id' => 0];
        add_filter('template_include', static function ($path) use (&$trace) {
            $trace['php_template'] = realpath($path) ?: '';
            $trace['dynamic_template_id'] = (int) ($GLOBALS['lc_rendered_dynamic_template_id'] ?? 0);
            $trace['queried_id'] = get_queried_object_id();
            $trace['livecanvas'] = function_exists('lc_post_is_using_livecanvas') && is_singular() && lc_post_is_using_livecanvas(get_queried_object_id());
            return $path;
        }, PHP_INT_MAX);
        $loader = null;
        add_filter('timber/loader/loader', static function ($value) use (&$loader) { $loader = $value; return $value; }, PHP_INT_MAX);
        $record_template = static function ($file) use (&$trace, &$loader) {
            if (!$file) return $file;
            try {
                $path = $loader && method_exists($loader, 'getSourceContext') ? $loader->getSourceContext($file)->getPath() : '';
                $trace['engine_templates'][] = ['name' => (string) $file, 'path' => $path ? (realpath($path) ?: '') : ''];
            } catch (Throwable $error) { $trace['engine_templates'][] = ['name' => (string) $file, 'path' => '']; }
            return $file;
        };
        add_filter('timber/compile/file', $record_template, PHP_INT_MAX);
        add_filter('timber/render/file', $record_template, PHP_INT_MAX);
        add_action('shutdown', static function () use (&$trace, $token) {
            $last = error_get_last();
            $fatal = $last && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);
            $trace['verified'] = !$fatal && $trace['php_template'] !== '' && http_response_code() < 400;
            set_transient('lcfa_trace_' . $token, $trace, 60);
        }, PHP_INT_MAX);
    }

    public static function resolve(array $selector, array $roots, string $revision = ''): array {
        $id = (int) $selector['target_id'];
        $post = $id ? get_post($id) : null;
        $type = $post ? $post->post_type : '';
        $base = ['verified' => false, 'kind' => 'unresolved', 'effective_template' => null,
            'impact' => 'unknown', 'audience' => 'public visitor', 'visual_check' => 'unavailable'];
        if ($selector['target_type'] === 'theme_file') {
            return array_merge($base, ['verified' => true, 'kind' => 'explicit_theme_file',
                'effective_template' => $roots['stylesheet'] . '/' . $selector['path'], 'impact' => 'multiple_pages']);
        }
        if (in_array($type, ['lc_partial', 'lc_dynamic_template', 'lc_block', 'lc_section'], true)) {
            return array_merge($base, ['verified' => true, 'kind' => $type, 'content_id' => $id, 'impact' => 'multiple_pages']);
        }
        if (in_array($selector['target_type'], ['site', 'new_page'], true) && !$id && !$selector['target_url']) {
            return array_merge($base, ['kind' => $selector['target_type'], 'impact' => $selector['target_type'] === 'site' ? 'multiple_pages' : 'one_page']);
        }
        $url = $selector['target_url'] ?: ($post && $post->post_status === 'publish' ? get_permalink($id) : '');
        if (!$url) return array_merge($base, ['reason' => 'No public URL. Inspect the explicit content or template target; no front-end claim is made.']);
        $key = 'lcfa_render_' . substr(hash('sha256', $url . $revision), 0, 40);
        $cached = get_transient($key);
        if (is_array($cached)) return $cached;
        $token = bin2hex(random_bytes(20));
        set_transient('lcfa_probe_' . $token, true, 30);
        // No credentials, redirects, TLS relaxation, or cross-site request headers.
        $response = wp_remote_get(add_query_arg('lcfa_render_probe', $token, $url), ['timeout' => 12, 'redirection' => 0, 'headers' => ['Cache-Control' => 'no-cache']]);
        $trace = get_transient('lcfa_trace_' . $token);
        delete_transient('lcfa_trace_' . $token);
        delete_transient('lcfa_probe_' . $token);
        if (is_wp_error($response) || !is_array($trace) || empty($trace['verified']) || ($id && (int) ($trace['queried_id'] ?? 0) !== $id)) {
            $result = array_merge($base, ['reason' => 'The public rendering probe was unavailable or failed. No browser/visual check was performed.']);
        } else {
            $result = array_merge($base, $trace, ['kind' => !empty($trace['dynamic_template_id']) ? 'livecanvas_dynamic_template' : (!empty($trace['livecanvas']) ? 'livecanvas_page' : 'theme_fallback'),
                'effective_template' => $trace['php_template'], 'impact' => !empty($trace['livecanvas']) && empty($trace['dynamic_template_id']) ? 'one_page' : 'multiple_pages']);
            foreach ($trace['engine_templates'] as $file) {
                if ($file['path'] !== '') {
                    $result['effective_template'] = $file['path'];
                    $result['engine'] = pathinfo($file['path'], PATHINFO_EXTENSION);
                    break;
                }
            }
        }
        set_transient($key, $result, 600);
        return $result;
    }
}
