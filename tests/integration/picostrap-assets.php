<?php
/** Isolated bundle destination, native Sass sources, no page/template/cache replacement. */
if (PHP_SAPI !== 'cli') exit(1);
$host = (string) getenv('LCFA_TEST_HOST'); $root = (string) getenv('LCFA_TEST_WP_ROOT');
if ($host !== 'test-ai-forge.local' || $root !== '/Users/commander/Local Sites/test-ai-forge/app/public') throw new RuntimeException('The authorized Picostrap test site is required.');
$GLOBALS['pa_fixture_filename'] = 'lcfa-compiled-fixture-' . bin2hex(random_bytes(6)) . '.css';
// Picostrap's documented pluggable helper affects this CLI process only. Web
// requests continue serving the real bundle.css; it is never overwritten.
function picostrap_get_complete_css_filename() { return $GLOBALS['pa_fixture_filename']; }
$_SERVER['HTTP_HOST'] = $host; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['SERVER_PORT'] = 80;
require $root . '/wp-load.php';
function pa_check($value, $message) { if (!$value) throw new RuntimeException($message); }
function pa_call($route, $payload) {
    $r = new WP_REST_Request('POST', '/lcfa/v1/' . $route);
    $r->set_header('Content-Type', 'application/json'); $r->set_body(wp_json_encode($payload));
    $body = rest_do_request($r)->get_data(); return $body['result'] ?? $body;
}
function pa_payload($fingerprint) {
    $c = LCFA_Write_Contract::prepare(['target_type' => 'site']); pa_check(!empty($c['ok']), 'Site context is required.');
    return ['write_context' => $c['write_context'], 'acknowledge_shared' => true, 'source_fingerprint' => $fingerprint];
}
function pa_undo($id) { return pa_call('changesets/undo', ['changeset_id' => $id] + pa_payload('')); }
function pa_tree($root) {
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $f) if ($f->isFile() && !$f->isLink()) $files[$f->getPathname()] = [hash_file('sha256', $f->getPathname()), $f->getPerms() & 0777];
    ksort($files); return $files;
}
global $wpdb;
$owner = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0] ?? 0); pa_check($owner > 0, 'Existing administrator required.'); wp_set_current_user($owner);
pa_check(wp_parse_url(home_url(), PHP_URL_HOST) === $host && (new LCFA_Environment())->detect_framework_family() === 'picostrap', 'Host/framework mismatch.');
$manifest = (new LCFA_Picostrap_Compile_Manifest(new LCFA_Environment()))->build();
$child = realpath(get_stylesheet_directory()); $path = $manifest['target_bundle_theme_path']; $absolute = $child . '/' . $path;
pa_check(!file_exists($absolute), 'Fixture destination already exists.');
$baseline = pa_tree($child); $posts = $wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A);
$option = 'theme_mods_' . get_stylesheet(); $mods = get_option($option); $table = $wpdb->prefix . 'lcfa_changesets';
$results = []; $failure = null;
try {
    $process = proc_open(['node', __DIR__ . '/picostrap-compile-fixture.js', $root], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    pa_check(is_resource($process), 'Dart Sass fixture could not start.'); fwrite($pipes[0], wp_json_encode($manifest)); fclose($pipes[0]);
    $compiled = json_decode(stream_get_contents($pipes[1]), true); fclose($pipes[1]); $errors = stream_get_contents($pipes[2]); fclose($pipes[2]);
    pa_check(proc_close($process) === 0 && !empty($compiled['css']), 'Sass compilation failed: ' . $errors);
    $css = $compiled['css']; $fingerprint = $compiled['source_fingerprint'];
    if (in_array('--probe-old', $argv, true)) {
        $probe = pa_call('picostrap/bundle', ['css' => $css] + pa_payload($fingerprint));
        echo wp_json_encode(['old_store_probe' => $probe, 'compiled_bytes' => $compiled['bytes']]) . "\n";
    } else {
        $store = new LCFA_Picostrap_Bundle_Store(new LCFA_Environment());
        pa_check(($store->store($css)['code'] ?? '') === 'context_required', 'Direct writes must require context.');
        $bad = pa_call('picostrap/bundle', ['css' => $css] + pa_payload(str_repeat('0', 64)));
        pa_check(($bad['code'] ?? '') === 'stale_compilation', 'Stale compilation must be rejected: ' . wp_json_encode($bad));
        $no_ack = pa_payload($fingerprint); $no_ack['acknowledge_shared'] = false;
        pa_check((pa_call('picostrap/bundle', ['css' => $css] + $no_ack)['code'] ?? '') === 'shared_impact_required', 'Shared scope acknowledgement is mandatory.');
        pa_check(!file_exists($absolute) && get_option($option) === $mods, 'Rejected writes must preserve all state.');
        $payload = ['css' => $css] + pa_payload($fingerprint);
        $save = pa_call('picostrap/bundle', $payload);
        pa_check(!empty($save['ok']) && !empty($save['changeset']['undo_available']), 'Compiled bundle storage needs a journal: ' . wp_json_encode($save));
        $id = $save['changeset']['id'];
        pa_check(file_get_contents($absolute) === $css && get_theme_mod('lcfa_picostrap_compiled_source_fingerprint') === $fingerprint, 'Saved CSS/evidence mismatch.');
        pa_check(($save['verification_states']['compiled'] ?? '') === 'client_reported' && ($save['verification_states']['visually_verified'] ?? '') === 'not_checked', 'Server must distinguish compiler claims from visual checks.');
        pa_check((pa_call('picostrap/bundle', $payload)['code'] ?? '') === 'stale_context', 'Reused site context must be rejected.');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%s", $id), ARRAY_A);
        pa_check(strpos($row['before_snapshot'], $GLOBALS['pa_fixture_filename']) === false, 'Bundle journal must be encrypted.');
        $wpdb->update($table, ['owner_user_id' => $owner + 1000000], ['id' => $id]);
        pa_check((pa_undo($id)['code'] ?? '') === 'changeset_not_found', 'Foreign owner must not restore a bundle.');
        $wpdb->update($table, ['owner_user_id' => $owner], ['id' => $id]);
        file_put_contents($absolute, $css . '/* external fixture edit */');
        pa_check((pa_undo($id)['code'] ?? '') === 'changeset_conflict', 'Changed CSS must block Undo.'); file_put_contents($absolute, $css);
        $saved_at = get_theme_mod('lcfa_picostrap_compiled_at'); set_theme_mod('lcfa_picostrap_compiled_at', 'external fixture edit');
        pa_check((pa_undo($id)['code'] ?? '') === 'changeset_conflict', 'Changed compile evidence must block Undo.'); set_theme_mod('lcfa_picostrap_compiled_at', $saved_at);
        $fired = false;
        $reject_ack = static function ($sql) use ($table, &$fired) {
            if (!$fired && preg_match('/^\s*UPDATE/i', $sql) && strpos($sql, $table) !== false && strpos($sql, "'undone'") !== false) { $fired = true; throw new RuntimeException('changeset_journal_failed: Injected Undo acknowledgement failure.'); }
            return $sql;
        };
        add_filter('query', $reject_ack, 1); try { $failed = pa_undo($id); } finally { remove_filter('query', $reject_ack, 1); }
        pa_check($fired && ($failed['changeset']['status'] ?? '') === 'saved' && !empty($failed['recovery']['bundle_and_metadata_compensation_verified']) && file_get_contents($absolute) === $css, 'Failed Undo must restore the saved bundle and metadata for retry.');
        $fixture_mod = 'lcfa_fixture_' . substr($GLOBALS['pa_fixture_filename'], 0, -4); set_theme_mod($fixture_mod, 'preserve me');
        $undo = wp_get_ability('livecanvas-forge-ai/undo-changeset')->execute(['changeset_id' => $id] + pa_payload(''));
        pa_check(!is_wp_error($undo) && !empty($undo['ok']) && !file_exists($absolute) && get_theme_mod($fixture_mod) === 'preserve me', 'Remote Undo must restore the bundle without reverting unrelated theme mods.'); remove_theme_mod($fixture_mod);
        pa_check(get_option($option) === $mods, 'Undo must restore exact compile metadata.');
        $results['native_sass_compilation_and_rest_store'] = $compiled['bytes'];
        $results['stale_context_source_shared_ack_and_owner'] = 'rejected';
        $results['css_and_metadata_conflicts'] = 'rejected';
        $results['remote_undo_and_unrelated_mod_preservation'] = 'passed';
        $results['undo_ack_failure'] = 'compensated';

        $previous_css = '/* previously valid fixture cache */ .old-fixture{display:block}';
        file_put_contents($absolute, $previous_css); chmod($absolute, 0640);
        $ability = wp_get_ability('livecanvas-forge-ai/picostrap-compile-apply');
        $remote_failed = $ability->execute(['compiled_css' => $css] + pa_payload(str_repeat('0', 64)));
        pa_check(!is_wp_error($remote_failed) && empty($remote_failed['picostrap_compile_apply']['ok']) && ($remote_failed['picostrap_compile_apply']['code'] ?? '') === 'stale_compilation', 'Remote Ability must not wrap a failed store in ok=true.');
        $remote = $ability->execute(['compiled_css' => $css] + pa_payload($fingerprint));
        pa_check(!is_wp_error($remote) && !empty($remote['picostrap_compile_apply']['ok']), 'Remote Ability must store a valid bundle.');
        pa_check(!empty(pa_undo($remote['picostrap_compile_apply']['changeset']['id'])['ok']), 'REST must Undo the remote Ability bundle.');
        pa_check(file_get_contents($absolute) === $previous_css && (fileperms($absolute) & 0777) === 0640, 'Existing bundle bytes and permissions must be restored exactly.');
        $results['remote_store_failure_and_rest_undo_parity'] = 'passed';

        foreach (['metadata', 'journal', 'external'] as $fault) {
            $fired = false;
            $inject = static function ($sql) use ($fault, &$fired, $option, $table, $absolute) {
                $match = preg_match('/^\s*UPDATE/i', $sql) && ($fault === 'metadata' ? strpos($sql, $option) !== false : strpos($sql, $table) !== false && strpos($sql, "'saved'") !== false);
                if (!$fired && $match) {
                    $fired = true;
                    if ($fault === 'external') file_put_contents($absolute, '/* preserve external fixture CSS */');
                    throw new RuntimeException('changeset_journal_failed: Injected asset fault.');
                }
                return $sql;
            };
            add_filter('query', $inject, 1); try { $failed = pa_call('picostrap/bundle', ['css' => $css] + pa_payload($fingerprint)); } finally { remove_filter('query', $inject, 1); }
            pa_check($fired && empty($failed['ok']), 'Injected fault must not report success.');
            if ($fault === 'external') {
                pa_check(($failed['changeset']['status'] ?? '') === 'needs_recovery' && file_get_contents($absolute) === '/* preserve external fixture CSS */', 'Compensation must preserve external CSS.'); file_put_contents($absolute, $previous_css);
            } else pa_check(!empty($failed['recovery']['bundle_and_metadata_compensation_verified']) && file_get_contents($absolute) === $previous_css, 'Failure must preserve the previous valid bundle and metadata.');
            pa_check(get_option($option) === $mods, 'Failure compensation must preserve metadata.');
        }
        $results['metadata_and_journal_failure_compensation'] = 'passed';
        $results['external_edit_on_failure'] = 'preserved_needs_recovery';
        $reject_snapshot = static function ($sql) use ($table) {
            if (preg_match('/^\s*INSERT/i', $sql) && strpos($sql, $table) !== false) throw new RuntimeException('changeset_snapshot_failed: Injected private snapshot failure.');
            return $sql;
        };
        add_filter('query', $reject_snapshot, 1); try { $failed = pa_call('picostrap/bundle', ['css' => $css] + pa_payload($fingerprint)); } finally { remove_filter('query', $reject_snapshot, 1); }
        pa_check(($failed['code'] ?? '') === 'changeset_snapshot_failed' && file_get_contents($absolute) === $previous_css && get_option($option) === $mods, 'Missing durable snapshot must prevent any mutation.');
        $results['private_snapshot_failure'] = 'no_write';
        $source_fixture = $child . '/sass/' . $GLOBALS['pa_fixture_filename'] . '.scss';
        pa_check(!file_exists($source_fixture), 'Source fixture collision.');
        $source_race = static function ($sql) use ($table, $source_fixture) {
            if (preg_match('/^\s*INSERT/i', $sql) && strpos($sql, $table) !== false) file_put_contents($source_fixture, '/* isolated source revision change */');
            return $sql;
        };
        add_filter('query', $source_race, 1);
        try { $failed = pa_call('picostrap/bundle', ['css' => $css] + pa_payload($fingerprint)); }
        finally { remove_filter('query', $source_race, 1); if (is_file($source_fixture)) unlink($source_fixture); }
        pa_check(($failed['code'] ?? '') === 'stale_context' && !empty($failed['recovery']['bundle_and_metadata_compensation_verified']) && file_get_contents($absolute) === $previous_css, 'Source changes during storage must reject the build and preserve the previous cache.');
        $results['source_revision_race'] = 'rejected_previous_cache_preserved';
        $legacy = $store->restore(['bundle_created' => true]);
        pa_check(($legacy['code'] ?? '') === 'legacy_restore_review_required', 'Legacy arbitrary restore must be blocked.');
    }
} finally {
    if (is_file($absolute)) unlink($absolute);
    // Remove only this fixture's private journal records.
    foreach ($wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE owner_user_id=%d AND action='store_picostrap_bundle'", $owner), ARRAY_A) as $row) {
        $s = (new ReflectionMethod(LCFA_Changesets::class, 'open_snapshot'))->invoke(null, $row);
        if (($s['path'] ?? '') === $path) $wpdb->delete($table, ['id' => $row['id']]);
    }
    $current = get_option($option);
    if ($current !== $mods) {
        $keys = array_flip(['css_bundle_version_number', 'lcfa_picostrap_compiled_source_fingerprint', 'lcfa_picostrap_compiled_at']);
        pa_check(array_diff_key($current, $keys) === array_diff_key($mods, $keys), 'Unrelated theme mods changed; manual review required, no broad restore performed.');
        update_option($option, $mods);
    }
    pa_check(pa_tree($child) === $baseline, 'Existing theme files changed.');
    pa_check($wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A) === $posts, 'Existing content changed.');
    pa_check(get_option($option) === $mods, 'Original theme metadata was not restored.');
}
echo wp_json_encode(['ok' => true, 'tests' => $results, 'existing_theme_content_and_metadata_unchanged' => true, 'fixture_removed' => true, 'actual_coding_agent' => 'not_invoked'], JSON_PRETTY_PRINT) . "\n";
