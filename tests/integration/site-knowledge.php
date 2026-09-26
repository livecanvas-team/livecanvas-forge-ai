<?php
/** Real WordPress, working-tree plugin, isolated option and synthetic instructions only. */
require __DIR__ . '/source-plugin-bootstrap.php';
function sk_assert($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function sk_reject($call, $code) { try { $call(); } catch (RuntimeException $e) { sk_assert($e->getMessage() === $code, 'Unexpected rejection: ' . $e->getMessage()); return; } throw new RuntimeException('Expected ' . $code); }
function sk_get($path) { return rest_do_request(new WP_REST_Request('GET', '/lcfa/v1/' . $path)); }
global $wpdb;
$fixture = 'lcfa_knowledge_fixture_' . bin2hex(random_bytes(8));
$before_posts = $wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A);
$before_meta = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} ORDER BY meta_id", ARRAY_A);
$before_knowledge = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->options} WHERE option_name=%s", LCFA_Site_Knowledge::OPTION), ARRAY_A);
$before_brief = get_option(LCFA_Settings::BRIEF_OPTION_KEY);
$before_sessions = LCFA_Session_Store::read();
$before_plugins = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='active_plugins'");
// Redirect only this service's option inside this process. Browser requests and
// the site's existing instructions keep using their original storage.
$redirect = static fn($sql) => str_replace("'" . LCFA_Site_Knowledge::OPTION . "'", "'" . $fixture . "'", $sql);
add_filter('query', $redirect);
$fault = null; $checks = [];
try {
    wp_set_current_user(0);
    sk_assert(in_array(sk_get('site-knowledge')->get_status(), [401, 403], true), 'Anonymous access accepted.');
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']); sk_assert($admins, 'Existing administrator required.');
    wp_set_current_user((int) $admins[0]); $nonce = wp_create_nonce('lcfa_site_knowledge');
    $initial = sk_get('site-knowledge')->get_data()['site_knowledge'];
    sk_assert($initial['state'] === 'not_shared' && $initial['instructions'] === '', 'Legacy information was implicitly imported.');
    $context = LCFA_Write_Contract::prepare(['target_type' => 'site']); sk_assert($context['ok'], 'Initial context failed.');
    $text = "Use concise English for the fixture.\nKeep WordPress and theme rules. Café 日本語.";
    sk_reject(fn() => LCFA_Site_Knowledge::review($text, $initial['revision'], 'invalid'), 'knowledge_admin_review_required');
    $shared = LCFA_Site_Knowledge::review($text, $initial['revision'], $nonce);
    sk_assert($shared['site_knowledge']['instructions'] === $text, 'Unicode or newline storage changed.');
    sk_assert(sk_get('site-knowledge')->get_data() === $shared, 'REST read differs.');
    $ability = wp_get_ability('livecanvas-forge-ai/get-site-knowledge'); sk_assert($ability && $ability->execute([]) === $shared, 'Ability parity failed.');
    sk_assert((LCFA_Write_Contract::validate(['write_context' => $context['write_context']], 'validate')['code'] ?? '') === 'stale_context', 'Approved instructions did not invalidate previous context.');
    $fresh = LCFA_Write_Contract::prepare(['target_type' => 'site']);
    sk_assert($fresh['context']['site_knowledge_revision'] === $shared['site_knowledge']['revision'], 'Context revision mismatch.');
    foreach (['framework', 'roots', 'rendering', 'pipeline'] as $field) sk_assert($fresh['context'][$field] === $context['context'][$field], 'User-authored instructions changed verified technical context.');
    sk_reject(fn() => LCFA_Site_Knowledge::review('Stale overwrite', $initial['revision'], $nonce), 'knowledge_revision_conflict');
    $checks['approved_context'] = 'rest_ability_parity_stale_context_rejected';
    foreach (['studio/connection-handoff'] as $path) sk_assert(str_contains(wp_json_encode(sk_get($path)->get_data()), 'get_site_knowledge'), 'Startup discovery missing.');
    $remote = wp_get_ability('livecanvas-forge-ai/get-connection-handoff')->execute([]);
    sk_assert(($remote['connection_handoff']['site_knowledge'] ?? []) === LCFA_Site_Knowledge::discovery(), 'Remote startup mismatch.');
    sk_assert(rest_do_request(new WP_REST_Request('POST', '/lcfa/v1/site-knowledge'))->get_status() === 404, 'Agent write route must not exist.');
    // A session header cannot use an administrator cookie as a read fallback.
    $invalid = new WP_REST_Request('GET', '/lcfa/v1/site-knowledge'); $invalid->set_header('x-lcfa-mcp-session', 'invalid-fixture');
    sk_assert(rest_do_request($invalid)->get_status() === 403, 'Invalid session bypassed through current user.');
    $checks['exposure'] = 'read_only_no_cookie_bypass_no_implicit_private_import';
    $deny_admin = static function ($caps) { $caps['manage_options'] = false; return $caps; };
    add_filter('user_has_cap', $deny_admin);
    try { sk_reject(fn() => LCFA_Site_Knowledge::review('Unapproved', $shared['site_knowledge']['revision'], $nonce), 'knowledge_admin_review_required'); }
    finally { remove_filter('user_has_cap', $deny_admin); }

    $fault = static fn($sql) => str_starts_with($sql, "UPDATE {$wpdb->options} SET option_value=") ? 'SELECT LCFA_EXPECTED_KNOWLEDGE_FAILURE()' : $sql;
    add_filter('query', $fault, 20); $old_errors = $wpdb->suppress_errors(true);
    try { sk_reject(fn() => LCFA_Site_Knowledge::review('Failed save', $shared['site_knowledge']['revision'], $nonce), 'knowledge_storage_failed'); }
    finally { remove_filter('query', $fault, 20); $fault = null; $wpdb->suppress_errors($old_errors); }
    sk_assert(LCFA_Site_Knowledge::read() === $shared, 'Failed save changed approved instructions.');
    $withdrawn = LCFA_Site_Knowledge::review('', $shared['site_knowledge']['revision'], $nonce);
    sk_assert($withdrawn['site_knowledge']['instructions'] === '' && $withdrawn['site_knowledge']['revision'] !== $initial['revision'], 'Withdrawal lost its revision.');
    sk_assert((LCFA_Write_Contract::validate(['write_context' => $fresh['write_context']], 'validate')['code'] ?? '') === 'stale_context', 'Withdrawal did not stale context.');
    $checks['storage_and_withdrawal'] = 'failed_save_preserves_data_withdrawal_stales_context';
    ob_start(); LCFA_Site_Knowledge::render_controls('</textarea><script>fixture</script>', 'Review changes.'); $html = ob_get_clean();
    sk_assert(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'Draft escaping failed.');
    sk_assert(str_contains($html, 'name="knowledge_revision"') && str_contains($html, '_wpnonce'), 'Review controls missing.');
} finally {
    if ($fault) remove_filter('query', $fault, 20);
    remove_filter('query', $redirect);
    delete_option($fixture);
    wp_cache_delete(LCFA_Site_Knowledge::OPTION, 'options');
}
sk_assert($before_posts === $wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A), 'Existing content changed.');
sk_assert($before_meta === $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} ORDER BY meta_id", ARRAY_A), 'Existing metadata changed.');
sk_assert($before_knowledge === $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->options} WHERE option_name=%s", LCFA_Site_Knowledge::OPTION), ARRAY_A), 'Existing site instructions changed.');
sk_assert($before_brief === get_option(LCFA_Settings::BRIEF_OPTION_KEY) && $before_sessions === LCFA_Session_Store::read(), 'Existing brief or sessions changed.');
sk_assert($before_plugins === $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='active_plugins'"), 'Plugin activation changed.');
echo wp_json_encode(['ok' => true, 'site' => $host, 'build' => $fixture_build === 'installed' ? 'installed_package' : 'working_tree_source_loaded_in_fixture_process_only', 'checks' => $checks,
    'existing_content_metadata_brief_knowledge_sessions' => 'unchanged', 'plugin_activation' => 'unchanged', 'actual_agent' => 'not_invoked']) . "\n";
