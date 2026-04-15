<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');

function __(string $text, string $domain = ''): string {
    return $text;
}

final class LCFA_Settings {
    public static function get(): array {
        return [];
    }
}

final class LCFA_Environment {}
final class LCFA_Inventory {}
final class LCFA_WindPress_Bridge {}
final class LCFA_Theme_Files_Bridge {}
final class LCFA_Local_MCP_Bridge {}
final class LCFA_Remote_Client {}
final class LCFA_Design_System_Compose {}
final class LCFA_Design_System_Apply {}
final class LCFA_Design_System_Picostrap_Executor {}
final class LCFA_Design_System_Picowind_Executor {
    public function __construct(...$args) {
    }
}
final class LCFA_Design_System_Build_Gateway {
    public function __construct(...$args) {
    }
}
final class LCFA_Design_System_Picostrap_Composer {}
final class LCFA_Design_System_Preview {}

function lcfa_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-command-deck.php';

$reflection = new ReflectionClass('LCFA_Command_Deck');
$instance = $reflection->newInstanceWithoutConstructor();
$method = new ReflectionMethod('LCFA_Command_Deck', 'evaluate_policy');

$pagePolicy = $method->invoke($instance, 'page_upsert', false);
lcfa_assert_same('advanced_templates', $pagePolicy['profile'] ?? null, 'command deck should default to advanced_templates when settings are missing');
lcfa_assert_same(true, $pagePolicy['allow_file_fallback'] ?? null, 'command deck should default to file fallback enabled when settings are missing');
lcfa_assert_same(false, $pagePolicy['force_preview'] ?? null, 'command deck should not downgrade normal apply actions when settings are missing');

$filePolicy = $method->invoke($instance, 'write_theme_file', false);
lcfa_assert_same(true, $filePolicy['allow_file_fallback'] ?? null, 'command deck should allow file fallback actions by default');
lcfa_assert_same(false, $filePolicy['force_preview'] ?? null, 'command deck should not preview-only file fallback actions by default');

echo "PASS\n";
