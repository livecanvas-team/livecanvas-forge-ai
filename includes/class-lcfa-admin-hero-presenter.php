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
                'title' => __('Forge Setup', 'livecanvas-forge-ai'),
                'subtitle' => __('Prepare the site and connection flow.', 'livecanvas-forge-ai'),
            ],
            'connections' => [
                'title' => __('Connections', 'livecanvas-forge-ai'),
                'subtitle' => __('Connect and verify your coding agent.', 'livecanvas-forge-ai'),
            ],
            'genesis' => [
                'title' => __('Project Brief & Build Plan', 'livecanvas-forge-ai'),
                'subtitle' => __('Shape the project after the connection is ready.', 'livecanvas-forge-ai'),
            ],
            'command' => [
                'title' => __('Command Deck', 'livecanvas-forge-ai'),
                'subtitle' => __('Run concrete site operations through Forge AI.', 'livecanvas-forge-ai'),
            ],
        ];

        return $map[$tab] ?? $map['connections'];
    }

    private function build_marks(array $snapshot): array {
        $framework = (string) ($snapshot['detected_framework'] ?? 'unknown');
        $marks = [
            [
                'key' => 'livecanvas',
                'label' => __('LiveCanvas', 'livecanvas-forge-ai'),
                'type' => 'partner',
                'asset' => 'livecanvas-micro',
                'active' => !empty($snapshot['livecanvas_active']),
            ],
        ];

        if ($framework === 'picowind') {
            $marks[] = [
                'key' => 'picowind',
                'label' => __('Picowind', 'livecanvas-forge-ai'),
                'type' => 'icon',
                'asset' => 'wind',
                'active' => true,
            ];
        } else {
            $marks[] = [
                'key' => 'bootstrap',
                'label' => __('Bootstrap', 'livecanvas-forge-ai'),
                'type' => 'partner',
                'asset' => 'bootstrap',
                'active' => $framework === 'bootstrap',
            ];
        }

        if (!empty($snapshot['windpress_active']) || !empty($snapshot['windpress_installed'])) {
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

        $client_value = (string) ($settings['preferred_client'] ?? '');
        if ($client_value !== '') {
            $chips[] = [
                'label' => __('Client', 'livecanvas-forge-ai'),
                'value' => $client_value,
                'tone' => 'other',
                'client' => $client_value,
            ];
        }

        $editor_value = (string) ($snapshot['framework_slug'] ?? '');
        if ($editor_value !== '') {
            $chips[] = [
                'label' => __('Editor', 'livecanvas-forge-ai'),
                'value' => $editor_value,
                'tone' => 'other',
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

        if (!empty($settings['preferred_client'])) {
            $details[] = [
                'label' => __('Preferred client', 'livecanvas-forge-ai'),
                'value' => (string) $settings['preferred_client'],
            ];
        }

        return $details;
    }
}
