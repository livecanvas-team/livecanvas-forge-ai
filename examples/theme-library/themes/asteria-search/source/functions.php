<?php

declare (strict_types=1);
namespace PicowindDeps;

/**
 * @package Picowind Child
 * @subpackage Picowind
 * @since 1.0.0
 */
\defined('ABSPATH') || exit;
if (\file_exists(__DIR__ . '/vendor/autoload.php')) {
    if (\file_exists(__DIR__ . '/vendor/scoper-autoload.php')) {
        require_once __DIR__ . '/vendor/scoper-autoload.php';
    } else {
        require_once __DIR__ . '/vendor/autoload.php';
    }
}
/* You can add your custom functions below this line */

/**
 * Resolve Asteria's one-page navigation against the real WordPress homepage.
 *
 * Header and footer partials are shared by every page. Plain fragment links
 * would otherwise resolve against the current page (for example,
 * /contact/#thesis) instead of the front page.
 */
function asteria_resolve_home_anchors(string $content): string
{
    foreach (['asteria-page', 'thesis', 'engagements', 'work'] as $anchor) {
        $home_anchor = \esc_url(\home_url('/#' . $anchor));
        $content = \str_replace(
            ['href="#' . $anchor . '"', 'href="/#' . $anchor . '"'],
            'href="' . $home_anchor . '"',
            $content
        );
    }

    return $content;
}

\add_filter('lc_modify_header_content', __NAMESPACE__ . '\\asteria_resolve_home_anchors');
\add_filter('lc_modify_footer_content', __NAMESPACE__ . '\\asteria_resolve_home_anchors');
