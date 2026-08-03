<?php

defined('ABSPATH') || exit;

function lc_define_editor_config($key) {
    $config = [
        'config_file_slug' => 'bootstrap-5.3',
    ];

    return $config[$key] ?? null;
}
