<?php

defined('ABSPATH') || exit;

final class LCFA_Stack_Capabilities {
    private const SCHEMA_VERSION = 'stack-capabilities.v1';
    private const PROFILE_VERSION = '2026.08.1';

    public function evaluate(array $snapshot, ?array $runtime = null): array {
        $framework = sanitize_key((string) ($snapshot['detected_framework'] ?? 'unknown'));
        $runtime = $runtime ?? $this->detect_runtime_capabilities($snapshot);
        $profiles = $this->get_profiles();
        $components = [];

        $components['wordpress'] = $this->evaluate_component(
            'wordpress',
            true,
            true,
            true,
            (string) ($snapshot['wordpress_version'] ?? ''),
            (array) ($runtime['wordpress'] ?? []),
            ['rest_api'],
            ['abilities_api'],
            $profiles
        );
        $components['livecanvas'] = $this->evaluate_component(
            'livecanvas',
            true,
            !empty($snapshot['livecanvas_installed']),
            !empty($snapshot['livecanvas_active']),
            (string) ($snapshot['livecanvas_version'] ?? ''),
            (array) ($runtime['livecanvas'] ?? []),
            ['page_detection', 'framework_api'],
            ['partial_storage', 'license_api'],
            $profiles
        );

        if ($framework === 'picostrap') {
            $components['picostrap'] = $this->evaluate_component(
                'picostrap',
                true,
                true,
                true,
                (string) ($snapshot['framework_version'] ?? ''),
                (array) ($runtime['picostrap'] ?? []),
                ['livecanvas_config', 'sass_source'],
                ['bundle_helpers', 'child_theme_writable'],
                $profiles
            );
            $components['picowind'] = $this->not_applicable_component('picowind', $profiles);
            $components['windpress'] = $this->evaluate_component(
                'windpress',
                false,
                !empty($snapshot['windpress_installed']),
                !empty($snapshot['windpress_active']),
                (string) ($snapshot['windpress_version'] ?? ''),
                (array) ($runtime['windpress'] ?? []),
                [],
                ['cache_api', 'volume_api', 'config_api'],
                $profiles
            );
        } elseif ($framework === 'picowind') {
            $components['picostrap'] = $this->not_applicable_component('picostrap', $profiles);
            $components['picowind'] = $this->evaluate_component(
                'picowind',
                true,
                true,
                true,
                (string) ($snapshot['framework_version'] ?? ''),
                (array) ($runtime['picowind'] ?? []),
                ['livecanvas_config', 'tailwind_entrypoint'],
                ['child_theme_writable'],
                $profiles
            );
            $components['windpress'] = $this->evaluate_component(
                'windpress',
                true,
                !empty($snapshot['windpress_installed']),
                !empty($snapshot['windpress_active']),
                (string) ($snapshot['windpress_version'] ?? ''),
                (array) ($runtime['windpress'] ?? []),
                ['cache_api', 'volume_api', 'config_api'],
                ['runtime_version_api', 'cache_flush_api'],
                $profiles
            );
        } else {
            $components['picostrap'] = $this->not_applicable_component('picostrap', $profiles);
            $components['picowind'] = $this->not_applicable_component('picowind', $profiles);
            $components['windpress'] = $this->evaluate_component(
                'windpress',
                false,
                !empty($snapshot['windpress_installed']),
                !empty($snapshot['windpress_active']),
                (string) ($snapshot['windpress_version'] ?? ''),
                (array) ($runtime['windpress'] ?? []),
                [],
                ['cache_api', 'volume_api', 'config_api'],
                $profiles
            );
            $components['framework'] = [
                'key'                  => 'framework',
                'required'             => true,
                'installed'            => false,
                'active'               => false,
                'version'              => '',
                'status'               => 'unsupported',
                'version_status'       => 'unknown',
                'tested_range'         => '',
                'capabilities'         => [],
                'missing_required'     => ['recognized_framework'],
                'missing_optional'     => [],
                'message'              => __('No supported Picostrap or Picowind framework was detected.', 'livecanvas-forge-ai'),
            ];
        }

        $required_components = array_filter($components, static function (array $component): bool {
            return !empty($component['required']);
        });
        $statuses = array_column($required_components, 'status');
        $status = in_array('unsupported', $statuses, true)
            ? 'unsupported'
            : (in_array('degraded', $statuses, true) ? 'degraded' : 'supported');
        $missing = [];
        $warnings = [];

        foreach ($required_components as $key => $component) {
            foreach ((array) ($component['missing_required'] ?? []) as $capability) {
                $missing[] = $key . ':' . $capability;
            }
            if (($component['status'] ?? '') === 'degraded') {
                $warnings[] = (string) ($component['message'] ?? '');
            }
        }

        $messages = [
            'supported'   => __('The detected LiveCanvas stack is inside tested version ranges and exposes the required APIs.', 'livecanvas-forge-ai'),
            'degraded'    => __('The stack is usable, but one or more versions or optional APIs are outside the tested profile.', 'livecanvas-forge-ai'),
            'unsupported' => __('The stack is missing a required component or API for reliable AI Bridge operations.', 'livecanvas-forge-ai'),
        ];

        return [
            'schema_version'       => self::SCHEMA_VERSION,
            'profile_version'      => self::PROFILE_VERSION,
            'status'               => $status,
            'message'              => $messages[$status],
            'framework'            => $framework,
            'components'           => $components,
            'missing_capabilities' => array_values(array_unique($missing)),
            'warnings'             => array_values(array_filter(array_unique($warnings))),
        ];
    }

    public function get_profiles(): array {
        $profiles = [
            'wordpress' => [
                'operational_min' => '6.7.0',
                'tested_min'      => '7.0.0',
                'tested_max'      => '8.0.0',
            ],
            'livecanvas' => [
                'operational_min' => '4.0.0',
                'tested_min'      => '4.9.0',
                'tested_max'      => '5.0.0',
            ],
            'picostrap' => [
                'operational_min' => '3.0.0',
                'tested_min'      => '3.8.0',
                'tested_max'      => '4.0.0',
            ],
            'picowind' => [
                'operational_min' => '0.0.10',
                'tested_min'      => '0.0.14',
                'tested_max'      => '1.0.0',
            ],
            'windpress' => [
                'operational_min' => '3.0.0',
                'tested_min'      => '3.2.0',
                'tested_max'      => '4.0.0',
            ],
        ];

        return (array) apply_filters('lcfa_stack_capability_profiles', $profiles);
    }

    private function evaluate_component(string $key, bool $required, bool $installed, bool $active, string $version, array $capabilities, array $required_capabilities, array $optional_capabilities, array $profiles): array {
        $profile = is_array($profiles[$key] ?? null) ? $profiles[$key] : [];
        $capabilities = array_map('boolval', $capabilities);
        $missing_required = array_values(array_filter($required_capabilities, static function (string $capability) use ($capabilities): bool {
            return empty($capabilities[$capability]);
        }));
        $missing_optional = array_values(array_filter($optional_capabilities, static function (string $capability) use ($capabilities): bool {
            return empty($capabilities[$capability]);
        }));
        $version_status = $this->evaluate_version($version, $profile);

        if (!$installed || !$active) {
            $status = $required ? 'unsupported' : 'not_applicable';
        } elseif ($missing_required || $version_status === 'unsupported') {
            $status = 'unsupported';
        } elseif ($version_status !== 'supported' || $missing_optional) {
            $status = 'degraded';
        } else {
            $status = 'supported';
        }

        if ($status === 'supported') {
            $message = sprintf(__('%s is inside the tested profile.', 'livecanvas-forge-ai'), ucfirst($key));
        } elseif ($status === 'not_applicable') {
            $message = sprintf(__('%s is not required for the active framework.', 'livecanvas-forge-ai'), ucfirst($key));
        } elseif (!$installed || !$active) {
            $message = sprintf(__('%s is required but is not installed and active.', 'livecanvas-forge-ai'), ucfirst($key));
        } elseif ($missing_required) {
            $message = sprintf(__('%1$s is missing required APIs: %2$s.', 'livecanvas-forge-ai'), ucfirst($key), implode(', ', $missing_required));
        } else {
            $message = sprintf(__('%s is available outside the fully tested capability profile.', 'livecanvas-forge-ai'), ucfirst($key));
        }

        return [
            'key'              => $key,
            'required'         => $required,
            'installed'        => $installed,
            'active'           => $active,
            'version'          => $version,
            'status'           => $status,
            'version_status'   => $version_status,
            'tested_range'     => $this->format_tested_range($profile),
            'capabilities'     => $capabilities,
            'missing_required' => $missing_required,
            'missing_optional' => $missing_optional,
            'message'          => $message,
        ];
    }

    private function not_applicable_component(string $key, array $profiles): array {
        return [
            'key'              => $key,
            'required'         => false,
            'installed'        => false,
            'active'           => false,
            'version'          => '',
            'status'           => 'not_applicable',
            'version_status'   => 'unknown',
            'tested_range'     => $this->format_tested_range((array) ($profiles[$key] ?? [])),
            'capabilities'     => [],
            'missing_required' => [],
            'missing_optional' => [],
            'message'          => sprintf(__('%s is not required for the active framework.', 'livecanvas-forge-ai'), ucfirst($key)),
        ];
    }

    private function evaluate_version(string $version, array $profile): string {
        $version = trim($version);
        if ($version === '') {
            return 'unknown';
        }

        $operational_min = (string) ($profile['operational_min'] ?? '');
        $tested_min = (string) ($profile['tested_min'] ?? '');
        $tested_max = (string) ($profile['tested_max'] ?? '');

        if ($operational_min !== '' && version_compare($version, $operational_min, '<')) {
            return 'unsupported';
        }

        if (
            ($tested_min === '' || version_compare($version, $tested_min, '>='))
            && ($tested_max === '' || version_compare($version, $tested_max, '<'))
        ) {
            return 'supported';
        }

        return 'untested';
    }

    private function format_tested_range(array $profile): string {
        $min = (string) ($profile['tested_min'] ?? '');
        $max = (string) ($profile['tested_max'] ?? '');

        if ($min === '' && $max === '') {
            return '';
        }

        return ($min !== '' ? '>=' . $min : '') . ($max !== '' ? ' <' . $max : '');
    }

    private function detect_runtime_capabilities(array $snapshot): array {
        $stylesheet_directory = function_exists('get_stylesheet_directory') ? (string) get_stylesheet_directory() : '';
        $template_directory = function_exists('get_template_directory') ? (string) get_template_directory() : '';
        $theme_directories = array_values(array_unique(array_filter([$stylesheet_directory, $template_directory])));
        $has_file = static function (string $relative_path) use ($theme_directories): bool {
            foreach ($theme_directories as $directory) {
                if (is_readable(trailingslashit($directory) . ltrim($relative_path, '/'))) {
                    return true;
                }
            }

            return false;
        };
        $class_has_methods = static function (string $class, array $methods): bool {
            if (!class_exists($class)) {
                return false;
            }
            foreach ($methods as $method) {
                if (!is_callable([$class, $method])) {
                    return false;
                }
            }

            return true;
        };

        return [
            'wordpress' => [
                'rest_api'      => function_exists('register_rest_route'),
                'abilities_api' => function_exists('wp_register_ability'),
            ],
            'livecanvas' => [
                'page_detection'  => function_exists('lc_post_is_using_livecanvas'),
                'framework_api'   => function_exists('lc_get_framework_slug') || function_exists('lc_define_editor_config'),
                'partial_storage' => function_exists('post_type_exists') && post_type_exists('lc_partial'),
                'license_api'     => function_exists('lc_get_apikey') || function_exists('get_site_option'),
            ],
            'picostrap' => [
                'livecanvas_config'   => $has_file('livecanvas/configuration.php'),
                'sass_source'         => function_exists('ps_get_main_sass') || $has_file('inc/picosass-compiler-integration.php'),
                'bundle_helpers'      => function_exists('picostrap_get_complete_css_filename') || $has_file('inc/enqueues.php'),
                'child_theme_writable'=> $stylesheet_directory !== '' && is_writable($stylesheet_directory),
            ],
            'picowind' => [
                'livecanvas_config'   => $has_file('livecanvas/configuration.php'),
                'tailwind_entrypoint' => $has_file('public/styles/tailwind.css'),
                'child_theme_writable'=> $stylesheet_directory !== '' && is_writable($stylesheet_directory),
            ],
            'windpress' => [
                'cache_api' => $class_has_methods('WindPress\\WindPress\\Core\\Cache', ['get_providers', 'save_cache', 'save_theme_json', 'get_cache_path']),
                'volume_api' => $class_has_methods('WindPress\\WindPress\\Core\\Volume', ['get_entries', 'get_available_handlers', 'save_entries']),
                'config_api' => $class_has_methods('WindPress\\WindPress\\Utils\\Config', ['get', 'set']),
                'runtime_version_api' => $class_has_methods('WindPress\\WindPress\\Core\\Runtime', ['tailwindcss_version']),
                'cache_flush_api' => $class_has_methods('WindPress\\WindPress\\Utils\\Cache', ['flush_cache_plugin']),
            ],
        ];
    }
}
