<?php

defined('ABSPATH') || exit;

final class LCFA_Design_System_Preview {
    public const OPTION_KEY = 'lcfa_design_system_preview_payload';

    public function hooks(): void {
        add_action('template_redirect', [$this, 'maybe_render']);
    }

    public function store(array $payload): string {
        update_option(self::OPTION_KEY, [
            'saved_at' => current_time('mysql', true),
            'payload' => $payload,
        ]);

        return $this->build_preview_url();
    }

    public function build_preview_url(): string {
        return home_url('/?lcfa_design_preview=1');
    }

    public function maybe_render(): void {
        if (empty($_GET['lcfa_design_preview'])) {
            return;
        }

        $state = get_option(self::OPTION_KEY, []);
        $payload = is_array($state['payload'] ?? null) ? $state['payload'] : [];

        if (function_exists('status_header')) {
            status_header(200);
        }

        if (function_exists('nocache_headers')) {
            nocache_headers();
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo $this->render_preview_html($payload);
        exit;
    }

    public function render_preview_html(array $payload): string {
        $preview = is_array($payload['preview'] ?? null) ? $payload['preview'] : [];
        $palette = is_array($preview['palette'] ?? null) ? $preview['palette'] : [];
        $typography = is_array($preview['typography'] ?? null) ? $preview['typography'] : [];
        $radius = is_array($preview['radius'] ?? null) ? $preview['radius'] : [];
        $buttons = is_array($preview['buttons'] ?? null) ? $preview['buttons'] : [];
        $warnings = array_values(array_filter(array_map('strval', (array) ($payload['warnings'] ?? []))));
        $compile_url = (string) ($payload['compile_url'] ?? '');
        $mood = (string) ($preview['mood'] ?? '');
        $summary = (string) ($payload['summary'] ?? 'Design system preview');
        $bodyBg = (string) ($palette['body_bg'] ?? '#ffffff');
        $bodyColor = (string) ($palette['body_color'] ?? '#111827');
        $headingFont = (string) ($typography['headings_font_family'] ?? 'inherit');
        $bodyFont = (string) ($typography['font_family_base'] ?? 'inherit');
        $buttonRadius = (string) ($buttons['btn_border_radius'] ?? ($radius['border_radius'] ?? '0.75rem'));
        $buttonPaddingY = (string) ($buttons['btn_padding_y'] ?? '0.75rem');
        $buttonPaddingX = (string) ($buttons['btn_padding_x'] ?? '1.25rem');
        $primary = (string) ($palette['primary'] ?? '#2563eb');
        $secondary = (string) ($palette['secondary'] ?? '#64748b');
        $light = (string) ($palette['light'] ?? '#f8fafc');
        $dark = (string) ($palette['dark'] ?? '#0f172a');

        $swatches = '';
        foreach ($palette as $name => $value) {
            $swatches .= '<div class="lcfa-swatch"><span class="lcfa-swatch-chip" style="background:' . $this->escape_attr($value) . ';"></span><strong>' . $this->escape_html($name) . '</strong><small>' . $this->escape_html($value) . '</small></div>';
        }

        $warningHtml = '';
        foreach ($warnings as $warning) {
            $warningHtml .= '<li>' . $this->escape_html($warning) . '</li>';
        }

        $compileHtml = $compile_url !== ''
            ? '<a class="lcfa-link" href="' . $this->escape_attr($compile_url) . '">Run Picostrap compiler</a>'
            : '';

        return '<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>LiveCanvas Forge AI Design Preview</title>
  <style>
    :root{color-scheme:light;}
    *{box-sizing:border-box}
    body{margin:0;background:' . $this->escape_attr($bodyBg) . ';color:' . $this->escape_attr($bodyColor) . ';font-family:' . $this->escape_attr($bodyFont) . ';padding:40px 20px}
    .lcfa-shell{max-width:1100px;margin:0 auto;display:grid;gap:24px}
    .lcfa-card{background:rgba(255,255,255,.78);backdrop-filter:blur(12px);border:1px solid rgba(15,23,42,.08);border-radius:24px;padding:28px;box-shadow:0 24px 60px rgba(15,23,42,.08)}
    .lcfa-hero{display:grid;gap:16px}
    .lcfa-badge{display:inline-flex;align-items:center;gap:8px;border-radius:999px;padding:8px 14px;background:' . $this->escape_attr($light) . ';color:' . $this->escape_attr($dark) . ';font-size:14px;font-weight:700;width:max-content}
    h1,h2{margin:0 0 8px;font-family:' . $this->escape_attr($headingFont) . '}
    h1{font-size:clamp(42px,7vw,84px);line-height:.95}
    h2{font-size:28px}
    p{margin:0;font-size:18px;line-height:1.6}
    .lcfa-cta{display:flex;flex-wrap:wrap;gap:14px;margin-top:8px}
    .lcfa-btn{display:inline-flex;align-items:center;justify-content:center;border:none;border-radius:' . $this->escape_attr($buttonRadius) . ';padding:' . $this->escape_attr($buttonPaddingY) . ' ' . $this->escape_attr($buttonPaddingX) . ';font:inherit;font-weight:700;text-decoration:none}
    .lcfa-btn--primary{background:' . $this->escape_attr($primary) . ';color:#fff}
    .lcfa-btn--secondary{background:' . $this->escape_attr($secondary) . ';color:#fff}
    .lcfa-grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}
    .lcfa-swatch{display:grid;gap:8px;padding:18px;border-radius:20px;background:#fff;border:1px solid rgba(15,23,42,.08)}
    .lcfa-swatch-chip{display:block;height:56px;border-radius:16px;border:1px solid rgba(15,23,42,.08)}
    .lcfa-meta{display:grid;gap:10px;font-size:16px}
    .lcfa-link{display:inline-flex;align-items:center;gap:8px;color:' . $this->escape_attr($primary) . ';font-weight:700;text-decoration:none}
    .lcfa-warning{margin:0;padding-left:20px}
    .lcfa-samples{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr))}
    .lcfa-sample-box{padding:20px;border-radius:' . $this->escape_attr((string) ($radius['border_radius_lg'] ?? '1rem')) . ';background:#fff;border:1px solid rgba(15,23,42,.08)}
    .lcfa-code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px;opacity:.7}
  </style>
</head>
<body>
  <main class="lcfa-shell">
    <section class="lcfa-card lcfa-hero">
      <span class="lcfa-badge">LiveCanvas Forge AI preview</span>
      <h1>' . $this->escape_html($summary) . '</h1>
      <p>' . $this->escape_html($mood !== '' ? $mood : 'Preview the generated palette, typography, and button feel before compiling Picostrap.') . '</p>
      <div class="lcfa-cta">
        <a class="lcfa-btn lcfa-btn--primary" href="' . $this->escape_attr(home_url('/')) . '">Open site</a>
        ' . $compileHtml . '
      </div>
    </section>

    <section class="lcfa-card">
      <h2>Palette</h2>
      <div class="lcfa-grid">' . $swatches . '</div>
    </section>

    <section class="lcfa-card lcfa-samples">
      <div class="lcfa-sample-box">
        <h2>Typography</h2>
        <p style="font-family:' . $this->escape_attr($headingFont) . ';font-size:42px;line-height:1">Expressive heading</p>
        <p style="margin-top:12px">Readable body copy in the selected base font for Bootstrap content and utility-driven layouts.</p>
        <p class="lcfa-code">Headings: ' . $this->escape_html($headingFont) . '</p>
        <p class="lcfa-code">Body: ' . $this->escape_html($bodyFont) . '</p>
      </div>
      <div class="lcfa-sample-box">
        <h2>Buttons</h2>
        <div class="lcfa-cta">
          <button class="lcfa-btn lcfa-btn--primary" type="button">Primary action</button>
          <button class="lcfa-btn lcfa-btn--secondary" type="button">Secondary action</button>
        </div>
        <p class="lcfa-code">Radius: ' . $this->escape_html($buttonRadius) . '</p>
        <p class="lcfa-code">Padding: ' . $this->escape_html($buttonPaddingY . ' / ' . $buttonPaddingX) . '</p>
      </div>
    </section>'
    . ($warningHtml !== '' ? '
    <section class="lcfa-card">
      <h2>Warnings</h2>
      <ul class="lcfa-warning">' . $warningHtml . '</ul>
    </section>' : '') . '
  </main>
</body>
</html>';
    }

    private function escape_html(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function escape_attr(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
