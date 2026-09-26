<?php
/** Native compiler, isolated cache destinations. Never replaces the served cache. */
if (PHP_SAPI !== 'cli') exit(1);
$host = (string) getenv('LCFA_TEST_HOST'); $root = (string) getenv('LCFA_TEST_WP_ROOT');
if ($host !== 'marketing-rocks.local' || $root !== '/Users/commander/Local Sites/marketing-rocks/app/public') throw new RuntimeException('Authorized MarketingRocks fixture required.');
$_SERVER['HTTP_HOST'] = $host; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['SERVER_PORT'] = 80;
require $root . '/wp-load.php';
function wa_check($value, $message) { if (!$value) throw new RuntimeException($message); }
function wa_call($route, $payload) {
    $r = new WP_REST_Request('POST', '/lcfa/v1/' . $route); $r->set_header('Content-Type', 'application/json'); $r->set_body(wp_json_encode($payload));
    $body = rest_do_request($r)->get_data(); return $body['result'] ?? $body;
}
function wa_payload() {
    $context = LCFA_Write_Contract::prepare(['target_type' => 'site']); wa_check(!empty($context['ok']), 'Fresh site context required.');
    return ['write_context' => $context['write_context'], 'source_revision' => $context['context']['source_revision'], 'acknowledge_shared' => true];
}
function wa_undo($id, $extra = []) { return wa_call('changesets/undo', $extra + ['changeset_id' => $id] + wa_payload()); }
function wa_tree($root) {
    $files = [];
    if (!is_dir($root)) return $files;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $f) if ($f->isFile() && !$f->isLink()) $files[$f->getPathname()] = [hash_file('sha256', $f->getPathname()), $f->getPerms() & 0777];
    ksort($files); return $files;
}
global $wpdb;
$owner = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0] ?? 0); wa_check($owner > 0, 'Existing administrator required.'); wp_set_current_user($owner);
wa_check(wp_parse_url(home_url(), PHP_URL_HOST) === $host && (new LCFA_Environment())->detect_framework_family() === 'picowind', 'Site/framework mismatch.');
$bridge = new LCFA_WindPress_Bridge(new LCFA_Environment());
$real_cache_dir = dirname(\WindPress\WindPress\Core\Cache::get_cache_path(\WindPress\WindPress\Core\Cache::CSS_CACHE_FILE));
$cache_baseline = wa_tree($real_cache_dir); $theme_root = realpath(get_stylesheet_directory()); $theme_baseline = wa_tree($theme_root);
$posts = $wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A); $mods = get_option('theme_mods_' . get_stylesheet());
$option = 'lcfa_windpress_compile_evidence'; $evidence = get_option($option, null);
$prefix = 'lcfa-windpress-fixture-' . bin2hex(random_bytes(6)); $uploads = wp_upload_dir(null, false); $fixture_dir = $uploads['basedir'] . '/' . $prefix;
wa_check(!file_exists($fixture_dir), 'Fixture directory collision.');
// Redirect the native Cache API only, in this CLI process. Volume/source paths
// and every frontend request retain their real uploads directory.
$redirect = static function ($upload) use ($prefix) {
    foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12) as $frame) {
        if (($frame['class'] ?? '') === 'WindPress\\WindPress\\Core\\Cache' && in_array($frame['function'], ['get_cache_path', 'get_cache_url'], true)) {
            $upload['basedir'] .= '/' . $prefix; $upload['baseurl'] .= '/' . $prefix; break;
        }
    }
    return $upload;
};
add_filter('upload_dir', $redirect, 1000);
$css_path = \WindPress\WindPress\Core\Cache::get_cache_path(\WindPress\WindPress\Core\Cache::CSS_CACHE_FILE);
$map_path = \WindPress\WindPress\Core\Cache::get_cache_path(\WindPress\WindPress\Core\Cache::CSS_SOURCEMAP_FILE);
wa_check(strpos($css_path, $fixture_dir . '/') === 0 && strpos($map_path, $fixture_dir . '/') === 0, 'Native fixture cache paths must be isolated.');
wp_mkdir_p(dirname($css_path));
$old_css = '/* previous fixture cache */ .fixture{display:block}'; $old_map = '{"version":3,"sources":["old-fixture.css"],"names":[],"mappings":""}';
file_put_contents($css_path, $old_css); chmod($css_path, 0640); file_put_contents($map_path, $old_map); chmod($map_path, 0640);
$table = $wpdb->prefix . 'lcfa_changesets'; $results = [];
try {
    $process = proc_open(['node', __DIR__ . '/windpress-compile-fixture.js'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null,
        array_merge(getenv(), ['LCFA_TEST_DB_SOCKET' => (string) ini_get('mysqli.default_socket')]));
    wa_check(is_resource($process), 'Native compiler fixture could not start.'); fclose($pipes[0]);
    $compiled = json_decode(stream_get_contents($pipes[1]), true); fclose($pipes[1]); $errors = stream_get_contents($pipes[2]); fclose($pipes[2]);
    wa_check(proc_close($process) === 0 && !empty($compiled['css']), 'Native compilation failed: ' . $errors);
    $css = $compiled['css']; $map = $compiled['sourcemap'];
    wa_check(is_string($map) && $map !== '', 'Installed compiler must produce a real source map for this fixture.');
    wa_check(strpos($compiled['compiler'], '/assets/dist/') !== false, 'Installed assets/dist manifest must resolve.');
    $input = ['css' => $css, 'sourcemap' => $map];
    if (in_array('--probe-old', $argv, true)) {
        $reject = static function ($new, $old) { return $old; }; add_filter('pre_update_option_' . $option, $reject, 1, 2);
        try { $probe = wa_call('windpress/cache', $input + wa_payload()); } finally { remove_filter('pre_update_option_' . $option, $reject, 1); }
        echo wp_json_encode(['old_store_probe' => ['reported_ok' => $probe['ok'] ?? false, 'css_replaced' => file_get_contents($css_path) !== $old_css, 'evidence_unchanged' => get_option($option, null) === $evidence], 'compiled_bytes' => strlen($css)]) . "\n";
    } else {
        wa_check(($bridge->save_cache_css($css)['code'] ?? '') === 'context_required', 'Missing context must be rejected.');
        $stale = wa_call('windpress/cache', $input + ['source_revision' => 'outdated'] + wa_payload());
        wa_check(($stale['code'] ?? '') === 'stale_sources', 'Stale sources must be rejected.');
        $invalid_map = wa_call('windpress/cache', ['css' => $css, 'sourcemap' => 'invalid-json'] + wa_payload());
        wa_check(($invalid_map['code'] ?? '') === 'source_map_invalid', 'Malformed source map must be rejected.');
        wa_check(file_get_contents($css_path) === $old_css && file_get_contents($map_path) === $old_map && get_option($option, null) === $evidence, 'Rejected builds must preserve previous artifacts.');
        $payload = $input + wa_payload(); $save = wa_call('windpress/cache', $payload);
        wa_check(!empty($save['ok']) && !empty($save['changeset']['undo_available']), 'WindPress cache storage needs a journal: ' . wp_json_encode($save));
        $id = $save['changeset']['id'];
        wa_check(file_get_contents($css_path) === $css && file_get_contents($map_path) === $map, 'Compiled bytes differ.');
        $stored_evidence = get_option($option);
        wa_check(($stored_evidence['css_sha256'] ?? '') === hash('sha256', $css) && ($save['verification_states']['compiled'] ?? '') === 'client_reported', 'Server must verify evidence without claiming to be the compiler.');
        wa_check((wa_call('windpress/cache', $payload)['code'] ?? '') === 'stale_context', 'Changed compiled artifacts must invalidate the old context even without framework plugins.');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%s", $id), ARRAY_A);
        wa_check(strpos($row['before_snapshot'], $prefix) === false, 'Snapshot must be encrypted.');
        $wpdb->update($table, ['owner_user_id' => $owner + 1000000], ['id' => $id]); wa_check((wa_undo($id)['code'] ?? '') === 'changeset_not_found', 'Foreign owner must be rejected.'); $wpdb->update($table, ['owner_user_id' => $owner], ['id' => $id]);
        file_put_contents($map_path, $map . ' '); wa_check((wa_undo($id)['code'] ?? '') === 'changeset_conflict', 'Source-map changes must block Undo.'); file_put_contents($map_path, $map);
        update_option($option, $stored_evidence + ['external_fixture' => true]); wa_check((wa_undo($id)['code'] ?? '') === 'changeset_conflict', 'Compile evidence changes must block Undo.'); update_option($option, $stored_evidence);
        wa_check(!empty(wa_undo($id, ['dry_run' => true])['ok']) && file_get_contents($css_path) === $css, 'Undo preview must not write.');
        $fired = false;
        $undo_ack = static function ($sql) use ($table, &$fired) {
            if (!$fired && preg_match('/^\s*UPDATE/i', $sql) && strpos($sql, $table) !== false && strpos($sql, "'undone'") !== false) { $fired = true; throw new RuntimeException('changeset_journal_failed: Injected Undo acknowledgement failure.'); }
            return $sql;
        };
        add_filter('query', $undo_ack, 1); try { $failed = wa_undo($id); } finally { remove_filter('query', $undo_ack, 1); }
        wa_check($fired && ($failed['changeset']['status'] ?? '') === 'saved' && !empty($failed['recovery']['cache_and_evidence_compensation_verified']) && file_get_contents($css_path) === $css && file_get_contents($map_path) === $map && get_option($option) === $stored_evidence, 'Failed Undo must compensate to the saved state for retry.');
        $undo = wp_get_ability('livecanvas-forge-ai/undo-changeset')->execute(['changeset_id' => $id] + wa_payload());
        wa_check(!is_wp_error($undo) && !empty($undo['ok']) && file_get_contents($css_path) === $old_css && file_get_contents($map_path) === $old_map && get_option($option, null) === $evidence, 'Remote Undo must restore CSS, map and evidence exactly.');
        wa_check((fileperms($css_path) & 0777) === 0640 && (fileperms($map_path) & 0777) === 0640, 'Undo must preserve permissions.');
        $results['installed_assets_dist_compilation'] = ['bytes' => strlen($css), 'map_bytes' => strlen($map), 'candidates' => $compiled['candidates'], 'plugins' => $compiled['plugins']];
        $results['rest_store_remote_undo_and_conflicts'] = 'passed';

        $ability = wp_get_ability('livecanvas-forge-ai/store-windpress-cache'); wa_check((bool) $ability, 'Remote cache Ability must exist.');
        $remote = $ability->execute(['css' => $css] + wa_payload()); wa_check(!is_wp_error($remote) && !empty($remote['result']['ok']), 'Remote cache Ability must store.');
        wa_check(!file_exists($map_path), 'A build without a map must remove the obsolete map.');
        wa_check(!empty(wa_undo($remote['result']['changeset']['id'])['ok']) && file_get_contents($map_path) === $old_map, 'REST Undo must restore the previous optional map.');
        $results['remote_store_rest_undo_and_obsolete_map'] = 'passed';

        foreach (['snapshot', 'evidence', 'journal', 'external'] as $fault) {
            $fired = false;
            $inject = static function ($sql) use ($fault, &$fired, $table, $option, $css_path) {
                $match = $fault === 'snapshot' ? preg_match('/^\s*INSERT/i', $sql) && strpos($sql, $table) !== false
                    : ($fault === 'evidence' ? preg_match('/^\s*(INSERT|UPDATE)/i', $sql) && strpos($sql, "'" . $option . "'") !== false
                    : preg_match('/^\s*UPDATE/i', $sql) && strpos($sql, $table) !== false && strpos($sql, "'saved'") !== false);
                if (!$fired && $match) { $fired = true; if ($fault === 'external') file_put_contents($css_path, '/* external fixture edit */'); throw new RuntimeException('changeset_journal_failed: Injected compiled-cache failure.'); }
                return $sql;
            };
            add_filter('query', $inject, 1); try { $failed = wa_call('windpress/cache', $input + wa_payload()); } finally { remove_filter('query', $inject, 1); }
            wa_check($fired && empty($failed['ok']), 'Injected cache failure must not report success.');
            if ($fault === 'external') { wa_check(($failed['changeset']['status'] ?? '') === 'needs_recovery' && file_get_contents($css_path) === '/* external fixture edit */', 'External edits must be preserved.'); file_put_contents($css_path, $old_css); }
            else wa_check(file_get_contents($css_path) === $old_css, 'Previous CSS must survive a failed store.');
            wa_check(file_get_contents($map_path) === $old_map && get_option($option, null) === $evidence, 'Map and evidence must survive a failed store.');
        }
        $hook_failure = static function () { throw new RuntimeException('Injected native cache hook failure.'); };
        add_action('a!windpress/core/cache:save_cache.after', $hook_failure, 1);
        try { $failed = wa_call('windpress/cache', $input + wa_payload()); } finally { remove_action('a!windpress/core/cache:save_cache.after', $hook_failure, 1); }
        wa_check(empty($failed['ok']) && !empty($failed['recovery']['cache_and_evidence_compensation_verified']) && file_get_contents($css_path) === $old_css && file_get_contents($map_path) === $old_map && get_option($option, null) === $evidence, 'Hook failure must compensate all owned artifacts.');
        $results['snapshot_evidence_journal_and_hook_failure'] = 'protected_or_compensated';
        $results['external_edit_during_failure'] = 'preserved_needs_recovery';
        $results['undo_acknowledgement_failure'] = 'compensated_for_retry';
        $source_fixture = $theme_root . '/' . $prefix . '.css'; wa_check(!file_exists($source_fixture), 'Source fixture collision.');
        $change_source = static function ($sql) use ($source_fixture, $table) {
            if (preg_match('/^\s*INSERT/i', $sql) && strpos($sql, $table) !== false) file_put_contents($source_fixture, '/* unused source revision fixture */');
            return $sql;
        };
        add_filter('query', $change_source, 1);
        try { $failed = wa_call('windpress/cache', $input + wa_payload()); }
        finally { remove_filter('query', $change_source, 1); if (is_file($source_fixture)) unlink($source_fixture); }
        wa_check(($failed['code'] ?? '') === 'stale_context' && !empty($failed['recovery']['cache_and_evidence_compensation_verified']) && file_get_contents($css_path) === $old_css && file_get_contents($map_path) === $old_map && get_option($option, null) === $evidence, 'Source changes during storage must preserve the previous artifacts.');
        $results['source_revision_race'] = 'rejected_previous_cache_preserved';
    }
} finally {
    foreach ([$css_path, $map_path] as $file) if (is_file($file)) unlink($file);
    $directory = dirname($css_path);
    while (strpos($directory, $fixture_dir) === 0 && is_dir($directory)) { if (!rmdir($directory)) break; if ($directory === $fixture_dir) break; $directory = dirname($directory); }
    foreach ($wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE owner_user_id=%d AND action='save_windpress_cache'", $owner), ARRAY_A) as $row) {
        $s = (new ReflectionMethod(LCFA_Changesets::class, 'open_snapshot'))->invoke(null, $row);
        if (strpos($s['target']['paths']['css'] ?? '', $prefix . '/') === 0) $wpdb->delete($table, ['id' => $row['id']]);
    }
    $remaining_evidence = get_option($option, null);
    if ($remaining_evidence !== $evidence) {
        wa_check(is_array($remaining_evidence) && ($remaining_evidence['cache_path'] ?? '') === $css_path, 'Unrelated compile evidence changed; no broad cleanup restore performed.');
        if ($evidence === null) delete_option($option); else update_option($option, $evidence);
    }
    remove_filter('upload_dir', $redirect, 1000);
    wa_check(wa_tree($real_cache_dir) === $cache_baseline, 'The served cache changed.');
    wa_check(wa_tree($theme_root) === $theme_baseline, 'Existing theme files changed.');
    wa_check($wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A) === $posts && get_option('theme_mods_' . get_stylesheet()) === $mods, 'Existing site content or theme metadata changed.');
    wa_check(get_option($option, null) === $evidence, 'Original compile evidence was not restored.');
}
echo wp_json_encode(['ok' => true, 'tests' => $results, 'served_cache_theme_content_and_evidence_unchanged' => true, 'fixtures_removed' => true, 'actual_coding_agent' => 'not_invoked'], JSON_PRETTY_PRINT) . "\n";
