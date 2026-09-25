<?php
declare(strict_types=1);

define('ABSPATH', '/tmp/forge-provenance-test/');
require_once __DIR__ . '/reflection-compat.php';

function sanitize_key($value): string {
    return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
}

final class LCFA_Settings {
    public static array $connections = ['preferred_client' => 'cursor'];
    public static function get_connections(): array { return self::$connections; }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-admin.php';
require dirname(__DIR__, 2) . '/includes/class-lcfa-rest-api.php';
require dirname(__DIR__, 2) . '/includes/class-lcfa-command-deck.php';

function expect_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': ' . var_export([$expected, $actual], true));
    }
}

foreach (['LCFA_Admin', 'LCFA_Rest_Api', 'LCFA_Command_Deck'] as $class) {
    $instance = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    $method = lcfa_test_reflection_method($class, 'get_payload_provenance');
    foreach (array_keys(LCFA_Agent_Registry::all(true)) as $client) {
        $result = $method->invoke($instance, [
            '_lcfa_origin' => 'mcp_agent',
            '_lcfa_agent' => $client,
            '_lcfa_processed_by' => $client . '_mcp',
        ]);
        expect_same($client, $result['agent'], $class . ' must preserve ' . $client);
        expect_same($client . '_mcp', $result['processed_by'], $class . ' must preserve the client processor');
    }
    foreach (['other' => 'generic', 'codex-cli' => 'codex', 'copilot-cli' => 'github-copilot', 'unrecognized' => 'generic'] as $input => $expected) {
        $result = $method->invoke($instance, ['_lcfa_origin' => 'mcp_agent', '_lcfa_agent' => $input]);
        expect_same($expected, $result['agent'], $class . ' must normalize aliases without inventing a client');
    }
    $result = $method->invoke($instance, []);
    expect_same('forge', $result['agent'], $class . ' must preserve the local Forge origin');
    expect_same('forge_local_rules', $result['processed_by'], $class . ' must preserve the local processor');
    $result = $method->invoke($instance, ['_lcfa_origin' => 'mcp_agent', '_lcfa_agent' => 'claude', '_lcfa_processed_by' => 'claude_mcp']);
    expect_same('claude', $result['agent'], $class . ' must preserve historical Claude attribution');
    expect_same('claude_mcp', $result['processed_by'], $class . ' must preserve historical Claude processors');
    $result = $method->invoke($instance, ['_lcfa_processed_by' => 'unrecognized_mcp']);
    expect_same('forge_local_rules', $result['processed_by'], $class . ' must reject arbitrary processors');
}

echo "PASS: admin, REST and command audit provenance share the complete agent registry\n";
