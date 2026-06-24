<?php

declare(strict_types=1);

define('ABSPATH', sys_get_temp_dir() . '/lcfa-full-power/');

function __(string $text, string $domain = ''): string { return $text; }
function sanitize_key($value): string { return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function absint($value): int { return max(0, (int) $value); }
function wp_text_diff(string $left, string $right, array $args = []): string { return $left === $right ? '' : 'diff'; }

final class LCFA_Inventory {
    public string $content = '<section id="hero"><h1>Old title</h1><p class="lead">Lead</p></section><section class="card">One</section><section class="card">Two</section>';

    public function get_target_content(string $target_type, int $target_id, string $variant = '1'): array {
        return [
            'post' => [
                'id' => $target_id,
                'title' => 'Patch target',
            ],
            'content' => $this->content,
        ];
    }
}

final class LCFA_Command_Deck {
    public array $last_payload = [];

    public function execute(array $payload): array {
        $this->last_payload = $payload;

        if (($payload['action'] ?? '') === 'validate_markup_for_framework') {
            return [
                'ok' => true,
                'data' => [
                    'valid' => true,
                ],
            ];
        }

        return [
            'ok' => true,
            'action' => $payload['action'] ?? '',
            'target_id' => $payload['target_id'] ?? 0,
            'content' => $payload['content'] ?? '',
        ];
    }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-content-patch-service.php';

function lcfa_full_power_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$inventory = new LCFA_Inventory();
$command_deck = new LCFA_Command_Deck();
$service = new LCFA_Content_Patch_Service($inventory, $command_deck);

$preview = $service->preview([
    'target_type' => 'page',
    'target_id' => 42,
    'operation' => 'replace_text',
    'search' => 'Old title',
    'replacement' => 'New title',
]);
lcfa_full_power_assert_true(!empty($preview['ok']), 'text patch preview should succeed for a unique exact match');
lcfa_full_power_assert_true(str_contains($preview['patched_html'] ?? '', 'New title'), 'text patch preview should include the replacement');
lcfa_full_power_assert_true(!str_contains($preview['patched_html'] ?? '', 'Old title'), 'text patch preview should remove the original text');

$ambiguous = $service->preview([
    'target_type' => 'page',
    'target_id' => 42,
    'selector' => '.card',
    'html' => '<section class="card">Updated</section>',
]);
lcfa_full_power_assert_true(empty($ambiguous['ok']), 'selector patch should fail when multiple elements match by default');

$selector_preview = $service->preview([
    'target_type' => 'page',
    'target_id' => 42,
    'selector' => '#hero',
    'operation' => 'append_html',
    'html' => '<a href="/contact">Contact</a>',
]);
lcfa_full_power_assert_true(!empty($selector_preview['ok']), 'selector append preview should succeed for a unique selector');
lcfa_full_power_assert_true(str_contains($selector_preview['patched_html'] ?? '', 'Contact'), 'selector append preview should include appended content');

$apply = $service->apply([
    'target_type' => 'page',
    'target_id' => 42,
    'operation' => 'replace_text',
    'search' => 'Old title',
    'replacement' => 'Applied title',
]);
lcfa_full_power_assert_true(!empty($apply['ok']), 'content patch apply should execute the command deck write');
lcfa_full_power_assert_true(($apply['action'] ?? '') === 'update_page', 'page content patch apply should use update_page');
lcfa_full_power_assert_true(str_contains($apply['content'] ?? '', 'Applied title'), 'content patch apply should pass patched content to Command Deck');

echo "PASS\n";
