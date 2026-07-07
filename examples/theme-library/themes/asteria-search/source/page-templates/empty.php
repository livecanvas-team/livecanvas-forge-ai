<?php
/**
 * Template Name: Empty Page Template
 *
 * @package LiveCanvas\AI\AsteriaSearch
 */

declare(strict_types=1);

namespace PicowindDeps;

\defined('ABSPATH') || exit;

$context = \Picowind\context();
$timber_post = \PicowindDeps\Timber\Timber::get_post();
$context['post'] = $timber_post;
\Picowind\render(\Picowind\template_fallbacks('page-templates/empty'), $context);
