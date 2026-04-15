<?php

declare(strict_types=1);

error_reporting(E_ALL);

$css = file_get_contents('/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.css');

if ($css === false) {
    fwrite(STDERR, "failed to read admin.css\n");
    exit(1);
}

function lcfa_assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

lcfa_assert_contains(
    ".lcfa-admin .lcfa-agent-guide__panel-grid--bundle {\n    grid-template-columns: repeat(2, minmax(0, 1fr));",
    $css,
    'bundle grid should use an explicit two-column layout'
);

echo "PASS\n";
