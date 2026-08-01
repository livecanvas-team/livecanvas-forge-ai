<?php

declare(strict_types=1);

final class PackageDistAssertionFailure extends RuntimeException {}

function package_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        throw new PackageDistAssertionFailure($message);
    }
}

function package_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new PackageDistAssertionFailure($message . ' Expected `' . var_export($expected, true) . '`, got `' . var_export($actual, true) . '`.');
    }
}

$zip_path = dirname(__DIR__, 2) . '/dist/livecanvas-forge-ai.zip';
package_assert_true(is_file($zip_path), 'distribution zip should exist');

$zip = new ZipArchive();
package_assert_same(true, $zip->open($zip_path) === true, 'distribution zip should be readable');

$entries = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $entries[] = $zip->getNameIndex($i);
}
$plugin_bootstrap = (string) $zip->getFromName('livecanvas-forge-ai/livecanvas-forge-ai.php');
$zip->close();

$root_entries = [];
foreach ($entries as $entry) {
    $trimmed = rtrim((string) $entry, '/');
    if ($trimmed === '') {
        continue;
    }

    if (strpos($trimmed, '/') === false) {
        $root_entries[] = $trimmed;
    }
}

sort($root_entries);

package_assert_same(['livecanvas-forge-ai'], $root_entries, 'distribution zip should expose a single top-level plugin directory');
package_assert_true(in_array('livecanvas-forge-ai/livecanvas-forge-ai.php', $entries, true), 'distribution zip should include the main plugin file inside the plugin directory');
package_assert_true(in_array('livecanvas-forge-ai/assets/plugin-icon-128.png', $entries, true), 'distribution zip should include the AI Bridge 1x plugin icon');
package_assert_true(in_array('livecanvas-forge-ai/assets/plugin-icon-256.png', $entries, true), 'distribution zip should include the AI Bridge 2x plugin icon');
package_assert_true(in_array('livecanvas-forge-ai/LICENSE.md', $entries, true), 'distribution zip should include the plugin license');
package_assert_true(in_array('livecanvas-forge-ai/composer.json', $entries, true), 'distribution zip should include the Composer manifest');
package_assert_true(in_array('livecanvas-forge-ai/composer.lock', $entries, true), 'distribution zip should include the Composer lock file');
package_assert_true(in_array('livecanvas-forge-ai/vendor/autoload.php', $entries, true), 'distribution zip should include the production Composer autoloader');
package_assert_true(in_array('livecanvas-forge-ai/vendor/league/oauth2-server/src/AuthorizationServer.php', $entries, true), 'distribution zip should include the OAuth server runtime');
package_assert_true(in_array('livecanvas-forge-ai/vendor/nyholm/psr7/src/ServerRequest.php', $entries, true), 'distribution zip should include the PSR-7 runtime');
package_assert_true(in_array('livecanvas-forge-ai/docs/coding-agent-setup.html', $entries, true), 'distribution zip should include the four-step coding-agent guide');
package_assert_true(strpos($plugin_bootstrap, 'Update URI: https://livecanvas.com/ai-bridge') !== false, 'distribution zip should preserve the LiveCanvas Update URI header');
package_assert_true(!in_array('livecanvas-forge-ai.php', $entries, true), 'distribution zip should not leak the plugin bootstrap at the archive root');
package_assert_true(!in_array('.git/', $entries, true), 'distribution zip should not include git metadata');
package_assert_true(!in_array('.claude/', $entries, true), 'distribution zip should not include local assistant metadata');
package_assert_true(!in_array('tests/', $entries, true), 'distribution zip should not expose tests at the archive root');
package_assert_true(!in_array('livecanvas-forge-ai/tests/', $entries, true), 'distribution zip should not include tests inside the plugin package');
foreach ($entries as $entry) {
    package_assert_true(strpos((string) $entry, 'livecanvas-forge-ai/mcp/node_modules/') !== 0, 'distribution zip should not include bundled MCP node_modules');
    package_assert_true(strpos((string) $entry, 'livecanvas-forge-ai/vendor/bin/') !== 0, 'distribution zip should not include Composer executable shims');
    package_assert_true(strpos((string) $entry, 'livecanvas-forge-ai/docs/screenshots/') !== 0, 'distribution zip should not include documentation screenshots');
    package_assert_true(substr((string) $entry, -9) !== '.DS_Store', 'distribution zip should not include macOS metadata files');
}

echo "PASS\n";
