<?php
if (PHP_SAPI !== 'cli') exit(1);
$host = (string) getenv('LCFA_TEST_HOST'); $root = (string) getenv('LCFA_TEST_WP_ROOT');
if (!in_array($host, ['test-ai-forge.local', 'marketing-rocks.local'], true) || !$root) exit(2);
$_SERVER['HTTP_HOST'] = $host; $_SERVER['REQUEST_URI'] = '/';
require rtrim($root, '/') . '/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== $host) exit(2);
$input = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
$owner = (int) ($input['owner'] ?? 0);
if (!user_can($owner, 'manage_options') || !str_starts_with($input['thread_id'] ?? '', 'thread-')) exit(2);
wp_set_current_user($owner);
$fixture = LCFA_Settings::get_thread($input['thread_id']);
$fixture_markers = array_filter($fixture['messages'] ?? [], static fn($message) => ($message['id'] ?? '') === 'private-text' && ($message['content'] ?? '') === ($input['fixture_marker'] ?? ''));
if (!$fixture_markers || !str_starts_with($input['fixture_marker'] ?? '', 'private-fixture-')) exit(2);
$deadline = min((float) ($input['start'] ?? 0), microtime(true) + 3);
while (microtime(true) < $deadline) usleep(1000);
if ($input['mode'] === 'claim') {
    if ((LCFA_Settings::get_agent_request($input['request_id'])['thread_id'] ?? '') !== $input['thread_id']) exit(2);
    $claim = LCFA_Private_Chat::claim($input['request_id'], 'opencode');
    // Pipe to the parent fixture only; the test output never prints this token.
    echo wp_json_encode(['claimed' => $claim !== null, 'lease_token' => $claim['lease_token'] ?? '']);
} elseif ($input['mode'] === 'append') {
    for ($i = 0; $i < 12; $i++) LCFA_Settings::append_thread_message($input['thread_id'], ['id' => 'concurrent-' . $input['worker'] . '-' . $i, 'role' => 'user', 'content' => 'Concurrent fixture message ' . $i]);
    echo wp_json_encode(['appended' => 12]);
} else exit(2);
