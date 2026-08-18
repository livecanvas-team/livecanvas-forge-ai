<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');

final class WP_Theme {
    private string $stylesheet;
    private string $template;
    private string $name;
    private ?self $parent;

    public function __construct(string $stylesheet, string $template, string $name, ?self $parent = null) {
        $this->stylesheet = $stylesheet;
        $this->template = $template;
        $this->name = $name;
        $this->parent = $parent;
    }

    public function get(string $field): string {
        $values = [
            'Name' => $this->name,
            'TextDomain' => $this->stylesheet,
            'Version' => '1.0.0',
        ];

        return $values[$field] ?? '';
    }

    public function get_stylesheet(): string {
        return $this->stylesheet;
    }

    public function get_template(): string {
        return $this->template;
    }

    public function parent(): ?self {
        return $this->parent;
    }
}

$picowind_parent = new WP_Theme('picowind', 'picowind', 'Picowind');
$GLOBALS['lcfa_test_themes'] = [
    'picowind-child' => new WP_Theme('picowind-child', 'picowind', 'Picowind Child', $picowind_parent),
    'picowind-child-1' => new WP_Theme('picowind-child-1', 'picowind', 'Picowind Base', $picowind_parent),
    'picowind' => $picowind_parent,
];
$GLOBALS['lcfa_test_active_theme'] = 'picowind-child-1';

function wp_get_themes(): array {
    return $GLOBALS['lcfa_test_themes'];
}

function wp_get_theme(): WP_Theme {
    return $GLOBALS['lcfa_test_themes'][$GLOBALS['lcfa_test_active_theme']];
}

require LCFA_DIR . 'includes/class-lcfa-environment.php';

$environment = new LCFA_Environment();

if ($environment->get_preferred_theme_stylesheet('picowind') !== 'picowind-child-1') {
    fwrite(STDERR, "setup should preserve the active compatible Picowind child theme\n");
    exit(1);
}

$GLOBALS['lcfa_test_active_theme'] = 'picowind';
if ($environment->get_preferred_theme_stylesheet('picowind') !== 'picowind') {
    fwrite(STDERR, "setup should preserve the active compatible parent theme\n");
    exit(1);
}

echo "PASS\n";
