<?php
if (PHP_SAPI !== 'cli') exit(1);
$host = (string) getenv('LCFA_TEST_HOST'); $root = (string) getenv('LCFA_TEST_WP_ROOT');
if (!in_array($host, ['test-ai-forge.local', 'marketing-rocks.local'], true) || !$root) exit(2);
$_SERVER['HTTP_HOST'] = $host; $_SERVER['REQUEST_URI'] = '/';
require rtrim($root, '/') . '/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== $host) exit(2);
$input = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
$prefix = $input['prefix'] ?? '';
if (!preg_match('/^transport-fixture-[a-f0-9]{16}$/', $prefix) || !isset(LCFA_Session_Store::read()[$prefix])) exit(2);
$deadline = min((float) ($input['start'] ?? 0), microtime(true) + 3);
while (microtime(true) < $deadline) usleep(1000);
for ($i = 0; $i < 6; $i++) {
    $id = $prefix . '-' . (int) $input['worker'] . '-' . $i;
    LCFA_Session_Store::mutate(static function ($sessions) use ($id) {
        usleep(10000);
        // Non-authenticating markers test simultaneous insertions without granting access.
        $sessions[$id] = ['session_id' => $id, 'revoked_at' => gmdate('c')];
        return $sessions;
    });
}
echo '{"inserted":6}';
