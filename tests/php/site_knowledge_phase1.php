<?php
define('ABSPATH', sys_get_temp_dir() . '/'); define('ARRAY_A', 'ARRAY_A');
$user = 7; $admin = true; $host = 'fixture.local';
function get_current_user_id() { global $user; return $user; }
function current_user_can($cap) { global $admin; return $admin; }
function wp_verify_nonce($nonce, $action) { return $nonce === 'review' && $action === 'lcfa_site_knowledge'; }
function sanitize_textarea_field($text) { return strip_tags($text); }
function home_url($path) { global $host; return 'http://' . $host . $path; }
function get_current_blog_id() { return 1; }
function wp_cache_delete(...$args) {}
function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return esc_html($text); }
function esc_textarea($text) { return esc_html($text); }
function esc_url($text) { return esc_html($text); }
function esc_html__($text, $domain) { return esc_html($text); }
function __($text, $domain) { return $text; }
function admin_url($path) { return 'http://fixture.local/wp-admin/' . $path; }
function wp_nonce_field($action) { echo '<input type="hidden" name="_wpnonce" value="review">'; }
class LCFA_MCP_Session_Manager { public static array $identity = []; public static function current_worker_identity() { return self::$identity; } }
class KnowledgeDB {
    public $options = 'wp_options', $last_error = '', $row = null, $fail_write = false, $lose_read = false;
    function prepare($sql, ...$args) { return [$sql, $args]; }
    function get_row($query, $format) { if ($this->lose_read) { $this->lose_read = false; return null; } return $this->row; }
    function suppress_errors($value) { return false; }
    function insert($table, $value) { if ($this->fail_write || $this->row) return false; $this->row = ['option_id' => 1, 'option_value' => $value['option_value']]; return 1; }
    function query($query) {
        if ($this->fail_write) return false;
        [$sql, $args] = $query;
        if (!str_contains($sql, 'BINARY option_value=BINARY')) throw new RuntimeException('CAS missing');
        if ($this->row['option_value'] !== $args[2]) return 0;
        $this->row['option_value'] = $args[0]; return 1;
    }
}
$wpdb = new KnowledgeDB();
require dirname(__DIR__, 2) . '/includes/class-lcfa-site-knowledge.php';
function sk_check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function sk_reject($call, $code) { try { $call(); } catch (RuntimeException $e) { sk_check($e->getMessage() === $code, $e->getMessage()); return; } throw new RuntimeException('Expected ' . $code); }
$initial = LCFA_Site_Knowledge::read()['site_knowledge'];
sk_check($initial['state'] === 'not_shared' && $initial['instructions'] === '', 'No implicit import.');
foreach ([['user', 0], ['admin', false]] as [$key, $value]) {
    $old = $GLOBALS[$key]; $GLOBALS[$key] = $value;
    sk_reject(fn() => LCFA_Site_Knowledge::review('Fixture', $initial['revision'], 'review'), 'knowledge_admin_review_required'); $GLOBALS[$key] = $old;
}
sk_reject(fn() => LCFA_Site_Knowledge::review('Fixture', $initial['revision'], 'wrong'), 'knowledge_admin_review_required');
LCFA_MCP_Session_Manager::$identity = ['session_id' => 'fixture'];
sk_reject(fn() => LCFA_Site_Knowledge::review('Fixture', $initial['revision'], 'review'), 'knowledge_admin_review_required');
LCFA_MCP_Session_Manager::$identity = [];
foreach ([str_repeat('x', 12001), "bad\0text", "\xff"] as $text) sk_reject(fn() => LCFA_Site_Knowledge::review($text, $initial['revision'], 'review'), 'knowledge_text_invalid');
$approved = LCFA_Site_Knowledge::review("<b>Use concise English.</b>\nKeep café names.", $initial['revision'], 'review')['site_knowledge'];
sk_check($approved['state'] === 'approved' && $approved['instructions'] === "Use concise English.\nKeep café names.", 'Plain text and UTF-8.');
sk_check(!$approved['permissions_granted'] && !$approved['technical_context_verified'] && !$approved['private_conversations_included'], 'No trust or authority expansion.');
sk_reject(fn() => LCFA_Site_Knowledge::review('Overwrite', $initial['revision'], 'review'), 'knowledge_revision_conflict');
$wpdb->fail_write = true;
sk_reject(fn() => LCFA_Site_Knowledge::review('Failed', $approved['revision'], 'review'), 'knowledge_storage_failed');
sk_check(LCFA_Site_Knowledge::read()['site_knowledge'] === $approved, 'Failed storage preserved approved state.'); $wpdb->fail_write = false;
$withdrawn = LCFA_Site_Knowledge::review('', $approved['revision'], 'review')['site_knowledge'];
sk_check($withdrawn['state'] === 'not_shared' && $withdrawn['instructions'] === '' && $withdrawn['revision'] !== $initial['revision'], 'Withdrawal retains a revision tombstone.');
$host = 'other.local'; sk_check(!LCFA_Site_Knowledge::read()['ok'], 'Copied options cannot authorize another site.'); $host = 'fixture.local';
$wpdb->row['option_value'] = 'damaged'; sk_check(!LCFA_Site_Knowledge::read()['ok'], 'Corruption fails closed.');
ob_start(); LCFA_Site_Knowledge::render_controls('</textarea><script>private</script>', 'Storage failed'); $html = ob_get_clean();
sk_check(strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;') !== false && strpos($html, 'Your unsaved draft') !== false, 'Failure preserves escaped draft.');
echo "PASS approved site context, explicit admin review, stale edits, withdrawal, storage failure and scope isolation\n";
