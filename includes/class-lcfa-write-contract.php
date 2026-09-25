<?php

defined('ABSPATH') || exit;

/** Server-derived write contract. This is a freshness/scope check, not authorization. */
final class LCFA_Write_Contract {
    private const TTL = 600;

    public static function hooks(): void {
        add_filter('rest_request_before_callbacks', static function ($response, $handler, $request) {
            if ($response !== null || strpos($request->get_route(), '/lcfa/v1/') !== 0) return $response;
            $method = is_array($handler['callback'] ?? null) ? (string) $handler['callback'][1] : '';
            if (!self::guards_operation($method)) return $response;
            $payload = $request->get_json_params();
            $payload = is_array($payload) ? $payload : $request->get_params();
            if (self::read_only_subaction($method, $payload)) return $response;
            $result = self::validate($payload, $method);
            return empty($result['ok']) ? new WP_Error($result['code'], $result['message'], ['status' => 409, 'verification' => $result]) : $response;
        }, 10, 3);
    }

    public static function guards_operation(string $operation): bool {
        return (bool) preg_match('/^(save_theme_|restore_theme_|preview_content_patch|apply_content_patch|replace_media|run_polylang_tool|run_seo_tool|apply_studio_native|preview_studio_native|store_picostrap_bundle|save_windpress_|reset_windpress_|install_theme_library|import_theme_library|rollback_theme_library|complete_theme_library|execute_.*genesis|apply_page|preview_page|apply_partial|preview_partial|apply_dynamic|preview_dynamic|theme_file_write|theme_file_preview|theme_file_restore|preview_theme_file_write)/', $operation);
    }

    public static function prepare(array $args): array {
        try {
            $selector = self::selector($args);
            $state = self::state($selector);
            $proof = ['selector' => $selector, 'fingerprint' => self::hash($state), 'expires' => time() + self::TTL];
            $proof['signature'] = hash_hmac('sha256', wp_json_encode($proof), wp_salt('auth'));
            return ['ok' => true, 'write_context' => $proof, 'context' => $state, 'workflow' => self::workflow()];
        } catch (Throwable $e) {
            return self::error('context_unavailable', $e->getMessage());
        }
    }

    public static function workflow(): array {
        return [
            'before_write' => 'Call get_write_context for the exact target ID, URL, or child-theme path. Inspect rendering, roots and framework. Pass its write_context unchanged to preview and apply. Refresh after every mutation.',
            'styles' => 'Picowind uses Tailwind through WindPress. DaisyUI and Typography require current compiled evidence. Picostrap uses Bootstrap/Sass and its compile manifest. Unknown themes use inspected theme-native markup.',
            'content' => 'Editorial content contains prose and semantic HTML, not layout CSS or scripts. Use the effective template for layout and managed page_css/page_js for supported LiveCanvas pages. Do not duplicate document wrappers.',
            'verification' => 'Report saved, compiled, visually_verified and published separately. Inspect desktop/mobile if available. A successful save is not a visual check. When restoring a reference, compare its layout before changing implementation.',
            'boundary' => 'Bridge validates operations through its tools. It cannot govern arbitrary shell commands, direct SQL, other plugins or external WordPress APIs.',
        ];
    }

    public static function validate(array $payload, string $action = ''): array {
        if (is_array($payload['payload'] ?? null)) $payload = $payload['payload'];
        if (self::read_only_subaction($action, $payload)) return ['ok' => true, 'readonly' => true];
        $proof = $payload['write_context'] ?? null;
        if (!is_array($proof)) return self::error('context_required', 'Call get_write_context for this target before writing; pass write_context unchanged.');
        $signature = (string) ($proof['signature'] ?? '');
        unset($proof['signature']);
        if (!hash_equals(hash_hmac('sha256', wp_json_encode($proof), wp_salt('auth')), $signature) || (int) ($proof['expires'] ?? 0) < time()) {
            return self::error('context_expired', 'The context signature is invalid or expired. Read the target again.');
        }
        try {
            $selector = self::selector((array) ($proof['selector'] ?? []));
            $state = self::state($selector);
            if (!hash_equals((string) ($proof['fingerprint'] ?? ''), self::hash($state))) return self::error('stale_context', 'The site, target, theme or source assets changed. Read context and preview again.');
            $id = (int) ($payload['target_id'] ?? $payload['post_id'] ?? 0);
            if ($id !== $selector['target_id']) return self::error('target_mismatch', 'The context belongs to a different content target.');
            if (preg_match('/polylang|native_pattern_page/', $action)) return self::error('granular_write_required', 'This operation constructs or copies content outside the verified LiveCanvas write path. Use a separately inspected target write.');
            if (!empty($payload['target_url']) && $selector['target_url'] !== self::site_url((string) $payload['target_url'])) return self::error('target_mismatch', 'The target URL differs from the inspected URL.');
            $path = preg_match('/windpress/', $action) ? '' : (string) ($payload['path'] ?? $payload['relative_path'] ?? $payload['file_path'] ?? '');
            if ($path !== '' && ($selector['target_type'] !== 'theme_file' || $path !== $selector['path'])) return self::error('target_mismatch', 'Inspect the exact theme-file path before writing.');
            if ($path !== '' && ($payload['root_scope'] ?? 'stylesheet') !== 'stylesheet') return self::error('parent_theme_read_only', 'Use an active child-theme override. Parent-theme writes are not allowed.');
            if ($path !== '' && !$state['theme']['is_child_theme']) return self::error('child_theme_required', 'Create and activate a child theme before theme-file changes.');
            if ($state['target']['no_theme_edits'] && ($path !== '' || preg_match('/shell|design_system|foundation/', $action))) return self::error('scope_violation', 'This target has a stored no_theme_edits policy.');
            if ($state['target']['no_theme_edits'] && array_key_exists('no_theme_edits', $payload) && !$payload['no_theme_edits']) return self::error('scope_violation', 'An ordinary content write cannot relax the stored no_theme_edits policy. Review that policy in WordPress first.');
            if (!empty($payload['framework']) && $payload['framework'] !== $state['framework']) return self::error('framework_mismatch', 'The requested framework differs from the active theme.');
            if (preg_match('/^(page_upsert|update_page)$/', $action) && $id && $state['target']['post_type'] !== 'page') return self::error('render_target_mismatch', 'This is editorial content, not a LiveCanvas page. Edit its effective template for layout.');
            if (in_array($action, ['page_upsert', 'update_page'], true) && $id && (($state['rendering']['verified'] && $state['rendering']['kind'] !== 'livecanvas_page') || (!$state['rendering']['verified'] && !get_post_meta($id, '_lc_livecanvas_enabled', true)))) return self::error('render_target_mismatch', 'This page is rendered by a template, or its renderer could not be verified. Inspect and edit the effective template instead of replacing the page shell.');
            if ($action === 'create_page' && $id) return self::error('target_mismatch', 'Creating a page requires a new_page context with no existing ID.');
            if ($action === 'create_dynamic_template' && ($id || $selector['target_type'] !== 'dynamic_template')) return self::error('target_mismatch', 'Inspect target_type=dynamic_template with no ID before creating a shared template.');
            if (preg_match('/foundation|global_shell|design_system_apply|restore_audit|genesis|theme_library/', $action)) return self::error('granular_write_required', 'This multi-target operation cannot preserve target-scoped context. Use separately inspected page, partial, theme-file and asset writes.');
            if (preg_match('/windpress|picostrap_bundle/', $action) && $selector['target_type'] !== 'site') return self::error('shared_scope_required', 'Inspect site scope before changing shared build assets.');
            if (preg_match('/^(update_partial|update_dynamic_template)$/', $action) && $state['target']['post_type'] !== ($action === 'update_partial' ? 'lc_partial' : 'lc_dynamic_template')) return self::error('target_mismatch', 'The inspected target has a different LiveCanvas content type.');
            if (preg_match('/^update_(header|footer)$/', $action)) return self::error('granular_write_required', 'Resolve the shared partial ID and use update_partial with its own context.');
            if (in_array($action, ['page_upsert', 'create_page'], true) && !$id && $selector['target_type'] !== 'new_page') return self::error('target_mismatch', 'Inspect target_type=new_page before creating a page.');
            if (self::field($payload, 'footer_script') !== '') return self::error('inline_script_forbidden', 'Use managed page_js or a theme JavaScript asset, not footer_script.');
            if ($state['target']['impact'] !== 'one_page' && empty($payload['acknowledge_shared']) && $action !== 'validate') return self::error('shared_impact_required', 'This target can affect multiple pages. Review the impact and set acknowledge_shared=true.');
            $markup = self::markup($payload);
            $error = self::validate_markup($markup, $state, $path);
            if ($error !== '') return self::error('markup_contract_violation', $error);
            if ($state['target']['editorial'] && (self::field($payload, 'page_css') !== '' || self::field($payload, 'page_js') !== '')) return self::error('editorial_assets_forbidden', 'Move article layout and interaction assets into the effective child-theme template and pipeline.');
            return ['ok' => true, 'context' => $state, 'fingerprint' => $proof['fingerprint']];
        } catch (Throwable $e) {
            return self::error('context_unavailable', $e->getMessage());
        }
    }

    public static function selector(array $args): array {
        $id = (int) ($args['target_id'] ?? $args['post_id'] ?? 0);
        $url = (string) ($args['target_url'] ?? $args['url'] ?? '');
        if ($url !== '') {
            $url = self::site_url($url);
            $url_id = url_to_postid($url);
            if ($id && $url_id !== $id) throw new RuntimeException('URL and content ID do not resolve to the same target.');
            $id = $id ?: $url_id;
        }
        $type = (string) ($args['target_type'] ?? ($id ? 'content' : 'site'));
        if (!in_array($type, ['content', 'page', 'partial', 'dynamic_template', 'theme_file', 'site', 'new_page'], true)) throw new RuntimeException('Unsupported context target type.');
        $path = (string) ($args['path'] ?? '');
        if ($type === 'theme_file' && ($path === '' || preg_match('~(^/|\\\\|(^|/)\.\.?(/|$))~', $path))) throw new RuntimeException('Use a relative child-theme file path.');
        return ['target_id' => $id, 'target_url' => $url, 'target_type' => $type, 'path' => $path];
    }

    private static function read_only_subaction(string $operation, array $payload): bool {
        if (strpos($operation, 'polylang') !== false) return in_array($payload['action'] ?? 'list_languages', ['list_languages', 'get_translations'], true);
        if (strpos($operation, 'seo') !== false) return ($payload['action'] ?? 'get') === 'get';
        return false;
    }

    public static function update_discussion(array $payload, bool $dry_run = false): array {
        $check = self::validate($payload, 'update_discussion_settings');
        if (empty($check['ok'])) return $check;
        $id = (int) ($payload['target_id'] ?? $payload['post_id'] ?? 0);
        $before = get_post($id, ARRAY_A);
        if (!$id || !$before) return self::error('target_required', 'Inspect an existing post before changing discussion settings.');
        $changes = ['ID' => $id];
        foreach (['comment_status', 'ping_status'] as $field) {
            if (!array_key_exists($field, $payload)) continue;
            if (!in_array($payload[$field], ['open', 'closed'], true)) return self::error('invalid_discussion_status', 'Use open or closed for comment_status and ping_status.');
            $changes[$field] = $payload[$field];
        }
        if (count($changes) === 1) return self::error('settings_required', 'Provide comment_status or ping_status.');
        // wp_update_post merges the old content and runs KSES under the current
        // execution identity. Restore only existing editorial bytes, after KSES,
        // for this exact post. Never grant unfiltered_html or accept new HTML.
        $preserve = static function ($data, $postarr) use ($id, $before) {
            if ((int) ($postarr['ID'] ?? 0) === $id) {
                foreach (['post_content', 'post_content_filtered', 'post_excerpt', 'post_title'] as $field) $data[$field] = wp_slash($before[$field]);
            }
            return $data;
        };
        if (!$dry_run) {
            add_filter('wp_insert_post_data', $preserve, PHP_INT_MAX, 2);
            try { $updated = wp_update_post(wp_slash($changes), true); }
            finally { remove_filter('wp_insert_post_data', $preserve, PHP_INT_MAX); }
            if (is_wp_error($updated)) return self::error('discussion_update_failed', $updated->get_error_message());
        }
        $after = get_post($id, ARRAY_A);
        foreach (['post_content', 'post_content_filtered', 'post_excerpt', 'post_title'] as $field) {
            if ($before[$field] !== $after[$field]) return self::error('content_preservation_failed', 'A third-party save hook changed editorial content. Inspect the post and its revision before continuing.');
        }
        return ['ok' => true, 'target_id' => $id, 'content_preserved' => true, 'content_sha256' => hash('sha256', $after['post_content']),
            'execution_identity' => ['user_id' => get_current_user_id(), 'unfiltered_html' => current_user_can('unfiltered_html')],
            'verification_states' => ['saved' => !$dry_run, 'compiled' => 'not_applicable', 'visually_verified' => 'not_checked', 'published' => $after['post_status'] === 'publish']];
    }

    private static function state(array $selector): array {
        $theme = wp_get_theme();
        $roots = ['wordpress' => realpath(ABSPATH), 'stylesheet' => realpath(get_stylesheet_directory()), 'template' => realpath(get_template_directory())];
        if (in_array(false, $roots, true)) throw new RuntimeException('Canonical WordPress or theme roots are unavailable.');
        $id = $selector['target_id'];
        $post = $id ? get_post($id) : null;
        if ($id && !$post) throw new RuntimeException('The requested content target does not exist.');
        $framework = (new LCFA_Environment())->detect_framework_family();
        $sources = self::source_revision($roots);
        $pipeline = self::pipeline($framework, $sources);
        $rendering = LCFA_Render_Target::resolve($selector, $roots, $sources);
        $target = ['id' => $id, 'post_type' => $post ? $post->post_type : '', 'status' => $post ? $post->post_status : '',
            'editorial' => $post && !in_array($post->post_type, ['page', 'lc_partial', 'lc_dynamic_template', 'lc_block', 'lc_section'], true),
            'no_theme_edits' => $id && get_post_meta($id, '_lcfa_no_theme_edits', true) === '1',
            'revision' => $post ? self::hash([$post, get_post_meta($id)]) : '',
            'impact' => in_array($selector['target_type'], ['theme_file', 'site', 'partial', 'dynamic_template'], true) || ($post && in_array($post->post_type, ['lc_partial', 'lc_dynamic_template', 'lc_block', 'lc_section'], true)) ? 'multiple_pages' : 'one_page'];
        if ($selector['target_type'] === 'theme_file') {
            $absolute = $roots['stylesheet'] . '/' . $selector['path'];
            $existing_parent = dirname($absolute);
            while (!file_exists($existing_parent) && dirname($existing_parent) !== $existing_parent) $existing_parent = dirname($existing_parent);
            $canonical = realpath(file_exists($absolute) ? $absolute : $existing_parent);
            if (!$canonical || ($canonical !== $roots['stylesheet'] && strpos($canonical, $roots['stylesheet'] . '/') !== 0)) throw new RuntimeException('Theme path escapes the canonical child root.');
            $target['file_sha256'] = is_file($absolute) ? hash_file('sha256', $absolute) : '';
        }
        return ['site' => ['url' => home_url('/'), 'fingerprint' => LCFA_Settings::get_site_fingerprint(), 'blog_id' => get_current_blog_id()],
            'theme' => ['stylesheet' => $theme->get_stylesheet(), 'template' => $theme->get_template(), 'name' => $theme->get('Name'), 'version' => $theme->get('Version'), 'parent_version' => wp_get_theme($theme->get_template())->get('Version'), 'is_child_theme' => $theme->get_stylesheet() !== $theme->get_template()],
            'roots' => $roots, 'framework' => $framework, 'livecanvas_active' => (new LCFA_Environment())->is_livecanvas_active(),
            'target' => $target, 'rendering' => $rendering, 'source_revision' => $sources, 'pipeline' => $pipeline];
    }

    public static function source_revision(array $roots = []): string {
        if (!$roots) $roots = ['stylesheet' => realpath(get_stylesheet_directory()), 'template' => realpath(get_template_directory())];
        $files = [];
        foreach (array_unique(array_intersect_key($roots, array_flip(['stylesheet', 'template']))) as $root) {
            if (!$root) continue;
            $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
            $filter = new RecursiveCallbackFilterIterator($directory, static function ($file) {
                return !$file->isLink() && !in_array($file->getFilename(), ['.git', 'node_modules', 'vendor', '.lcfa-backups'], true);
            });
            foreach (new RecursiveIteratorIterator($filter) as $file) {
                if ($file->isFile() && preg_match('/\.(php|twig|latte|html|css|scss|json|js)$/', $file->getFilename())) $files[$file->getPathname()] = hash_file('sha256', $file->getPathname());
            }
        }
        global $wpdb;
        $posts = $wpdb->get_results("SELECT ID, post_type, post_status, post_content, post_modified_gmt, menu_order FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','attachment') ORDER BY ID", ARRAY_A);
        if ($posts === null) throw new RuntimeException('Cannot read the content source revision.');
        $meta = $wpdb->get_results("SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key LIKE 'is_%' OR meta_key IN ('lc_use_template_of_slug','_wp_page_template','_lc_livecanvas_enabled','_lcfa_page_css','_lcfa_page_js','_lcfa_no_theme_edits') ORDER BY post_id, meta_key, meta_id", ARRAY_A);
        if ($meta === null) throw new RuntimeException('Cannot read template-assignment revisions.');
        $volume = class_exists('WindPress\\WindPress\\Core\\Volume') ? \WindPress\WindPress\Core\Volume::get_entries() : [];
        $taxonomies = isset($wpdb->term_relationships, $wpdb->term_taxonomy, $wpdb->terms)
            ? $wpdb->get_results("SELECT r.object_id, r.term_taxonomy_id, t.taxonomy, t.parent, n.slug FROM {$wpdb->term_relationships} r JOIN {$wpdb->term_taxonomy} t ON r.term_taxonomy_id=t.term_taxonomy_id JOIN {$wpdb->terms} n ON n.term_id=t.term_id ORDER BY r.object_id, r.term_taxonomy_id", ARRAY_A) : [];
        if ($taxonomies === null) throw new RuntimeException('Cannot read taxonomy assignment revisions.');
        $plugin_revisions = [];
        if (defined('WP_PLUGIN_DIR')) foreach ((array) get_option('active_plugins') as $plugin) {
            if (is_file(WP_PLUGIN_DIR . '/' . $plugin)) $plugin_revisions[$plugin] = hash_file('sha256', WP_PLUGIN_DIR . '/' . $plugin);
        }
        ksort($files);
        return self::hash([$files, $posts, $meta, $volume, $taxonomies, $plugin_revisions, get_option('active_plugins'), get_option('theme_mods_' . get_stylesheet()), get_option('windpress_options'), get_option('picowind_options'), get_option('lc_settings'), get_option('show_on_front'), get_option('page_on_front'), get_option('page_for_posts'), get_option('permalink_structure')]);
    }

    private static function pipeline(string $framework, string $revision): array {
        $evidence = get_option('lcfa_windpress_compile_evidence', []);
        $valid = is_array($evidence) && ($evidence['source_revision'] ?? '') === $revision;
        $css_path = (string) ($evidence['cache_path'] ?? '');
        $valid = $valid && is_file($css_path) && hash_file('sha256', $css_path) === ($evidence['css_sha256'] ?? '');
        return ['strategy' => $framework === 'picowind' ? 'tailwind-windpress' : ($framework === 'picostrap' ? 'bootstrap-sass' : 'theme-native'),
            'windpress_active' => (new LCFA_Environment())->is_windpress_active(),
            'daisyui' => $valid && !empty($evidence['plugins']['daisyui']) ? 'compiled' : 'unverified',
            'typography' => $valid && !empty($evidence['plugins']['typography']) ? 'compiled' : 'unverified'];
    }

    public static function validate_markup(string $html, array $state, string $path = ''): string {
        $is_markup = $path === '' || preg_match('/\.(twig|latte|html|php)$/', $path);
        if (!$is_markup || trim($html) === '') return '';
        if (preg_match('/<\s*(?:style|script)\b|\sstyle\s*=/i', $html)) return 'Keep CSS and JavaScript out of content/template markup. Use the verified asset pipeline or supported managed asset fields.';
        if (preg_match('/<[^>]*\s(?:on[a-z]+|srcdoc)\s*=|(?:href|src)\s*=\s*["\']?\s*javascript\s*:/i', html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) return 'Inline event handlers and JavaScript URLs are not allowed. Use managed page_js or an enqueued theme script.';
        if ($path === '' && preg_match('/<!doctype|<\s*\/?\s*(?:html|head|body|main)\b/i', $html)) return 'Return a fragment without html, head, body or main wrappers; LiveCanvas owns the document shell.';
        preg_match_all('/\bclass\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $html, $matches, PREG_SET_ORDER);
        $classes = implode(' ', array_map(static function ($match) { return html_entity_decode(($match[1] ?? '') . ($match[2] ?? '') . ($match[3] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'); }, $matches));
        $framework = $state['framework'];
        if ($framework === 'picowind') {
            if (!$state['pipeline']['windpress_active']) return 'WindPress is unavailable. Restore the required compiler before changing Picowind markup.';
            if (preg_match('/(?:^|\s)(?:row|col-(?:md-|lg-|sm-)?\d+|container-fluid|navbar-expand-\w+|btn-outline-primary)(?:\s|$)/', $classes)) return 'Bootstrap layout classes are incompatible with this Picowind site. Use Tailwind.';
            if ($state['pipeline']['daisyui'] !== 'compiled' && preg_match('/(?:^|\s|:)(?:btn(?:-[\w-]+)?|card-body|card-title|hero-content|navbar|modal-box|bg-base-[123]00|text-base-content|bg-primary)(?:\s|$)/', $classes)) return 'DaisyUI has no current successful compile evidence. Use plain Tailwind or compile and verify DaisyUI first.';
            if ($state['pipeline']['typography'] !== 'compiled' && preg_match('/(?:^|\s|:)prose(?:-[\w-]+)?(?:\s|$)/', $classes)) return 'Typography has no current successful compile evidence. Use plain Tailwind or verify Typography first.';
        } elseif ($framework === 'picostrap' && preg_match('/(?:^|\s|:)(?:grid-cols-\d+|bg-base-[123]00|text-base-content|hero-content|prose)(?:\s|$)/', $classes)) {
            return 'Use Bootstrap and the Picostrap Sass pipeline on this site, not Tailwind/DaisyUI classes.';
        }
        return '';
    }

    private static function markup(array $payload): string {
        return implode("\n", array_map(static fn($key) => self::field($payload, $key), ['content', 'body_html', 'header_html', 'footer_html', 'footer_script']));
    }

    private static function field(array $payload, string $key): string {
        return isset($payload[$key]) ? (string) $payload[$key] : implode("\n", (array) ($payload[$key . '_lines'] ?? []));
    }

    private static function site_url(string $url): string {
        $site = wp_parse_url(home_url('/')); $target = wp_parse_url($url);
        foreach (['scheme', 'host', 'port'] as $key) if (($site[$key] ?? '') !== ($target[$key] ?? '')) throw new RuntimeException('The target URL must belong to this WordPress site.');
        if (!empty($target['user']) || !empty($target['pass']) || !empty($target['fragment'])) throw new RuntimeException('Use a site URL without credentials or a fragment.');
        return $url;
    }

    private static function hash($value): string { return hash('sha256', wp_json_encode($value)); }
    private static function error(string $code, string $message): array { return ['ok' => false, 'code' => $code, 'message' => $message, 'saved' => false, 'compiled' => false, 'visually_verified' => false, 'published' => false]; }
}
