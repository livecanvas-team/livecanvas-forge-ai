<?php

defined('ABSPATH') || exit;

final class LCFA_Design_System_Picostrap_Composer {
    private const SUPPORTED_COLORS = ['primary', 'secondary', 'success', 'info', 'warning', 'danger', 'light', 'dark', 'body_bg', 'body_color'];
    private const SUPPORTED_TYPOGRAPHY = ['font_family_base', 'headings_font_family', 'font_size_base', 'line_height_base'];
    private const SUPPORTED_RADIUS = ['border_radius', 'border_radius_sm', 'border_radius_lg'];
    private const SUPPORTED_BUTTONS = ['btn_padding_y', 'btn_padding_x', 'btn_border_radius'];

    public function compose(array $payload): array {
        $prompt = strtolower(trim((string) ($payload['prompt'] ?? '')));

        if ($this->is_too_vague($prompt)) {
            return [
                'ok' => false,
                'message' => __('Add more direction about color, typography, or button shape before composing a design system.', 'livecanvas-forge-ai'),
                'warnings' => [],
            ];
        }

        $warnings = [];
        $this->warn_unsupported_concepts($prompt, $warnings);

        $tokens = [
            'colors' => $this->compose_colors($prompt, $warnings),
            'typography' => $this->compose_typography($prompt),
            'radius' => $this->compose_radius($prompt),
            'buttons' => $this->compose_buttons($prompt),
        ];

        $tokens = $this->prune_supported($tokens);

        return [
            'ok' => true,
            'summary' => __('Composed a Picostrap design system preview.', 'livecanvas-forge-ai'),
            'message' => __('Design system preview prepared.', 'livecanvas-forge-ai'),
            'preview' => [
                'mood' => $this->compose_mood($prompt, $payload),
                'palette' => $tokens['colors'],
                'typography' => $tokens['typography'],
                'radius' => $tokens['radius'],
                'buttons' => $tokens['buttons'],
            ],
            'apply_payload' => [
                'action' => 'design_system_apply',
                'framework' => 'picostrap',
                'colors' => $tokens['colors'],
                'typography' => $tokens['typography'],
                'radius' => $tokens['radius'],
                'buttons' => $tokens['buttons'],
            ],
            'warnings' => $warnings,
        ];
    }

    private function is_too_vague(string $prompt): bool {
        return $prompt === '' || in_array($prompt, ['make it nice', 'nice', 'good', 'better'], true);
    }

    private function compose_mood(string $prompt, array $payload): string {
        $parts = [];

        foreach ((array) ($payload['brand_personality'] ?? []) as $value) {
            $value = sanitize_text_field($value);
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        foreach (['vibrant', 'premium', 'energetic', 'minimal', 'clean', 'playful', 'bold'] as $keyword) {
            if (str_contains($prompt, $keyword) && !in_array($keyword, $parts, true)) {
                $parts[] = $keyword;
            }
        }

        if (!$parts) {
            $parts[] = 'balanced';
        }

        return implode(', ', $parts);
    }

    private function compose_colors(string $prompt, array &$warnings): array {
        if ($this->contains_any($prompt, ['vibrant', 'bright', 'bold', 'fashion', 'premium'])) {
            return [
                'primary' => '#ff2d55',
                'secondary' => '#6a00ff',
                'success' => '#39ff14',
                'info' => '#00cfff',
                'warning' => '#ffb703',
                'danger' => '#ff3b30',
                'light' => '#fff4d6',
                'dark' => '#111827',
                'body_bg' => '#fff8ef',
                'body_color' => '#1f2937',
            ];
        }

        if ($this->contains_any($prompt, ['minimal', 'clean', 'calm'])) {
            return [
                'primary' => '#2563eb',
                'secondary' => '#64748b',
                'success' => '#16a34a',
                'info' => '#0891b2',
                'warning' => '#d97706',
                'danger' => '#dc2626',
                'light' => '#f8fafc',
                'dark' => '#0f172a',
                'body_bg' => '#ffffff',
                'body_color' => '#1e293b',
            ];
        }

        $warnings[] = __('No explicit palette direction was found. A safe energetic Bootstrap palette was used.', 'livecanvas-forge-ai');

        return [
            'primary' => '#ff2d55',
            'secondary' => '#6a00ff',
            'success' => '#39ff14',
            'info' => '#00cfff',
            'warning' => '#ffb703',
            'danger' => '#ff3b30',
            'light' => '#fff4d6',
            'dark' => '#111827',
            'body_bg' => '#fff8ef',
            'body_color' => '#1f2937',
        ];
    }

    private function compose_typography(string $prompt): array {
        if ($this->contains_any($prompt, ['expressive', 'display', 'premium', 'fashion'])) {
            return [
                'font_family_base' => '"Poppins", sans-serif',
                'headings_font_family' => '"Bebas Neue", sans-serif',
                'font_size_base' => '1rem',
                'line_height_base' => '1.6',
            ];
        }

        return [
            'font_family_base' => '"Inter", sans-serif',
            'headings_font_family' => '"Inter", sans-serif',
            'font_size_base' => '1rem',
            'line_height_base' => '1.6',
        ];
    }

    private function compose_radius(string $prompt): array {
        if ($this->contains_any($prompt, ['round', 'rounded', 'pill'])) {
            return [
                'border_radius' => '1rem',
                'border_radius_sm' => '0.6rem',
                'border_radius_lg' => '1.4rem',
            ];
        }

        return [
            'border_radius' => '0.5rem',
            'border_radius_sm' => '0.35rem',
            'border_radius_lg' => '0.85rem',
        ];
    }

    private function compose_buttons(string $prompt): array {
        if (str_contains($prompt, 'pill')) {
            return [
                'btn_padding_y' => '0.75rem',
                'btn_padding_x' => '1.4rem',
                'btn_border_radius' => '999px',
            ];
        }

        if ($this->contains_any($prompt, ['round', 'rounded'])) {
            return [
                'btn_padding_y' => '0.75rem',
                'btn_padding_x' => '1.3rem',
                'btn_border_radius' => '1rem',
            ];
        }

        return [
            'btn_padding_y' => '0.75rem',
            'btn_padding_x' => '1.25rem',
            'btn_border_radius' => '0.5rem',
        ];
    }

    private function warn_unsupported_concepts(string $prompt, array &$warnings): void {
        if (str_contains($prompt, 'accent')) {
            $warnings[] = __('"accent" is not a first-slice Picostrap token and was omitted.', 'livecanvas-forge-ai');
        }

        if (str_contains($prompt, 'shadow')) {
            $warnings[] = __('"card shadow" is conceptual only in this slice and was not included in apply_payload.', 'livecanvas-forge-ai');
        }

        if ($this->contains_any($prompt, ['motion', 'animation'])) {
            $warnings[] = __('Motion and animation systems are outside first-slice Picostrap support and were omitted.', 'livecanvas-forge-ai');
        }
    }

    private function prune_supported(array $tokens): array {
        return [
            'colors' => array_intersect_key((array) $tokens['colors'], array_flip(self::SUPPORTED_COLORS)),
            'typography' => array_intersect_key((array) $tokens['typography'], array_flip(self::SUPPORTED_TYPOGRAPHY)),
            'radius' => array_intersect_key((array) $tokens['radius'], array_flip(self::SUPPORTED_RADIUS)),
            'buttons' => array_intersect_key((array) $tokens['buttons'], array_flip(self::SUPPORTED_BUTTONS)),
        ];
    }

    private function contains_any(string $prompt, array $keywords): bool {
        foreach ($keywords as $keyword) {
            if (str_contains($prompt, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
