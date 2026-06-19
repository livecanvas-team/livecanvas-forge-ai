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
package_assert_true(strpos($plugin_bootstrap, 'Update URI: https://livecanvas.com/ai-bridge') !== false, 'distribution zip should preserve the LiveCanvas Update URI header');
package_assert_true(!in_array('livecanvas-forge-ai.php', $entries, true), 'distribution zip should not leak the plugin bootstrap at the archive root');
package_assert_true(!in_array('.git/', $entries, true), 'distribution zip should not include git metadata');
package_assert_true(!in_array('.claude/', $entries, true), 'distribution zip should not include local assistant metadata');
package_assert_true(!in_array('tests/', $entries, true), 'distribution zip should not expose tests at the archive root');
package_assert_true(!in_array('livecanvas-forge-ai/tests/', $entries, true), 'distribution zip should not include tests inside the plugin package');
package_assert_true(!in_array('livecanvas-forge-ai/docs/', $entries, true), 'distribution zip should not include internal docs inside the plugin package');

echo "PASS\n";
