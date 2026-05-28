<?php

declare(strict_types=1);

define('ABSPATH', sys_get_temp_dir() . '/lcfa-wp7-ai-planning/');

function __($text, $domain = null) { return $text; }
function current_time($type, $gmt = false) { return '2026-05-27 10:00:00'; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\\-]/', '', strtolower((string) $value)); }
function sanitize_title($value) {
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    return trim((string) $value, '-');
}
function absint($value) { return max(0, (int) $value); }
function wp_strip_all_tags($value) { return strip_tags((string) $value); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function home_url($path = '') { return 'https://example.test/' . ltrim((string) $path, '/'); }
function is_wp_error($value): bool { return $value instanceof WP_Error; }

final class WP_Error {
    private string $message;

    public function __construct(string $code, string $message) {
        $this->message = $message;
    }

    public function get_error_message(): string {
        return $this->message;
    }
}

final class LCFA_Test_AI_Planning_Builder {
    private string $prompt;

    public function __construct(string $prompt) {
        $this->prompt = $prompt;
    }

    public function is_supported_for_text_generation(): bool {
        return true;
    }

    public function using_system_instruction(string $value): self {
        return $this;
    }

    public function using_temperature(float $value): self {
        return $this;
    }

    public function using_max_tokens(int $value): self {
        return $this;
    }

    public function as_json_response(array $schema): self {
        return $this;
    }

    public function generate_text(): string {
        if (str_contains($this->prompt, 'Genesis build plan')) {
            return json_encode([
                'pages' => [
                    [
                        'slug' => 'home',
                        'description' => 'AI-refined home page with proof, offers, and a clear conversion path.',
                        'ai_notes' => 'Lead with the strongest offer.',
                    ],
                ],
                'tasks' => [
                    [
                        'id' => 'foundation-shell',
                        'label' => 'AI-refined shell',
                        'description' => 'Create navigation around the main conversion paths.',
                        'user_prompt' => 'Create a header and footer that emphasize services and contact.',
                    ],
                ],
                'advisories' => [
                    'Capture logo assets before final visual polish.',
                ],
            ], JSON_UNESCAPED_SLASHES);
        }

        return json_encode([
            'mood' => 'AI premium, calm, conversion-focused',
            'colors' => [
                'primary' => '#123abc',
                'secondary' => '#f05a28',
                'accent' => '#ffffff',
            ],
            'typography' => [
                'font_family_base' => '"Inter", sans-serif',
                'headings_font_family' => '"Space Grotesk", sans-serif',
            ],
            'radius' => [
                'border_radius' => '0.75rem',
            ],
            'buttons' => [
                'btn_border_radius' => '999px',
            ],
            'warnings' => [
                'Accent was intentionally omitted because Picostrap first slice does not support it.',
            ],
        ], JSON_UNESCAPED_SLASHES);
    }
}

function wp_ai_client_prompt(string $prompt = ''): LCFA_Test_AI_Planning_Builder {
    return new LCFA_Test_AI_Planning_Builder($prompt);
}

final class LCFA_Settings {
    public static function get_sanitized_project_brief(array $brief): array {
        return array_merge(self::get_project_brief(), $brief);
    }

    public static function get_project_brief(): array {
        return [
            'project_mode' => 'from_scratch',
            'brand_name' => 'Example Brand',
            'sector' => 'consulting',
            'tone' => 'premium',
            'logo_status' => 'provided',
            'required_pages' => 'Home, Services, Contact',
            'notes' => 'Need a conversion-focused services site.',
        ];
    }

    public static function get_project_brief_hash(array $brief = []): string {
        return md5(json_encode($brief ?: self::get_project_brief()));
    }
}

final class LCFA_Environment {
    public function get_snapshot(): array {
        return [
            'detected_framework' => 'picostrap',
            'current_theme_stylesheet' => 'picostrap-child',
            'site_mode' => 'local',
            'windpress_active' => false,
            'woocommerce_active' => false,
            'acf_active' => false,
        ];
    }

    public function detect_framework_family(): string {
        return 'picostrap';
    }
}

final class LCFA_Inventory {
    public function get_summary(): array {
        return [
            'pages' => 0,
            'headers' => 0,
            'footers' => 0,
            'dynamic_templates' => 0,
            'blocks' => 0,
            'sections' => 0,
            'framework' => 'picostrap',
        ];
    }
}

final class LCFA_Design_System_Picostrap_Composer {
    public function compose(array $payload): array {
        return [
            'ok' => true,
            'summary' => 'Composed fallback preview.',
            'message' => 'Fallback prepared.',
            'preview' => [
                'mood' => 'fallback',
                'palette' => [
                    'primary' => '#ff2d55',
                    'secondary' => '#6a00ff',
                ],
                'typography' => [
                    'font_family_base' => '"Inter", sans-serif',
                ],
                'radius' => [
                    'border_radius' => '0.5rem',
                ],
                'buttons' => [
                    'btn_border_radius' => '0.5rem',
                ],
            ],
            'apply_payload' => [
                'action' => 'design_system_apply',
                'framework' => 'picostrap',
                'colors' => [
                    'primary' => '#ff2d55',
                    'secondary' => '#6a00ff',
                ],
                'typography' => [
                    'font_family_base' => '"Inter", sans-serif',
                ],
                'radius' => [
                    'border_radius' => '0.5rem',
                ],
                'buttons' => [
                    'btn_border_radius' => '0.5rem',
                ],
            ],
            'warnings' => [],
        ];
    }
}

final class LCFA_Design_System_Apply {
    public function run(array $payload, bool $dry_run): array {
        return ['ok' => true, 'message' => 'Applied.'];
    }
}

final class LCFA_Design_System_Preview {
    public function store(array $payload): string {
        return 'https://example.test/?lcfa_design_preview=1';
    }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-ai-client.php';
require dirname(__DIR__, 2) . '/includes/class-lcfa-genesis-planner.php';
require dirname(__DIR__, 2) . '/includes/class-lcfa-design-system-compose.php';

function lcfa_ai_planning_assert_true($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$ai_client = new LCFA_AI_Client();
$planner = new LCFA_Genesis_Planner(new LCFA_Environment(), new LCFA_Inventory(), $ai_client);
$plan = $planner->generate(LCFA_Settings::get_project_brief());

lcfa_ai_planning_assert_true(!empty($plan['ai']['used']), 'Genesis planner should use WordPress AI Client when available');
lcfa_ai_planning_assert_true(($plan['pages'][0]['description'] ?? '') === 'AI-refined home page with proof, offers, and a clear conversion path.', 'Genesis planner should merge AI page descriptions by slug');
lcfa_ai_planning_assert_true(($plan['tasks'][1]['label'] ?? '') === 'AI-refined shell', 'Genesis planner should merge AI task labels by ID');
lcfa_ai_planning_assert_true(end($plan['tasks'])['stage'] === 'ai_advisory', 'Genesis planner should append AI advisory tasks without executable payloads');

$compose = new LCFA_Design_System_Compose(
    new LCFA_Environment(),
    new LCFA_Design_System_Picostrap_Composer(),
    new LCFA_Design_System_Apply(),
    new LCFA_Design_System_Preview(),
    $ai_client
);
$result = $compose->run([
    'framework' => 'picostrap',
    'prompt' => 'Create a calm premium consulting design system with a strong orange secondary color and pill buttons.',
]);

lcfa_ai_planning_assert_true(!empty($result['ok']), 'AI-assisted design-system compose should succeed');
lcfa_ai_planning_assert_true(!empty($result['data']['ai_client']['used']), 'Design-system compose should mark AI usage');
lcfa_ai_planning_assert_true(($result['apply_payload']['colors']['primary'] ?? '') === '#123abc', 'Design-system compose should merge AI colors');
lcfa_ai_planning_assert_true(!isset($result['apply_payload']['colors']['accent']), 'Design-system compose should drop unsupported AI color keys');
lcfa_ai_planning_assert_true(($result['apply_payload']['buttons']['btn_border_radius'] ?? '') === '999px', 'Design-system compose should merge AI button tokens');

echo "PASS\n";
