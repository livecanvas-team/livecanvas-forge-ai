<?php
/** Production header with synthetic data only. */
ob_start(); require dirname(__DIR__) . '/php/admin_hero_phase1.php'; ob_end_clean();
echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/admin.css"><link rel="stylesheet" href="/assets/admin-v2.css"><style>body{margin:0;background:#12131c;font:16px system-ui}.lcfa-admin{margin:0;padding:24px}</style></head><body><main class="lcfa-admin">';
echo $output;
echo '<p>Synthetic test. No coding agent is connected.</p></main></body></html>';
