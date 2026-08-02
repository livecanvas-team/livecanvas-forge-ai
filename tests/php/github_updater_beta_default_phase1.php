<?php

declare(strict_types=1);

define('ABSPATH', '/tmp/lcfa-updater-beta-default/');
define('LCFA_VERSION', '0.2.0-beta.1');

$GLOBALS['lcfa_beta_default_channel'] = '';

function get_option(string $name, $default = false) {
    if ($name === 'lcfa_update_channel' && $GLOBALS['lcfa_beta_default_channel'] !== '') {
        return $GLOBALS['lcfa_beta_default_channel'];
    }

    return $default;
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-github-updater.php';

$updater = new LCFA_GitHub_Updater();
if ($updater->get_update_channel() !== 'beta') {
    fwrite(STDERR, "A beta installation should follow the beta channel by default.\n");
    exit(1);
}

$GLOBALS['lcfa_beta_default_channel'] = 'stable';
if ($updater->get_update_channel() !== 'stable') {
    fwrite(STDERR, "An administrator should be able to move a beta installation back to stable.\n");
    exit(1);
}

echo "PASS\n";
