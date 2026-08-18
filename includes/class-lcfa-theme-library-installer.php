<?php

defined('ABSPATH') || exit;

if (!class_exists('LCFA_Framework_Prerequisites', false)) {
    require_once __DIR__ . '/class-lcfa-framework-prerequisites.php';
}

final class LCFA_Theme_Library_Installer {
    private const PENDING_INSTALLS_OPTION = 'lcfa_theme_library_pending_installs';
    private const PENDING_INSTALL_RETENTION_SECONDS = 604800;

    private LCFA_Theme_Library_Validator $validator;
    private ?LCFA_WindPress_Bridge $windpress_bridge;
    private LCFA_Framework_Prerequisites $framework_prerequisites;

    public function __construct(LCFA_Theme_Library_Validator $validator, ?LCFA_WindPress_Bridge $windpress_bridge = null, ?LCFA_Framework_Prerequisites $framework_prerequisites = null) {
        $this->validator = $validator;
        $this->windpress_bridge = $windpress_bridge;
        $this->framework_prerequisites = $framework_prerequisites ?: new LCFA_Framework_Prerequisites();
    }

    public function preview(array $theme): array {
        $download = $this->download_theme_zip($theme);
        if (empty($download['ok'])) {
            return $download;
        }

        $validation = $this->validator->validate_zip((string) $download['zip_path'], $theme);
        $this->delete_file((string) $download['zip_path']);

        if (empty($validation['ok'])) {
            return $validation;
        }

        return [
            'ok'           => true,
            'theme'        => $theme,
            'checksum'     => (string) ($validation['checksum'] ?? ''),
            'preview_plan' => $validation['preview_plan'] ?? [],
            'manifest'     => $validation['manifest'] ?? [],
            'prerequisites'=> $this->get_prerequisites($theme, (array) ($validation['manifest'] ?? []), (string) ($validation['requires_php'] ?? '')),
        ];
    }

    public function install(array $theme): array {
        $this->cleanup_stale_pending_install_states();

        $prerequisites = $this->get_prerequisites($theme);
        if (empty($prerequisites['ready'])) {
            return $this->prerequisite_error($prerequisites);
        }

        $download = $this->download_theme_zip($theme);
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
        $prerequisites = $this->get_prerequisites($theme, $manifest, (string) ($validation['requires_php'] ?? ''));
        if (empty($prerequisites['ready'])) {
            $this->delete_file($zip_path);
            return $this->prerequisite_error($prerequisites);
        }
        $stylesheet = sanitize_key((string) ($manifest['theme']['stylesheet'] ?? $manifest['theme']['slug'] ?? $theme['slug'] ?? ''));

        $overwrite_existing = false;
        if ($stylesheet !== '') {
            $installed_stylesheet = $this->resolve_installed_stylesheet($stylesheet, $manifest, $theme);
            $existing_theme = wp_get_theme($installed_stylesheet);
            if ($existing_theme->exists()) {
                $installed_version = sanitize_text_field((string) $existing_theme->get('Version'));
                $target_version = sanitize_text_field((string) ($manifest['theme']['version'] ?? $theme['version'] ?? ''));
                $overwrite_existing = $installed_version !== ''
                    && $target_version !== ''
                    && version_compare($target_version, $installed_version, '>');

                if (!$overwrite_existing) {
                    $this->delete_file($zip_path);
                    $activation = $this->activate_theme_with_rollback(
                        $installed_stylesheet,
                        $manifest,
                        $theme,
                        (string) ($validation['checksum'] ?? '')
                    );
                    if (empty($activation['ok'])) {
                        return $activation;
                    }

                    return [
                        'ok'               => true,
                        'status'           => 'already_installed',
                        'message'          => __('Theme Library child theme was already installed and has been activated.', 'livecanvas-forge-ai'),
                        'theme'            => $theme,
                        'manifest'         => $manifest,
                        'theme_stylesheet' => $installed_stylesheet,
                    ];
                }
            }
        }

        $this->load_upgrader_dependencies();
        $upgrader = new Theme_Upgrader(new Automatic_Upgrader_Skin());
        $result = $upgrader->install($zip_path, [
            'clear_update_cache' => true,
            'overwrite_package'  => $overwrite_existing,
        ]);
        $this->delete_file($zip_path);

        if (is_wp_error($result)) {
            return [
                'ok'      => false,
                'message' => $result->get_error_message(),
            ];
        }

        if (!$result && $stylesheet === '') {
            return [
                'ok'      => false,
                'message' => __('Theme installation failed.', 'livecanvas-forge-ai'),
            ];
        }

        $installed_stylesheet = $stylesheet !== '' ? $this->resolve_installed_stylesheet($stylesheet, $manifest, $theme) : '';
        if ($installed_stylesheet !== '') {
            $theme_object = wp_get_theme($installed_stylesheet);
            if ($theme_object->exists()) {
                $activation = $this->activate_theme_with_rollback(
                    $installed_stylesheet,
                    $manifest,
                    $theme,
                    (string) ($validation['checksum'] ?? '')
                );
                if (empty($activation['ok'])) {
                    return $activation;
                }
            }
        }

        return [
            'ok'              => true,
            'status'          => $overwrite_existing ? 'updated' : 'installed',
            'message'         => $overwrite_existing
                ? __('Theme Library child theme updated and kept active.', 'livecanvas-forge-ai')
                : __('Theme Library child theme installed and activated.', 'livecanvas-forge-ai'),
            'theme'           => $theme,
            'manifest'        => $manifest,
            'theme_stylesheet'=> $installed_stylesheet !== '' ? $installed_stylesheet : $stylesheet,
        ];
    }

    public function get_prerequisites(array $theme = [], array $manifest = [], string $style_requires_php = ''): array {
        $framework = sanitize_key((string) ($theme['framework'] ?? ($theme['stack']['framework'] ?? 'picowind')));
        if ($framework === '') {
            $framework = 'picowind';
        }

        $compatibility = is_array($manifest['compatibility'] ?? null) ? $manifest['compatibility'] : [];
        $declared_requirement = $compatibility['php'] ?? $compatibility['requires_php'] ?? $theme['requires_php'] ?? '';
        if ($style_requires_php !== '') {
            $declared_requirement = $this->higher_requirement((string) $declared_requirement, $style_requires_php);
        }

        return $this->framework_prerequisites->check($framework, $declared_requirement);
    }

    public function get_pending_install_state(string $stylesheet, string $theme_slug = ''): array {
        $this->cleanup_stale_pending_install_states();

        $pending = get_option(self::PENDING_INSTALLS_OPTION, []);
        if (!is_array($pending)) {
            return [];
        }

        $stylesheet = sanitize_key($stylesheet);
        if ($stylesheet !== '' && is_array($pending[$stylesheet] ?? null)) {
            return $pending[$stylesheet];
        }

        $theme_slug = sanitize_key($theme_slug);
        if ($theme_slug === '') {
            return [];
        }

        foreach ($pending as $state) {
            if (is_array($state) && sanitize_key((string) ($state['theme_slug'] ?? '')) === $theme_slug) {
                return $state;
            }
        }

        return [];
    }

    public function consume_pending_install_state(string $stylesheet, string $theme_slug = ''): array {
        $pending = get_option(self::PENDING_INSTALLS_OPTION, []);
        if (!is_array($pending)) {
            return [];
        }

        $stylesheet = sanitize_key($stylesheet);
        $theme_slug = sanitize_key($theme_slug);
        $matched_key = '';

        if ($stylesheet !== '' && is_array($pending[$stylesheet] ?? null)) {
            $matched_key = $stylesheet;
        } elseif ($theme_slug !== '') {
            foreach ($pending as $key => $state) {
                if (is_array($state) && sanitize_key((string) ($state['theme_slug'] ?? '')) === $theme_slug) {
                    $matched_key = (string) $key;
                    break;
                }
            }
        }

        if ($matched_key === '' || !is_array($pending[$matched_key] ?? null)) {
            return [];
        }

        $state = $pending[$matched_key];
        unset($pending[$matched_key]);
        if ($pending) {
            update_option(self::PENDING_INSTALLS_OPTION, $pending, false);
        } else {
            delete_option(self::PENDING_INSTALLS_OPTION);
        }

        return $state;
    }

    /**
     * Remove abandoned install handoffs without invalidating the active theme.
     * Runtime backups are deleted only after their pending record is no longer usable.
     */
    public function cleanup_stale_pending_install_states(int $max_age_seconds = self::PENDING_INSTALL_RETENTION_SECONDS, ?int $now = null): array {
        $pending = get_option(self::PENDING_INSTALLS_OPTION, []);
        if (!is_array($pending) || !$pending) {
            return [
                'ok'       => true,
                'removed'  => 0,
                'retained' => 0,
                'errors'   => [],
            ];
        }

        $max_age_seconds = max(3600, $max_age_seconds);
        $now = $now ?? time();
        $active_stylesheet = sanitize_key((string) wp_get_theme()->get_stylesheet());
        $retained = [];
        $removed = [];
        $errors = [];

        foreach ($pending as $key => $state) {
            $key = sanitize_key((string) $key);
            if ($key === '' || !is_array($state)) {
                $removed[] = $key;
                continue;
            }

            $stylesheet = sanitize_key((string) ($state['stylesheet'] ?? $key));
            $captured_at = trim((string) ($state['captured_at'] ?? ''));
            $captured_timestamp = $captured_at !== '' ? strtotime($captured_at . ' UTC') : false;
            $is_stale = $captured_timestamp === false || ($now - $captured_timestamp) > $max_age_seconds;

            if (!$is_stale || ($stylesheet !== '' && $stylesheet === $active_stylesheet)) {
                $retained[$key] = $state;
                continue;
            }

            $runtime_state = is_array($state['windpress_runtime'] ?? null) ? $state['windpress_runtime'] : [];
            if ($runtime_state && $this->windpress_bridge && method_exists($this->windpress_bridge, 'delete_runtime_backup')) {
                $cleanup = $this->windpress_bridge->delete_runtime_backup($runtime_state);
                if (empty($cleanup['ok'])) {
                    $errors[] = (string) ($cleanup['message'] ?? __('A stale WindPress runtime backup could not be removed.', 'livecanvas-forge-ai'));
                    $retained[$key] = $state;
                    continue;
                }
            }

            $removed[] = $key;
        }

        if ($retained) {
            update_option(self::PENDING_INSTALLS_OPTION, $retained, false);
        } else {
            delete_option(self::PENDING_INSTALLS_OPTION);
        }

        return [
            'ok'       => empty($errors),
            'removed'  => count($removed),
            'retained' => count($retained),
            'keys'     => array_values(array_filter($removed)),
            'errors'   => $errors,
            'message'  => empty($errors)
                ? __('Stale Theme Library install handoffs cleaned.', 'livecanvas-forge-ai')
                : __('Some stale Theme Library install handoffs could not be cleaned.', 'livecanvas-forge-ai'),
        ];
    }

    public function download_theme_zip(array $theme): array {
        $local_path = $this->resolve_local_package_path((string) ($theme['package_path'] ?? ''));
        if ($local_path !== '') {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $tmp = wp_tempnam(basename($local_path));
            if ($tmp && copy($local_path, $tmp)) {
                return [
                    'ok'       => true,
                    'zip_path' => (string) $tmp,
                    'source'   => 'local_package_path',
                ];
            }

            return [
                'ok'      => false,
                'message' => __('Local Theme Library package could not be copied to a temporary file.', 'livecanvas-forge-ai'),
            ];
        }

        $url = esc_url_raw((string) ($theme['package_url'] ?? ''));
        if ($url === '') {
            return [
                'ok'      => false,
                'message' => __('Theme package URL is missing.', 'livecanvas-forge-ai'),
            ];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $zip_path = download_url($url, 30);
        if (is_wp_error($zip_path)) {
            return [
                'ok'      => false,
                'message' => $zip_path->get_error_message(),
            ];
        }

        return [
            'ok'       => true,
            'zip_path' => (string) $zip_path,
        ];
    }

    private function resolve_local_package_path(string $relative_path): string {
        if ($relative_path === '' || !defined('LCFA_DIR')) {
            return '';
        }

        $relative_path = str_replace('\\', '/', trim($relative_path));
        $relative_path = ltrim($relative_path, '/');
        if ($relative_path === '' || strpos($relative_path, '..') !== false) {
            return '';
        }

        $base = realpath(LCFA_DIR);
        $path = realpath(trailingslashit(LCFA_DIR) . $relative_path);
        if (!$base || !$path || strpos($path, $base) !== 0 || !is_readable($path)) {
            return '';
        }

        return $path;
    }

    private function load_upgrader_dependencies(): void {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';
    }

    private function resolve_installed_stylesheet(string $stylesheet, array $manifest, array $theme): string {
        if ($stylesheet !== '' && wp_get_theme($stylesheet)->exists()) {
            return $stylesheet;
        }

        $expected_name = sanitize_text_field((string) ($manifest['theme']['name'] ?? $theme['name'] ?? ''));
        $expected_slug = sanitize_key((string) ($manifest['theme']['slug'] ?? $theme['slug'] ?? $stylesheet));
        $expected_text_domain = sanitize_key((string) ($manifest['theme']['text_domain'] ?? $expected_slug));
        $expected_parent = $this->get_expected_parent($manifest, $theme);

        foreach (wp_get_themes() as $candidate_stylesheet => $candidate) {
            if (!$candidate->exists()) {
                continue;
            }

            $candidate_name = sanitize_text_field((string) $candidate->get('Name'));
            $candidate_text_domain = sanitize_key((string) $candidate->get('TextDomain'));
            $candidate_template = sanitize_key((string) $candidate->get_template());
            $candidate_key = sanitize_key((string) $candidate_stylesheet);

            if ($expected_name !== '' && strcasecmp($candidate_name, $expected_name) === 0 && $candidate_template === $expected_parent) {
                return (string) $candidate_stylesheet;
            }

            if ($expected_text_domain !== '' && $candidate_text_domain === $expected_text_domain) {
                return (string) $candidate_stylesheet;
            }

            if ($expected_slug !== '' && strpos($candidate_key, $expected_slug . '-') === 0) {
                return (string) $candidate_stylesheet;
            }
        }

        return $stylesheet;
    }

    private function get_expected_parent(array $manifest, array $theme): string {
        $parent = sanitize_key((string) ($manifest['theme']['parent'] ?? $manifest['compatibility']['parent_theme'] ?? ''));
        if ($parent !== '') {
            return $parent;
        }

        $framework = sanitize_key((string) ($manifest['theme']['framework'] ?? $theme['framework'] ?? $theme['stack']['framework'] ?? 'picowind'));

        return $framework === 'picostrap' ? 'picostrap5' : 'picowind';
    }

    private function activate_theme_with_rollback(string $stylesheet, array $manifest, array $theme, string $checksum): array {
        $prerequisites = $this->get_prerequisites($theme, $manifest);
        if (empty($prerequisites['ready'])) {
            return $this->prerequisite_error($prerequisites);
        }

        $stylesheet = sanitize_key($stylesheet);
        $current_stylesheet = sanitize_key((string) wp_get_theme()->get_stylesheet());
        if ($stylesheet === '' || $current_stylesheet === $stylesheet) {
            return [
                'ok'      => true,
                'changed' => false,
            ];
        }

        $theme_slug = sanitize_key((string) ($manifest['theme']['slug'] ?? $theme['slug'] ?? $stylesheet));
        $audit_id = sanitize_key('theme-install-' . $theme_slug . '-' . strtolower(wp_generate_password(8, false, false)));
        $runtime_state = [
            'ok'        => true,
            'available' => false,
            'audit_id'  => $audit_id,
        ];

        if ($this->windpress_bridge) {
            $runtime_state = $this->windpress_bridge->capture_runtime_state($audit_id);
            if (empty($runtime_state['ok'])) {
                return [
                    'ok'      => false,
                    'message' => (string) ($runtime_state['message'] ?? __('WindPress runtime backup failed; the child theme was not activated.', 'livecanvas-forge-ai')),
                ];
            }
        }

        $pending = get_option(self::PENDING_INSTALLS_OPTION, []);
        if (!is_array($pending)) {
            $pending = [];
        }
        $pending[$stylesheet] = [
            'theme_slug'        => $theme_slug,
            'theme_version'     => sanitize_text_field((string) ($manifest['theme']['version'] ?? $theme['version'] ?? '')),
            'stylesheet'        => $stylesheet,
            'previous_theme'    => $current_stylesheet,
            'previous_theme_mods' => [
                'nav_menu_locations' => get_theme_mod('nav_menu_locations', []),
            ],
            'checksum'          => sanitize_text_field($checksum),
            'windpress_runtime' => $runtime_state,
            'captured_at'       => current_time('mysql', true),
        ];
        $pending = array_slice($pending, -20, 20, true);
        update_option(self::PENDING_INSTALLS_OPTION, $pending, false);

        switch_theme($stylesheet);

        return [
            'ok'      => true,
            'changed' => true,
            'state'   => $pending[$stylesheet],
        ];
    }

    private function delete_file(string $path): void {
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    private function prerequisite_error(array $prerequisites): array {
        return [
            'ok'            => false,
            'status'        => 'php_upgrade_required',
            'code'          => 'lcfa_framework_php_upgrade_required',
            'message'       => (string) ($prerequisites['message'] ?? __('The server PHP version is not compatible with this theme.', 'livecanvas-forge-ai')),
            'prerequisites' => $prerequisites,
        ];
    }

    private function higher_requirement(string $first, string $second): string {
        $normalize = static function (string $value): string {
            return preg_match('/^(?:>=\s*)?(\d+(?:\.\d+){0,2})$/', trim($value), $matches)
                ? $matches[1]
                : '';
        };
        $first = $normalize($first);
        $second = $normalize($second);
        if ($first === '') {
            return $second;
        }
        if ($second === '') {
            return $first;
        }

        return version_compare($first, $second, '>=') ? $first : $second;
    }
}
