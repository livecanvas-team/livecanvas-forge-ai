<?php
/** Read-only checks against the two explicitly authorized local test sites. */
if (PHP_SAPI !== 'cli') exit(1);
$root = (string) getenv('LCFA_TEST_WP_ROOT');
$host = (string) getenv('LCFA_TEST_HOST');
if (!in_array($host, ['test-ai-forge.local', 'marketing-rocks.local'], true) || !$root) throw new RuntimeException('Explicit authorized site and root required.');
$_SERVER['HTTP_HOST'] = $host;
$_SERVER['REQUEST_URI'] = '/';
require rtrim($root, '/') . '/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== $host) throw new RuntimeException('Site identity mismatch.');
function workflow_check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function workflow_get($path) { return rest_do_request(new WP_REST_Request('GET', '/lcfa/v1/' . $path)); }
wp_set_current_user(0);
$denied = workflow_get('workflows');
workflow_check(in_array($denied->get_status(), [401, 403], true), 'Anonymous access must be denied.');
$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
workflow_check(!empty($admins), 'An administrator execution identity is required for this fixture.');
wp_set_current_user((int) $admins[0]);
global $wpdb;
$before = hash('sha256', wp_json_encode($wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A)));
$response = workflow_get('workflows');
workflow_check($response->get_status() === 200, 'Authenticated workflow catalog failed.');
$catalog = $response->get_data();
workflow_check(!empty($catalog['ok']) && count($catalog['workflows']) === 5, 'Incomplete workflow package.');
$ability = function_exists('wp_get_ability') ? wp_get_ability('livecanvas-forge-ai/list-workflows') : null;
workflow_check($ability !== null, 'Remote workflow Ability missing.');
workflow_check($ability->execute([]) === $catalog, 'Remote Ability and REST catalog differ.');
foreach ($catalog['workflows'] as $descriptor) {
    $read = workflow_get('workflows/' . $descriptor['id'])->get_data();
    $ability_read = wp_get_ability('livecanvas-forge-ai/read-workflow')->execute(['id' => $descriptor['id']]);
    workflow_check($read === $ability_read, 'REST and remote workflow body differ.');
    workflow_check(hash('sha256', $read['workflow']['instructions']) === $descriptor['sha256'], 'Workflow checksum mismatch.');
}
workflow_check(workflow_get('workflows/missing')->get_status() === 404, 'Unknown workflow must return 404.');
$handoff = workflow_get('studio/connection-handoff')->get_data();
workflow_check(strpos(wp_json_encode($handoff), 'list_workflows') !== false, 'Startup handoff must advertise workflows.');
workflow_check(($handoff['connection_handoff']['editor_chat']['same_desktop_conversation_verified'] ?? null) === false, 'MCP readiness must not claim a verified desktop conversation.');
$private_contract = $handoff['connection_handoff']['private_conversations'] ?? [];
workflow_check(($private_contract['queue_protocol'] ?? '') === 'post_claim_lease_v2', 'Startup handoff must explain the private queue protocol.');
$remote_handoff = wp_get_ability('livecanvas-forge-ai/get-connection-handoff')->execute([]);
workflow_check(($remote_handoff['connection_handoff']['private_conversations'] ?? []) === $private_contract, 'Local and remote startup must advertise the same private conversation contract.');
$changesets = $handoff['connection_handoff']['changesets'] ?? [];
workflow_check(!empty($changesets['child_theme_sources']) && ($changesets['file_write_coordinator'] ?? '') === 'authenticated_wordpress', 'Startup must advertise shared file journal enforcement.');
workflow_check(($remote_handoff['connection_handoff']['changesets'] ?? []) === $changesets, 'Remote and local startup must advertise the same Undo coverage.');
$after = hash('sha256', wp_json_encode($wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A)));
workflow_check($before === $after, 'Read-only discovery changed site content.');
echo wp_json_encode(['ok' => true, 'site' => $host, 'workflow_version' => $catalog['version'], 'catalog_count' => count($catalog['workflows']),
    'anonymous_access' => 'denied', 'rest_ability_parity' => true, 'handoff_discovery' => true, 'post_content_unchanged' => true,
    'desktop_chat' => 'not_tested', 'coding_agent_execution' => 'not_tested']) . "\n";
