<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

require_once __DIR__ . '/livecanvas/configuration.php';

add_action('wp_print_scripts', static function (): void {
    wp_dequeue_script('bootstrap5');
}, 100);

add_action('wp_enqueue_scripts', static function (): void {
    $bootstrap = __DIR__ . '/js/bootstrap.bundle.min.js';
    $custom = __DIR__ . '/js/custom.js';

    wp_enqueue_script(
        'hearthline-bootstrap',
        get_stylesheet_directory_uri() . '/js/bootstrap.bundle.min.js',
        [],
        is_file($bootstrap) ? (string) filemtime($bootstrap) : '1.0.0',
        ['strategy' => 'defer', 'in_footer' => true]
    );
    wp_enqueue_script(
        'hearthline-interactions',
        get_stylesheet_directory_uri() . '/js/custom.js',
        [],
        is_file($custom) ? (string) filemtime($custom) : '1.0.0',
        ['strategy' => 'defer', 'in_footer' => true]
    );
}, 101);
