<?php

declare(strict_types=1);

define('ABSPATH', sys_get_temp_dir() . '/lcfa-wp7-ai-client/');

function __($text, $domain = null) { return $text; }
function absint($value) { return max(0, (int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\\-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function is_wp_error($value): bool { return $value instanceof WP_Error; }

final class WP_Error {
    private string $code;
    private string $message;

    public function __construct(string $code, string $message) {
        $this->code = $code;
        $this->message = $message;
    }

    public function get_error_code(): string {
        return $this->code;
    }

    public function get_error_message(): string {
        return $this->message;
    }
}

final class LCFA_Test_AI_Builder {
    public string $prompt;
    public array $calls = [];

    public function __construct(string $prompt) {
        $this->prompt = $prompt;
    }

    public function is_supported_for_text_generation(): bool {
        return true;
    }

    public function using_system_instruction(string $value): self {
        $this->calls['system_instruction'] = $value;
        return $this;
    }

    public function using_temperature(float $value): self {
        $this->calls['temperature'] = $value;
        return $this;
    }

    public function using_max_tokens(int $value): self {
        $this->calls['max_tokens'] = $value;
        return $this;
    }

    public function using_model_preference(string ...$models): self {
        $this->calls['model_preference'] = $models;
        return $this;
    }

    public function as_json_response(array $schema): self {
        $this->calls['response_schema'] = $schema;
        return $this;
    }

    public function generate_text(): string {
        $GLOBALS['lcfa_test_ai_last_builder'] = $this;
        if (!empty($this->calls['response_schema'])) {
            return '{"headline":"Structured headline"}';
        }

        return 'Generated: ' . $this->prompt;
    }
}

function wp_ai_client_prompt(string $prompt = ''): LCFA_Test_AI_Builder {
    return new LCFA_Test_AI_Builder($prompt);
}

function wp_get_ai_connectors(): array {
    return [
        'test_connector' => [
            'id' => 'test_connector',
            'label' => 'Test Connector',
            'capabilities' => ['text_generation', 'image_generation'],
        ],
    ];
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-ai-client.php';

function lcfa_ai_assert_true($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$client = new LCFA_AI_Client();
$status = $client->get_status();
lcfa_ai_assert_true(!empty($status['available']), 'AI Client should report available when wp_ai_client_prompt exists');
lcfa_ai_assert_true(!empty($status['text_generation_supported']), 'AI Client should detect text generation support');
lcfa_ai_assert_true(!empty($status['json_response_supported']), 'AI Client should report JSON response support when the builder exposes it');
lcfa_ai_assert_true(($status['connectors']['source'] ?? '') === 'wp_get_ai_connectors', 'AI Client status should include connector registry diagnostics');
lcfa_ai_assert_true(($status['connectors']['text_generation_count'] ?? 0) === 1, 'AI Client status should count text-generation connectors');

$result = $client->generate_text('Write a short headline.', [
    'system_instruction' => 'You are concise.',
    'temperature'        => 0.4,
    'max_tokens'         => 120,
    'model_preference'   => ['gpt-5.4', '<bad>'],
]);

lcfa_ai_assert_true(!is_wp_error($result), 'AI Client should generate text when supported');
lcfa_ai_assert_true(($result['text'] ?? '') === 'Generated: Write a short headline.', 'AI Client should return generated text');

$builder = $GLOBALS['lcfa_test_ai_last_builder'] ?? null;
lcfa_ai_assert_true($builder instanceof LCFA_Test_AI_Builder, 'AI Client should use the prompt builder');
lcfa_ai_assert_true(($builder->calls['system_instruction'] ?? '') === 'You are concise.', 'AI Client should apply system instruction');
lcfa_ai_assert_true(($builder->calls['temperature'] ?? null) === 0.4, 'AI Client should apply temperature');
lcfa_ai_assert_true(($builder->calls['max_tokens'] ?? 0) === 120, 'AI Client should apply max tokens');
lcfa_ai_assert_true(($builder->calls['model_preference'][0] ?? '') === 'gpt-5.4', 'AI Client should apply model preferences');

$empty = $client->generate_text('');
lcfa_ai_assert_true(is_wp_error($empty), 'AI Client should reject empty prompts');
lcfa_ai_assert_true($empty->get_error_code() === 'lcfa_ai_empty_prompt', 'AI Client should return a specific empty prompt error');

$json = $client->generate_json('Return JSON.', ['type' => 'object']);
lcfa_ai_assert_true(!is_wp_error($json), 'AI Client should parse structured JSON responses');
lcfa_ai_assert_true(($json['data']['headline'] ?? '') === 'Structured headline', 'AI Client should return decoded JSON data');

$json_builder = $GLOBALS['lcfa_test_ai_last_builder'] ?? null;
lcfa_ai_assert_true(($json_builder->calls['response_schema']['type'] ?? '') === 'object', 'AI Client should apply JSON response schema');

echo "PASS\n";
