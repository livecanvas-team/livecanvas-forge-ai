<?php

defined('ABSPATH') || exit;

final class LCFA_Framework_Prerequisites {
    private const DEFAULT_MINIMUM_PHP = [
        'picowind' => '8.2.0',
    ];

    private string $runtime_php_version;

    public function __construct(?string $runtime_php_version = null) {
        $this->runtime_php_version = $this->normalize_version($runtime_php_version ?: PHP_VERSION) ?: PHP_VERSION;
    }

    public function check(string $framework, $declared_requirement = ''): array {
        $framework = $this->normalize_framework($framework);
        $framework_minimum = self::DEFAULT_MINIMUM_PHP[$framework] ?? '';
        if (function_exists('apply_filters')) {
            $framework_minimum = (string) apply_filters(
                'lcfa_framework_minimum_php_version',
                $framework_minimum,
                $framework
            );
        }

        $framework_minimum = $this->normalize_requirement($framework_minimum);
        $declared_minimum = $this->normalize_requirement($declared_requirement);
        $required_php = $this->highest_version($framework_minimum, $declared_minimum);
        $ready = $required_php === '' || version_compare($this->runtime_php_version, $required_php, '>=');

        $framework_label = $framework === 'picowind' ? 'Picowind' : ucfirst($framework ?: 'Theme');
        $current_label = $this->display_version($this->runtime_php_version);
        $required_label = $this->display_version($required_php);

        return [
            'ok'               => $ready,
            'ready'            => $ready,
            'status'           => $ready ? 'ready' : 'php_upgrade_required',
            'framework'        => $framework,
            'current_php'      => $this->runtime_php_version,
            'required_php'     => $required_php,
            'declared_php'     => $declared_minimum,
            'framework_php'    => $framework_minimum,
            'message'          => $ready
                ? sprintf(
                    /* translators: 1: current PHP version, 2: framework name. */
                    __('PHP %1$s is compatible with %2$s.', 'livecanvas-forge-ai'),
                    $current_label,
                    $framework_label
                )
                : sprintf(
                    /* translators: 1: framework name, 2: required PHP version, 3: current PHP version. */
                    __('%1$s requires PHP %2$s or newer. This server uses PHP %3$s. Upgrade PHP in your hosting control panel before installing or activating this theme.', 'livecanvas-forge-ai'),
                    $framework_label,
                    $required_label,
                    $current_label
                ),
        ];
    }

    public function check_windpress(string $framework, bool $installed, bool $active): array {
        $framework = $this->normalize_framework($framework);

        if ($framework === 'picowind') {
            if ($active) {
                return [
                    'status'    => 'ready',
                    'framework' => $framework,
                    'required'  => true,
                    'installed' => $installed,
                    'active'    => true,
                    'action'    => 'none',
                    'message'   => __('WindPress is active and available for the Picowind Tailwind build.', 'livecanvas-forge-ai'),
                ];
            }

            return [
                'status'    => $installed ? 'activation_required' : 'installation_required',
                'framework' => $framework,
                'required'  => true,
                'installed' => $installed,
                'active'    => false,
                'action'    => $installed ? 'activate' : 'install',
                'message'   => $installed
                    ? __('Picowind requires WindPress. Activate it before compiling Tailwind or DaisyUI styles.', 'livecanvas-forge-ai')
                    : __('Picowind requires WindPress. Install and activate it before compiling Tailwind or DaisyUI styles.', 'livecanvas-forge-ai'),
            ];
        }

        if ($framework === 'picostrap') {
            return [
                'status'    => $active ? 'deactivation_recommended' : 'not_required',
                'framework' => $framework,
                'required'  => false,
                'installed' => $installed,
                'active'    => $active,
                'action'    => $active ? 'deactivate' : 'none',
                'message'   => $active
                    ? __('WindPress is not used by the active Picostrap theme. Deactivate it to keep this Bootstrap stack focused.', 'livecanvas-forge-ai')
                    : __('WindPress is not required by Picostrap or Bootstrap themes.', 'livecanvas-forge-ai'),
            ];
        }

        return [
            'status'    => 'framework_unknown',
            'framework' => $framework,
            'required'  => false,
            'installed' => $installed,
            'active'    => $active,
            'action'    => 'none',
            'message'   => __('Choose Picostrap or Picowind before changing the WindPress runtime.', 'livecanvas-forge-ai'),
        ];
    }

    private function normalize_framework(string $framework): string {
        return strtolower((string) preg_replace('/[^a-z0-9_-]/i', '', trim($framework)));
    }

    private function normalize_requirement($requirement): string {
        if (is_array($requirement)) {
            foreach (['php', 'requires_php', 'minimum_php'] as $key) {
                if (isset($requirement[$key])) {
                    $requirement = $requirement[$key];
                    break;
                }
            }
        }

        if (!is_scalar($requirement)) {
            return '';
        }

        $requirement = trim((string) $requirement);
        if (!preg_match('/^(?:>=\s*)?(\d+(?:\.\d+){0,2})$/', $requirement, $matches)) {
            return '';
        }

        return $this->normalize_version($matches[1]);
    }

    private function normalize_version(string $version): string {
        if (!preg_match('/^(\d+)(?:\.(\d+))?(?:\.(\d+))?/', trim($version), $matches)) {
            return '';
        }

        return implode('.', [
            (string) ((int) $matches[1]),
            (string) ((int) ($matches[2] ?? 0)),
            (string) ((int) ($matches[3] ?? 0)),
        ]);
    }

    private function highest_version(string $first, string $second): string {
        if ($first === '') {
            return $second;
        }
        if ($second === '') {
            return $first;
        }

        return version_compare($first, $second, '>=') ? $first : $second;
    }

    private function display_version(string $version): string {
        $parts = explode('.', $version);
        while (count($parts) > 2 && end($parts) === '0') {
            array_pop($parts);
        }

        return implode('.', $parts);
    }
}
