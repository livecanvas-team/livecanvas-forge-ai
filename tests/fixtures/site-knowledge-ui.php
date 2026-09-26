<?php
/** Synthetic browser fixture; never loads WordPress or personal site data. */
ob_start(); require dirname(__DIR__) . '/php/site_knowledge_phase1.php'; ob_end_clean();
$wpdb = new KnowledgeDB();
$state = $argv[1] ?? 'empty';
if ($state === 'approved' || $state === 'conflict') LCFA_Site_Knowledge::review('Use concise English. Keep the site palette.', LCFA_Site_Knowledge::fingerprint(), 'review');
if ($state === 'unavailable') $wpdb->last_error = 'synthetic storage failure';
echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/admin.css"><style>body{margin:0;background:#12131c;font:16px system-ui}.lcfa-admin{margin:0;padding:24px}main{max-width:900px;margin:auto}textarea{box-sizing:border-box;max-width:100%;font:inherit}button{font:inherit}summary:focus-visible,button:focus-visible,textarea:focus-visible{outline:2px solid #2cc5d8;outline-offset:3px}</style></head><body><div class="lcfa-admin"><main><h1>Site instructions test</h1><p>Synthetic fixture. No agent is connected.</p>';
LCFA_Site_Knowledge::render_controls($state === 'conflict' || $state === 'unavailable' ? 'Preserve this draft: ' . str_repeat('LongFixtureName', 15) . ' 日本語 العربية ☀️' : null,
    $state === 'conflict' ? 'Someone changed the shared instructions. Review both versions before saving.' : '');
echo '</main></div></body></html>';
