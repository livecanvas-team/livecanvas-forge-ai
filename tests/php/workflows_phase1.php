<?php
declare(strict_types=1);
define('ABSPATH', '/tmp/lcfa-workflows/');
require dirname(__DIR__, 2) . '/includes/class-lcfa-workflows.php';
function workflow_assert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$catalog = LCFA_Workflows::catalog();
workflow_assert($catalog['ok'] === true, 'Catalog must load from the installed package.');
workflow_assert(count($catalog['workflows']) === 5, 'All planned workflow categories must be discoverable.');
$seen = [];
foreach ($catalog['workflows'] as $item) {
    workflow_assert(!isset($item['instructions']), 'Discovery must not preload every workflow body.');
    workflow_assert(!isset($seen[$item['id']]), 'IDs must be unique.');
    $seen[$item['id']] = true;
    $read = LCFA_Workflows::read($item['id']);
    workflow_assert($read['ok'] === true, 'Every descriptor must have a readable body.');
    workflow_assert($item['sha256'] === hash('sha256', $read['workflow']['instructions']), 'Version cache must include the delivered common and specific instructions.');
    workflow_assert(strpos($read['workflow']['instructions'], 'get_write_context') !== false, 'Every workflow must include mandatory context.');
}
foreach (['../shared', 'page/../../wp-config.php', '', 'PAGE', 'page%00', 'does-not-exist'] as $id) {
    workflow_assert(LCFA_Workflows::read($id)['code'] === 'unknown_workflow', 'Only exact catalog IDs may be read.');
}
workflow_assert(LCFA_Workflows::discovery()['catalog'] === $catalog, 'Startup discovery must use the same catalog.');
echo "PASS workflows_phase1\n";
