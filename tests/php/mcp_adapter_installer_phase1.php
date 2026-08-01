<?php

declare(strict_types=1);

function mcp_adapter_installer_assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function mcp_adapter_installer_assert_not_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
$installer = (string) file_get_contents($root . '/includes/class-lcfa-installer.php');
$admin = (string) file_get_contents($root . '/includes/class-lcfa-admin.php');

mcp_adapter_installer_assert_contains(
    'https://github.com/WordPress/mcp-adapter/releases/latest/download/mcp-adapter.zip',
    $installer,
    'MCP Adapter installer must use the official production release asset'
);
mcp_adapter_installer_assert_contains(
    'lcfa_mcp_adapter_package_url',
    $installer,
    'MCP Adapter package URL should remain filterable for managed hosting'
);
mcp_adapter_installer_assert_not_contains(
    'MCP_ADAPTER_LATEST_RELEASE_API',
    $installer,
    'MCP Adapter installer must not fall back to a GitHub source zipball without release dependencies'
);
mcp_adapter_installer_assert_contains(
    'admin_post_lcfa_install_mcp_adapter',
    $admin,
    'Connections must expose an explicit admin-only MCP Adapter installation action'
);
mcp_adapter_installer_assert_contains(
    'current_user_can(\'install_plugins\')',
    $admin,
    'MCP Adapter installation must require the WordPress plugin installation capability'
);

echo "PASS\n";
