<?php
require __DIR__ . '/source-plugin-bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
if (!preg_match('/\Adelivery-fixture-[a-f0-9]{16}\z/D', $input['marker'] ?? '') ||
    !in_array($input['desktop_thread_id'] ?? '', [$input['marker'], $input['marker'] . '-protocol'], true)) exit(2);
wp_set_current_user(0);
if (!LCFA_MCP_Session_Manager::validate_session_token($input['session_token'] ?? '')) exit(2);
$request = LCFA_Private_Store::read('request', $input['request_id']);
if (($request['user_prompt'] ?? '') !== $input['marker']) exit(2);
$deadline = min((float) ($input['start'] ?? 0), microtime(true) + 3);
while (microtime(true) < $deadline) usleep(1000);
$result = match ($input['operation'] ?? 'reserve') {
    'pending' => LCFA_Desktop_Delivery::pending($input['desktop_thread_id']),
    'reserve' => LCFA_Desktop_Delivery::reserve($input['request_id'], $input['desktop_thread_id'], $input['payload_hash']),
    'record' => LCFA_Desktop_Delivery::record($input['request_id'], $input['desktop_thread_id'], $input['receipt_token'], $input['turn_id'], $input['turn_status']),
    default => throw new RuntimeException('Unknown fixture operation.'),
};
// Pipe-only private controller result. The parent does not print credentials.
echo wp_json_encode($result);
