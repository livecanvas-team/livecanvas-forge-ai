<?php
declare(strict_types=1);
define('ABSPATH', '/tmp/forge-registry-test/');
require dirname(__DIR__, 2) . '/includes/class-lcfa-agent-registry.php';

function expect($expected, $actual): void {
    if ($expected !== $actual) throw new RuntimeException(var_export([$expected, $actual], true));
}

expect('generic', LCFA_Agent_Registry::normalize('unrecognized'));
expect('', LCFA_Agent_Registry::normalize('unrecognized', ''));
expect('claude-code', LCFA_Agent_Registry::normalize('claude-code'));
expect('claude-desktop', LCFA_Agent_Registry::normalize('claude-desktop'));
expect('codex', LCFA_Agent_Registry::normalize('codex-cli'));
expect('claude-desktop', LCFA_Agent_Registry::from_connections(['preferred_client' => 'claude', 'claude_connection_target' => 'desktop_app']));
expect('claude-code', LCFA_Agent_Registry::from_connections(['preferred_client' => 'claude', 'claude_connection_target' => 'cli']));
expect(false, isset(LCFA_Agent_Registry::all()['claude']));
foreach (LCFA_Agent_Registry::all(true) as $id => $agent) {
    expect($id, LCFA_Agent_Registry::normalize($id));
    expect($agent['label'], LCFA_Agent_Registry::label($id));
}
expect(['read', 'preview', 'write', 'media', 'theme_files', 'debug', 'cache', 'seo'], LCFA_Agent_Registry::full_access_scopes());
echo "PASS: shared agent registry, canonical identities and legacy Claude targets\n";
