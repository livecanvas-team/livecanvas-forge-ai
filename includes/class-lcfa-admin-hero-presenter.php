<?php

defined('ABSPATH') || exit;

final class LCFA_Admin_Hero_Presenter {
    public function build(string $tab, array $snapshot, array $settings): array {
        $content = $this->get_tab_content($tab);

        return [
            'tab' => $tab,
            'title' => $content['title'],
            'subtitle' => $content['subtitle'],
            'marks' => $this->build_marks($snapshot),
            'chips' => $this->build_chips($snapshot, $settings),
            'details' => $this->build_details($snapshot, $settings),
        ];
    }

    private function get_tab_content(string $tab): array {
        $map = [
            'setup' => [
                'title' => __('Get Started', 'livecanvas-forge-ai'),
                'subtitle' => __('Check the LiveCanvas stack, confirm this project, then connect a coding agent.', 'livecanvas-forge-ai'),
            ],
            'connections' => [
                'title' => __('Connect a Coding Agent', 'livecanvas-forge-ai'),
                'subtitle' => __('Follow the highlighted action, verify the target site, and finish with a smoke test.', 'livecanvas-forge-ai'),
            ],
            'genesis' => [
                'title' => __('Build Plan', 'livecanvas-forge-ai'),
                'subtitle' => __('Describe the site once and turn it into a reusable sequence of pages and implementation tasks.', 'livecanvas-forge-ai'),
            ],
            'studio' => [
                'title' => __('Abilities & Runs', 'livecanvas-forge-ai'),
                'subtitle' => __('Inspect available tools, write exposure, readiness, and recent audited activity.', 'livecanvas-forge-ai'),
            ],
            'theme-library' => [
                'title' => __('Theme Library', 'livecanvas-forge-ai'),
                'subtitle' => __('Preview, install, and import validated LiveCanvas starter themes.', 'livecanvas-forge-ai'),
            ],
            'command' => [
                'title' => __('Command Deck', 'livecanvas-forge-ai'),
                'subtitle' => __('Preview or apply a specific operation with audit and rollback support.', 'livecanvas-forge-ai'),
            ],
        ];

        return $map[$tab] ?? $map['connections'];
    }

    private function build_marks(array $snapshot): array {
        $framework = (string) ($snapshot['detected_framework'] ?? 'unknown');
        $marks = [];

        if ($framework === 'picowind') {
            $marks[] = [
                'key' => 'picowind',
                'label' => __('Picowind', 'livecanvas-forge-ai'),
                'type' => 'icon',
                'asset' => 'wind',
                'active' => true,
            ];
        } elseif ($framework === 'picostrap') {
            $marks[] = [
                'key' => 'bootstrap',
                'label' => __('Bootstrap', 'livecanvas-forge-ai'),
                'type' => 'partner',
                'asset' => 'bootstrap',
                'active' => true,
            ];
        } else {
            $marks[] = [
                'key' => 'framework-setup',
                'label' => __('Theme setup needed', 'livecanvas-forge-ai'),
                'type' => 'icon',
                'asset' => 'layers',
                'active' => false,
            ];
        }

        if ($framework === 'picowind' && (!empty($snapshot['windpress_active']) || !empty($snapshot['windpress_installed']))) {
            $marks[] = [
                'key' => 'windpress',
                'label' => __('WindPress', 'livecanvas-forge-ai'),
                'type' => 'partner',
                'asset' => 'windpress',
                'active' => !empty($snapshot['windpress_active']),
            ];
        }

        return $marks;
    }

    private function build_chips(array $snapshot, array $settings): array {
        $chips = [];

        $chips[] = [
            'label' => __('Mode', 'livecanvas-forge-ai'),
            'value' => (string) (($settings['site_mode'] ?? '') ?: ($snapshot['site_mode'] ?? 'local')),
            'tone' => 'active',
        ];

        $theme_value = (string) (($snapshot['current_theme_stylesheet'] ?? '') ?: ($snapshot['current_theme_name'] ?? ''));
        if ($theme_value !== '') {
            $chips[] = [
                'label' => __('Theme', 'livecanvas-forge-ai'),
                'value' => $theme_value,
                'tone' => 'active',
            ];
        }

        $client_value = $this->normalize_client_value((string) ($settings['preferred_client'] ?? ''));
        if ($client_value !== '') {
            $chips[] = [
                'label' => __('Client', 'livecanvas-forge-ai'),
                'value' => $client_value,
                'tone' => 'other',
                'client' => $client_value,
            ];
        }

        $editor_value = (string) ($snapshot['framework_slug'] ?? '');
        $detected_framework = (string) ($snapshot['detected_framework'] ?? 'unknown');
        if ($editor_value !== '' && $detected_framework !== 'unknown') {
            $chips[] = [
                'label' => __('Editor', 'livecanvas-forge-ai'),
                'value' => $editor_value,
                'tone' => 'other',
            ];
        }

        $compatibility = is_array($snapshot['stack_capabilities'] ?? null) ? $snapshot['stack_capabilities'] : [];
        $compatibility_status = strtolower(trim((string) ($compatibility['status'] ?? '')));
        if (in_array($compatibility_status, ['supported', 'degraded', 'unsupported'], true)) {
            $chips[] = [
                'label' => __('Compatibility', 'livecanvas-forge-ai'),
                'value' => ucfirst($compatibility_status),
                'tone' => $compatibility_status === 'supported' ? 'active' : 'other',
            ];
        }

        return $chips;
    }

    private function build_details(array $snapshot, array $settings): array {
        $details = [];

        $details[] = [
            'label' => __('Theme template', 'livecanvas-forge-ai'),
            'value' => (string) ($snapshot['current_theme_template'] ?? 'n/a'),
        ];
        $details[] = [
            'label' => __('ACF', 'livecanvas-forge-ai'),
            'value' => !empty($snapshot['acf_active']) ? __('Detected', 'livecanvas-forge-ai') : __('Not detected', 'livecanvas-forge-ai'),
        ];
        $details[] = [
            'label' => __('Tangible', 'livecanvas-forge-ai'),
            'value' => !empty($snapshot['tangible_available']) ? __('Available', 'livecanvas-forge-ai') : __('Unavailable', 'livecanvas-forge-ai'),
        ];

        $framework = (string) ($snapshot['detected_framework'] ?? '');
        if ($framework !== '') {
            $details[] = [
                'label' => __('Framework', 'livecanvas-forge-ai'),
                'value' => $framework,
            ];
        }

        $compatibility = is_array($snapshot['stack_capabilities'] ?? null) ? $snapshot['stack_capabilities'] : [];
        if (!empty($compatibility['profile_version'])) {
            $details[] = [
                'label' => __('Compatibility profile', 'livecanvas-forge-ai'),
                'value' => (string) $compatibility['profile_version'],
            ];
        }
        if (!empty($compatibility['missing_capabilities'])) {
            $details[] = [
                'label' => __('Missing required APIs', 'livecanvas-forge-ai'),
                'value' => implode(', ', array_map('sanitize_text_field', (array) $compatibility['missing_capabilities'])),
            ];
        }

        $preferred_client = $this->normalize_client_value((string) ($settings['preferred_client'] ?? ''));
        if ($preferred_client !== '') {
            $details[] = [
                'label' => __('Preferred client', 'livecanvas-forge-ai'),
                'value' => $preferred_client,
            ];
        }

        $updates = is_array($snapshot['ai_bridge_updates'] ?? null) ? $snapshot['ai_bridge_updates'] : [];
        if (!empty($updates)) {
            $details[] = [
                'label' => __('AI Bridge updates', 'livecanvas-forge-ai'),
                'value' => !empty($updates['eligible'])
                    ? (!empty($updates['update_available']) ? __('Update available', 'livecanvas-forge-ai') : __('Enabled', 'livecanvas-forge-ai'))
                    : __('Requires LiveCanvas license', 'livecanvas-forge-ai'),
            ];
        }

        return $details;
    }

    private function normalize_client_value(string $client): string {
        if ($client === 'claude-code') {
            return 'claude';
        }

        return $client;
    }
}
