<?php

defined('ABSPATH') || exit;

final class LCFA_Theme_Library_Importer {
    private const IMPORTS_OPTION = 'lcfa_theme_library_imports';

    private LCFA_Theme_Library_Installer $installer;
    private LCFA_Theme_Library_Validator $validator;
    private LCFA_WindPress_Bridge $windpress_bridge;

    public function __construct(LCFA_Theme_Library_Installer $installer, LCFA_Theme_Library_Validator $validator, LCFA_WindPress_Bridge $windpress_bridge) {
        $this->installer = $installer;
        $this->validator = $validator;
        $this->windpress_bridge = $windpress_bridge;
    }

    public function import(array $theme, bool $force = false): array {
        $download = $this->installer->download_theme_zip($theme);
        if (empty($download['ok'])) {
            return $download;
        }

        $zip_path = (string) $download['zip_path'];
        $validation = $this->validator->validate_zip($zip_path, $theme);
        if (empty($validation['ok'])) {
            $this->delete_file($zip_path);
            return $validation;
        }

        $manifest = is_array($validation['manifest'] ?? null) ? $validation['manifest'] : [];
        $slug = sanitize_key((string) ($manifest['theme']['slug'] ?? $theme['slug'] ?? ''));
        $version = sanitize_text_field((string) ($manifest['theme']['version'] ?? $theme['version'] ?? ''));
        $checksum = (string) ($validation['checksum'] ?? '');
        $import_key = $slug . ':' . $version . ':' . $checksum;

        $imports = $this->get_imports();
        $existing_import = is_array($imports[$slug] ?? null) ? $imports[$slug] : [];
        $existing_status = (string) ($existing_import['status'] ?? 'imported');
        if (!$force && $existing_import && $existing_status !== 'failed' && (string) ($existing_import['import_key'] ?? '') === $import_key) {
            $this->delete_file($zip_path);
            return [
                'ok'       => true,
                'status'   => 'already_imported',
                'message'  => __('This Theme Library item is already imported at the same version and checksum.', 'livecanvas-forge-ai'),
                'import'   => $existing_import,
                'manifest' => $manifest,
            ];
        }

        $stylesheet = sanitize_key((string) ($manifest['theme']['stylesheet'] ?? $slug));
        if ($stylesheet !== '' && !wp_get_theme($stylesheet)->exists()) {
            $this->delete_file($zip_path);
            return [
                'ok'      => false,
                'message' => __('Install the child theme before importing starter data.', 'livecanvas-forge-ai'),
            ];
        }

        $destination = trailingslashit(get_temp_dir()) . 'lcfa-theme-library-' . wp_generate_password(8, false, false);
        $extract = $this->validator->extract_zip($zip_path, $destination);
        $this->delete_file($zip_path);
        if (empty($extract['ok'])) {
            return $extract;
        }

        $base_dir = trailingslashit($destination);
        $root = trim((string) ($validation['root'] ?? ''), '/');
        if ($root !== '') {
            $base_dir .= trailingslashit($root);
        }

        $audit_id = sanitize_key('theme-import-' . $slug . '-' . strtolower(wp_generate_password(8, false, false)));
        $rollback = [
            'type'              => 'theme_library_import',
            'audit_id'          => $audit_id,
            'theme_slug'        => $slug,
            'theme_version'     => $version,
            'checksum'          => $checksum,
            'import_key'        => $import_key,
            'created_posts'     => [],
            'updated_posts'     => [],
            'created_media'     => [],
            'updated_options'   => [],
            'previous_theme'    => wp_get_theme()->get_stylesheet(),
            'previous_theme_mods'=> [
                'nav_menu_locations' => get_theme_mod('nav_menu_locations', []),
            ],
            'created_menus'     => [],
        ];

        $result = [
            'ok'              => true,
            'status'          => 'imported',
            'message'         => __('Theme Library starter data imported.', 'livecanvas-forge-ai'),
            'import_audit_id' => $audit_id,
            'theme_slug'      => $slug,
            'theme_version'   => $version,
            'checksum'        => $checksum,
            'steps'           => [],
            'warnings'        => [],
        ];

        try {
            if ($stylesheet !== '' && wp_get_theme($stylesheet)->exists() && wp_get_theme()->get_stylesheet() !== $stylesheet) {
                switch_theme($stylesheet);
                $result['steps'][] = 'theme_activated';
            }

            $this->import_options($base_dir, (string) ($manifest['livecanvas_settings'] ?? ''), $rollback, $result);
            $this->import_design_system($base_dir, $manifest, $rollback, $result);
            $media_map = $this->import_media($base_dir, (string) ($manifest['media_manifest'] ?? ''), $slug, $checksum, $rollback, $result);

            $header = $this->read_content_file($base_dir, (string) ($manifest['header']['content_file'] ?? ''), $media_map);
            $footer = $this->read_content_file($base_dir, (string) ($manifest['footer']['content_file'] ?? ''), $media_map);
            $homepage = $this->read_content_file($base_dir, (string) ($manifest['homepage']['content_file'] ?? ''), $media_map);

            if (preg_match('/<\\/?(?:header|footer)\\b/i', $homepage)) {
                throw new RuntimeException(__('Homepage content must not contain inline header or footer markup.', 'livecanvas-forge-ai'));
            }

            $header_id = $this->upsert_partial('header', $manifest['header'], $header, $slug, $version, $audit_id, $rollback);
            $footer_id = $this->upsert_partial('footer', $manifest['footer'], $footer, $slug, $version, $audit_id, $rollback);
            $page_id = $this->upsert_homepage($manifest['homepage'], $homepage, $slug, $version, $audit_id, $rollback);

            $result['header_id'] = $header_id;
            $result['footer_id'] = $footer_id;
            $result['homepage_id'] = $page_id;
            $result['steps'][] = 'livecanvas_content_imported';

            $this->import_menus($base_dir, (string) ($manifest['menus_file'] ?? ''), $rollback, $result);
            $this->set_homepage($page_id, $rollback, $result);

            $flush = $this->windpress_bridge->flush_runtime_cache();
            if (empty($flush['ok'])) {
                $result['warnings'][] = (string) ($flush['message'] ?? __('WindPress cache flush was not available.', 'livecanvas-forge-ai'));
            } else {
                $result['steps'][] = 'windpress_cache_flushed';
            }

            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }

            $imports[$slug] = [
                'slug'       => $slug,
                'version'    => $version,
                'checksum'   => $checksum,
                'status'     => 'imported',
                'import_key' => $import_key,
                'audit_id'   => $audit_id,
                'imported_at'=> current_time('mysql', true),
                'homepage_id'=> $page_id,
                'header_id'  => $header_id,
                'footer_id'  => $footer_id,
            ];
            update_option(self::IMPORTS_OPTION, $imports, false);

            LCFA_Settings::store_rollback_record($audit_id, $rollback);
        } catch (Throwable $throwable) {
            $result = [
                'ok'              => false,
                'message'         => $throwable->getMessage(),
                'import_audit_id' => $audit_id,
                'rollback_stored' => true,
            ];
            $imports[$slug] = [
                'slug'       => $slug,
                'version'    => $version,
                'checksum'   => $checksum,
                'status'     => 'failed',
                'import_key' => $import_key,
                'audit_id'   => $audit_id,
                'imported_at'=> current_time('mysql', true),
                'error'      => $throwable->getMessage(),
            ];
            update_option(self::IMPORTS_OPTION, $imports, false);
            LCFA_Settings::store_rollback_record($audit_id, $rollback);
        }

        $this->delete_directory($destination);

        return $result;
    }

    public static function get_imports(): array {
        $imports = get_option(self::IMPORTS_OPTION, []);

        return is_array($imports) ? $imports : [];
    }

    private function import_options(string $base_dir, string $relative_path, array &$rollback, array &$result): void {
        $settings = $this->read_json_file($base_dir, $relative_path);
        $options = is_array($settings['options'] ?? null) ? $settings['options'] : [];
        foreach ($options as $option_name => $value) {
            $option_name = sanitize_key((string) $option_name);
            if ($option_name === '') {
                continue;
            }

            if (!array_key_exists($option_name, $rollback['updated_options'])) {
                $rollback['updated_options'][$option_name] = [
                    'exists' => get_option($option_name, '__lcfa_missing__') !== '__lcfa_missing__',
                    'value'  => get_option($option_name),
                ];
            }

            update_option($option_name, $value, false);
        }

        if ($options) {
            $result['steps'][] = 'livecanvas_settings_imported';
        }
    }

    private function import_design_system(string $base_dir, array $manifest, array &$rollback, array &$result): void {
        $design_path = (string) ($manifest['design_system_file'] ?? '');
        $design = $this->read_json_file($base_dir, $design_path);
        if ($design) {
            $saved = $this->windpress_bridge->save_theme_json($design);
            if (empty($saved['ok'])) {
                $result['warnings'][] = (string) ($saved['message'] ?? __('WindPress design system import was not available.', 'livecanvas-forge-ai'));
            } else {
                $result['steps'][] = 'windpress_theme_json_imported';
            }
        }

        $css_path = $this->safe_join($base_dir, 'public/styles/tailwind.css');
        if ($css_path !== '' && is_readable($css_path)) {
            $css = (string) file_get_contents($css_path);
            if ($css !== '') {
                $saved_css = $this->windpress_bridge->save_cache_css($css);
                if (empty($saved_css['ok'])) {
                    $result['warnings'][] = (string) ($saved_css['message'] ?? __('WindPress CSS cache import was not available.', 'livecanvas-forge-ai'));
                } else {
                    $result['steps'][] = 'windpress_css_imported';
                }
            }
        }
    }

    private function import_media(string $base_dir, string $relative_path, string $theme_slug, string $checksum, array &$rollback, array &$result): array {
        $manifest = $this->read_json_file($base_dir, $relative_path);
        $items = [];
        if (isset($manifest['items']) && is_array($manifest['items'])) {
            $items = $manifest['items'];
        } elseif (isset($manifest['media']) && is_array($manifest['media'])) {
            $items = $manifest['media'];
        }

        $map = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $asset_id = sanitize_key((string) ($item['id'] ?? $item['asset_id'] ?? ''));
            $file = (string) ($item['file'] ?? '');
            if ($asset_id === '' || $file === '') {
                continue;
            }

            $existing_id = $this->find_attachment($theme_slug, $asset_id, $checksum);
            if ($existing_id > 0) {
                $map[$asset_id] = [
                    'id'  => $existing_id,
                    'url' => wp_get_attachment_url($existing_id),
                ];
                continue;
            }

            $source_path = $this->safe_join($base_dir, $file);
            if ($source_path === '' || !is_readable($source_path)) {
                $result['warnings'][] = sprintf('Media asset "%s" was not found.', $asset_id);
                continue;
            }

            $attachment_id = $this->sideload_local_file($source_path, [
                'title'   => sanitize_text_field((string) ($item['title'] ?? $asset_id)),
                'alt'     => sanitize_text_field((string) ($item['alt'] ?? '')),
                'caption' => sanitize_text_field((string) ($item['caption'] ?? '')),
            ]);

            if ($attachment_id <= 0) {
                $result['warnings'][] = sprintf('Media asset "%s" could not be imported.', $asset_id);
                continue;
            }

            update_post_meta($attachment_id, '_lcfa_theme_library_slug', $theme_slug);
            update_post_meta($attachment_id, '_lcfa_theme_library_asset_id', $asset_id);
            update_post_meta($attachment_id, '_lcfa_theme_library_checksum', $checksum);
            $rollback['created_media'][] = $attachment_id;

            $map[$asset_id] = [
                'id'  => $attachment_id,
                'url' => wp_get_attachment_url($attachment_id),
            ];
        }

        if ($map) {
            $result['steps'][] = 'media_imported';
        }

        return $map;
    }

    private function read_content_file(string $base_dir, string $relative_path, array $media_map): string {
        $path = $this->safe_join($base_dir, $relative_path);
        if ($path === '' || !is_readable($path)) {
            throw new RuntimeException(__('Theme content file was not found.', 'livecanvas-forge-ai'));
        }

        $content = (string) file_get_contents($path);
        foreach ($media_map as $asset_id => $media) {
            $url = (string) ($media['url'] ?? '');
            $content = str_replace([
                '{{media:' . $asset_id . '}}',
                '{{media:' . $asset_id . ':url}}',
            ], $url, $content);
        }

        return $content;
    }

    private function upsert_partial(string $type, array $definition, string $content, string $theme_slug, string $version, string $audit_id, array &$rollback): int {
        $variant = sanitize_text_field((string) ($definition['variant'] ?? '1'));
        $title = sanitize_text_field((string) ($definition['title'] ?? ucfirst($type)));
        $post_id = $this->find_imported_post('lc_partial', $theme_slug, $type);

        $postarr = [
            'post_type'    => 'lc_partial',
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_content' => $content,
        ];

        if ($post_id > 0) {
            $this->record_post_rollback($post_id, $rollback);
            $postarr['ID'] = $post_id;
            wp_update_post($postarr, true);
        } else {
            $post_id = (int) wp_insert_post($postarr, true);
            if ($post_id <= 0 || is_wp_error($post_id)) {
                throw new RuntimeException(__('LiveCanvas partial could not be created.', 'livecanvas-forge-ai'));
            }
            $rollback['created_posts'][] = $post_id;
        }

        update_post_meta($post_id, $type === 'header' ? 'is_header' : 'is_footer', $variant);
        update_post_meta($post_id, '_lcfa_theme_library_slug', $theme_slug);
        update_post_meta($post_id, '_lcfa_theme_library_version', $version);
        update_post_meta($post_id, '_lcfa_theme_library_import_id', $audit_id);
        update_post_meta($post_id, '_lcfa_theme_library_part', $type);

        return $post_id;
    }

    private function upsert_homepage(array $definition, string $content, string $theme_slug, string $version, string $audit_id, array &$rollback): int {
        $title = sanitize_text_field((string) ($definition['title'] ?? 'Home'));
        $slug = sanitize_title((string) ($definition['slug'] ?? 'home'));
        $template = sanitize_text_field((string) ($definition['template'] ?? ''));
        $post_id = $this->find_imported_post('page', $theme_slug, 'homepage');

        if ($post_id <= 0 && $slug !== '') {
            $existing = get_page_by_path($slug, OBJECT, 'page');
            if ($existing instanceof WP_Post) {
                $post_id = (int) $existing->ID;
            }
        }

        $postarr = [
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => $content,
        ];

        if ($post_id > 0) {
            $this->record_post_rollback($post_id, $rollback);
            $postarr['ID'] = $post_id;
            wp_update_post($postarr, true);
        } else {
            $post_id = (int) wp_insert_post($postarr, true);
            if ($post_id <= 0 || is_wp_error($post_id)) {
                throw new RuntimeException(__('Homepage could not be created.', 'livecanvas-forge-ai'));
            }
            $rollback['created_posts'][] = $post_id;
        }

        update_post_meta($post_id, '_lc_livecanvas_enabled', '1');
        update_post_meta($post_id, '_lcfa_theme_library_slug', $theme_slug);
        update_post_meta($post_id, '_lcfa_theme_library_version', $version);
        update_post_meta($post_id, '_lcfa_theme_library_import_id', $audit_id);
        update_post_meta($post_id, '_lcfa_theme_library_part', 'homepage');
        if ($template !== '') {
            update_post_meta($post_id, '_wp_page_template', $template);
        }

        return $post_id;
    }

    private function import_menus(string $base_dir, string $relative_path, array &$rollback, array &$result): void {
        $manifest = $this->read_json_file($base_dir, $relative_path);
        $menus = is_array($manifest['menus'] ?? null) ? $manifest['menus'] : [];
        if (!$menus) {
            return;
        }

        $locations = get_theme_mod('nav_menu_locations', []);
        $rollback['previous_theme_mods']['nav_menu_locations'] = $locations;

        foreach ($menus as $menu) {
            if (!is_array($menu)) {
                continue;
            }

            $name = sanitize_text_field((string) ($menu['name'] ?? 'Theme Library Menu'));
            $location = sanitize_key((string) ($menu['location'] ?? 'primary'));
            $menu_term = wp_get_nav_menu_object($name);
            if (!$menu_term) {
                $menu_id = wp_create_nav_menu($name);
                if (is_wp_error($menu_id)) {
                    $result['warnings'][] = $menu_id->get_error_message();
                    continue;
                }
                $rollback['created_menus'][] = (int) $menu_id;
            } else {
                $menu_id = (int) $menu_term->term_id;
            }

            foreach ((array) ($menu['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                wp_update_nav_menu_item($menu_id, 0, [
                    'menu-item-title'  => sanitize_text_field((string) ($item['title'] ?? 'Item')),
                    'menu-item-url'    => esc_url_raw((string) ($item['url'] ?? home_url('/'))),
                    'menu-item-status' => 'publish',
                ]);
            }

            $locations[$location] = $menu_id;
        }

        set_theme_mod('nav_menu_locations', $locations);
        $result['steps'][] = 'menus_imported';
    }

    private function set_homepage(int $page_id, array &$rollback, array &$result): void {
        foreach (['show_on_front', 'page_on_front'] as $option_name) {
            if (!array_key_exists($option_name, $rollback['updated_options'])) {
                $rollback['updated_options'][$option_name] = [
                    'exists' => get_option($option_name, '__lcfa_missing__') !== '__lcfa_missing__',
                    'value'  => get_option($option_name),
                ];
            }
        }

        update_option('show_on_front', 'page', false);
        update_option('page_on_front', $page_id, false);
        $result['steps'][] = 'homepage_assigned';
    }

    private function read_json_file(string $base_dir, string $relative_path): array {
        $path = $this->safe_join($base_dir, $relative_path);
        if ($path === '' || !is_readable($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function safe_join(string $base_dir, string $relative_path): string {
        $relative_path = $this->validator->normalize_relative_path($relative_path);
        if ($relative_path === '') {
            return '';
        }

        $base = realpath($base_dir);
        $path = realpath(trailingslashit($base_dir) . $relative_path);
        if (!$base || !$path || strpos($path, $base) !== 0) {
            return '';
        }

        return $path;
    }

    private function sideload_local_file(string $source_path, array $metadata): int {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = wp_tempnam(basename($source_path));
        if (!$tmp || !copy($source_path, $tmp)) {
            return 0;
        }

        $file = [
            'name'     => basename($source_path),
            'tmp_name' => $tmp,
        ];

        $attachment_id = media_handle_sideload($file, 0, (string) ($metadata['title'] ?? ''));
        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            return 0;
        }

        if (!empty($metadata['alt'])) {
            update_post_meta((int) $attachment_id, '_wp_attachment_image_alt', (string) $metadata['alt']);
        }

        if (!empty($metadata['caption'])) {
            wp_update_post([
                'ID'           => (int) $attachment_id,
                'post_excerpt' => (string) $metadata['caption'],
            ]);
        }

        return (int) $attachment_id;
    }

    private function find_attachment(string $theme_slug, string $asset_id, string $checksum): int {
        $posts = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_lcfa_theme_library_slug',
                    'value' => $theme_slug,
                ],
                [
                    'key'   => '_lcfa_theme_library_asset_id',
                    'value' => $asset_id,
                ],
                [
                    'key'   => '_lcfa_theme_library_checksum',
                    'value' => $checksum,
                ],
            ],
        ]);

        return isset($posts[0]) ? (int) $posts[0] : 0;
    }

    private function find_imported_post(string $post_type, string $theme_slug, string $part): int {
        $posts = get_posts([
            'post_type'      => $post_type,
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_lcfa_theme_library_slug',
                    'value' => $theme_slug,
                ],
                [
                    'key'   => '_lcfa_theme_library_part',
                    'value' => $part,
                ],
            ],
        ]);

        return isset($posts[0]) ? (int) $posts[0] : 0;
    }

    private function record_post_rollback(int $post_id, array &$rollback): void {
        if (isset($rollback['updated_posts'][$post_id])) {
            return;
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return;
        }

        $rollback['updated_posts'][$post_id] = [
            'ID'           => $post_id,
            'post_title'   => $post->post_title,
            'post_name'    => $post->post_name,
            'post_status'  => $post->post_status,
            'post_content' => (string) get_post_field('post_content', $post_id, 'raw'),
            'meta'         => [
                '_lc_livecanvas_enabled' => get_post_meta($post_id, '_lc_livecanvas_enabled', true),
                '_wp_page_template'      => get_post_meta($post_id, '_wp_page_template', true),
                'is_header'              => get_post_meta($post_id, 'is_header', true),
                'is_footer'              => get_post_meta($post_id, 'is_footer', true),
                '_lcfa_theme_library_slug' => get_post_meta($post_id, '_lcfa_theme_library_slug', true),
                '_lcfa_theme_library_version' => get_post_meta($post_id, '_lcfa_theme_library_version', true),
                '_lcfa_theme_library_import_id' => get_post_meta($post_id, '_lcfa_theme_library_import_id', true),
                '_lcfa_theme_library_part' => get_post_meta($post_id, '_lcfa_theme_library_part', true),
            ],
        ];
    }

    private function delete_file(string $path): void {
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    private function delete_directory(string $path): void {
        if ($path === '' || !is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($child)) {
                $this->delete_directory($child);
            } else {
                @unlink($child);
            }
        }

        @rmdir($path);
    }
}
