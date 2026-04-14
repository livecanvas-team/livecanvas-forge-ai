# Picostrap Headless Compile Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a bridge-driven Dart Sass compile pipeline for Picostrap that works for both local and remote WordPress sites, stores the compiled bundle in the active child theme, and integrates with the existing `design_system_compose -> design_system_apply` flow.

**Architecture:** Keep WordPress as the source of truth for Picostrap tokens and bundle storage, but move Sass compilation to the MCP bridge. WordPress exposes a compile manifest plus safe source/store endpoints; the Node bridge compiles with Dart Sass, stores the bundle back through REST, and returns bundle metadata that `design_system_compose` can surface automatically.

**Tech Stack:** WordPress PHP, Picostrap theme APIs (`ps_get_main_sass()`), MCP bridge Node runtime, Dart Sass (`sass` npm package), REST endpoints, existing Forge AI command deck and WP client.

---

## File Map

### Create
- ` /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-picostrap-compile-manifest.php`
- ` /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-picostrap-bundle-store.php`
- ` /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-picostrap-compile-service.php`
- ` /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/src/picostrap-compiler.js`
- ` /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/tests/picostrap-compiler.test.js`
- ` /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/picostrap_compile_phase1.php`

### Modify
- `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-rest-api.php`
- `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-plugin.php`
- `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/livecanvas-forge-ai.php`
- `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-command-deck.php`
- `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-design-system-compose.php`
- `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-local-mcp-bridge.php`
- `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/src/wp-client.js`
- `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/src/tool-registry.js`
- `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/src/cli.js`
- `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/design_system_compose_phase1.php`
- `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/foundation_contract_phase1.php`

### Existing references to keep aligned
- `/Users/commander/Studio/consultala/wp-content/themes/picostrap5/inc/picosass-compiler-integration.php`
- `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-design-system-apply.php`
- `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-design-system-preview.php`

---

### Task 1: Red Harness For Picostrap Manifest And Bundle Store

**Files:**
- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/picostrap_compile_phase1.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/foundation_contract_phase1.php`

- [ ] **Step 1: Write the failing PHP harness for manifest generation**

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/foundation_contract_phase1.php';
require_once LCFA_DIR . 'includes/class-lcfa-picostrap-compile-manifest.php';
require_once LCFA_DIR . 'includes/class-lcfa-picostrap-bundle-store.php';
require_once LCFA_DIR . 'includes/class-lcfa-picostrap-compile-service.php';

function test_manifest_uses_active_stylesheet_target(): void {
    $service = new LCFA_Picostrap_Compile_Manifest(new LCFA_Environment());
    $manifest = $service->build();

    lcfa_assert_same('picostrap', $manifest['framework'], 'Manifest should target Picostrap');
    lcfa_assert_true(!empty($manifest['main_sass']), 'Manifest should expose main Sass');
    lcfa_assert_same('picostrap5-child-base', $manifest['stylesheet'], 'Manifest should target active child stylesheet');
    lcfa_assert_same('wp-content/themes/picostrap5-child-base/css-output/bundle.css', $manifest['target_bundle_relative_path'], 'Manifest should point at child-theme bundle');
}
```

- [ ] **Step 2: Add the failing bundle store test**

```php
function test_store_writes_bundle_and_bumps_version(): void {
    $store = new LCFA_Picostrap_Bundle_Store(new LCFA_Environment());
    $before = (int) get_theme_mod('css_bundle_version_number', 0);

    $result = $store->store('body{color:#123456;}');

    lcfa_assert_true(!empty($result['ok']), 'Bundle store should succeed');
    lcfa_assert_true(is_file($result['bundle_path']), 'Stored bundle path should exist');
    lcfa_assert_true($result['bundle_version'] > $before, 'Bundle version should increment');
}
```

- [ ] **Step 3: Wire a smoke-style compile service contract test that should fail initially**

```php
function test_compile_service_reports_bridge_unavailable_without_false_success(): void {
    $environment = new LCFA_Environment();
    $manifest = new LCFA_Picostrap_Compile_Manifest($environment);
    $store = new LCFA_Picostrap_Bundle_Store($environment);
    $bridge = new LCFA_Local_MCP_Bridge($environment);
    $service = new LCFA_Picostrap_Compile_Service($environment, $manifest, $store, $bridge);

    $result = $service->run(['action' => 'picostrap_compile_bundle'], false);

    lcfa_assert_same('picostrap_compile_bundle', $result['action'], 'Service should expose compile action');
    lcfa_assert_true(isset($result['build_executed']), 'Service should always report build execution state');
}
```

- [ ] **Step 4: Run the new harness to verify it fails**

Run:

```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/picostrap_compile_phase1.php
```

Expected: `FAIL` with missing class errors or missing methods for manifest/store/service.

- [ ] **Step 5: Commit the red test harness**

```bash
cd /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai
git add tests/php/picostrap_compile_phase1.php tests/php/foundation_contract_phase1.php
git commit -m "test: add picostrap headless compile harness"
```

---

### Task 2: Implement Manifest And Bundle Store In PHP

**Files:**
- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-picostrap-compile-manifest.php`
- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-picostrap-bundle-store.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/livecanvas-forge-ai.php`

- [ ] **Step 1: Implement the compile manifest class**

```php
<?php

defined('ABSPATH') || exit;

final class LCFA_Picostrap_Compile_Manifest {
    private LCFA_Environment $environment;

    public function __construct(LCFA_Environment $environment) {
        $this->environment = $environment;
    }

    public function build(): array {
        require_once get_template_directory() . '/inc/picosass-compiler-integration.php';

        $stylesheet = wp_get_theme()->get_stylesheet();
        $template = wp_get_theme()->get_template();
        $bundle_relative = 'wp-content/themes/' . $stylesheet . '/css-output/bundle.css';

        return [
            'framework' => 'picostrap',
            'stylesheet' => $stylesheet,
            'template' => $template,
            'is_child_theme' => is_child_theme(),
            'main_sass' => ps_get_main_sass(),
            'entry_virtual_file' => 'main.scss',
            'base_relative_dir' => 'sass',
            'import_roots' => $this->build_import_roots(),
            'target_bundle_relative_path' => $bundle_relative,
            'current_bundle_version' => (int) get_theme_mod('css_bundle_version_number', 0),
            'theme_mods' => array_filter((array) get_theme_mods(), static fn ($value, $key) => strpos((string) $key, 'SCSSvar_') === 0, ARRAY_FILTER_USE_BOTH),
            'source_map' => false,
            'compile_mode' => 'expanded',
        ];
    }
}
```

- [ ] **Step 2: Implement the bundle store using the active stylesheet directory**

```php
<?php

defined('ABSPATH') || exit;

final class LCFA_Picostrap_Bundle_Store {
    private LCFA_Environment $environment;

    public function __construct(LCFA_Environment $environment) {
        $this->environment = $environment;
    }

    public function store(string $css): array {
        $target_dir = trailingslashit(get_stylesheet_directory()) . 'css-output';
        wp_mkdir_p($target_dir);

        $bundle_path = $target_dir . '/bundle.css';
        $written = file_put_contents($bundle_path, $css);

        if ($written === false) {
            return [
                'ok' => false,
                'message' => __('Unable to write Picostrap bundle.', 'livecanvas-forge-ai'),
            ];
        }

        $version = (int) get_theme_mod('css_bundle_version_number', 0);
        $version = $version > 0 ? $version + 1 : 1;
        set_theme_mod('css_bundle_version_number', $version);

        return [
            'ok' => true,
            'bundle_path' => $bundle_path,
            'bundle_url' => get_stylesheet_directory_uri() . '/css-output/bundle.css?ver=' . $version,
            'bundle_version' => $version,
            'compiled_at' => current_time('mysql', true),
        ];
    }
}
```

- [ ] **Step 3: Load the new classes in plugin bootstrap**

```php
require_once LCFA_DIR . 'includes/class-lcfa-picostrap-compile-manifest.php';
require_once LCFA_DIR . 'includes/class-lcfa-picostrap-bundle-store.php';
require_once LCFA_DIR . 'includes/class-lcfa-picostrap-compile-service.php';
```

- [ ] **Step 4: Run the manifest/store harness**

Run:

```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/picostrap_compile_phase1.php
```

Expected: the first two tests pass, while compile-service tests still fail.

- [ ] **Step 5: Commit the PHP manifest/store implementation**

```bash
cd /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai
git add includes/class-lcfa-picostrap-compile-manifest.php includes/class-lcfa-picostrap-bundle-store.php livecanvas-forge-ai.php
git commit -m "feat: add picostrap compile manifest and bundle store"
```

---

### Task 3: Expose Safe REST Endpoints For Manifest, SCSS Source, And Bundle Store

**Files:**
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-rest-api.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/picostrap_compile_phase1.php`

- [ ] **Step 1: Add new REST routes**

```php
register_rest_route('lcfa/v1', '/picostrap/compile-manifest', [
    'methods'             => WP_REST_Server::READABLE,
    'callback'            => [$this, 'get_picostrap_compile_manifest'],
    'permission_callback' => [$this, 'can_read'],
]);

register_rest_route('lcfa/v1', '/picostrap/compile-source', [
    'methods'             => WP_REST_Server::READABLE,
    'callback'            => [$this, 'get_picostrap_compile_source'],
    'permission_callback' => [$this, 'can_read'],
]);

register_rest_route('lcfa/v1', '/picostrap/bundle', [
    'methods'             => WP_REST_Server::CREATABLE,
    'callback'            => [$this, 'store_picostrap_bundle'],
    'permission_callback' => [$this, 'can_write'],
]);
```

- [ ] **Step 2: Implement safe remote source resolution**

```php
public function get_picostrap_compile_source(WP_REST_Request $request): WP_REST_Response {
    $relative = ltrim((string) $request->get_param('import_path'), '/');

    if ($relative === '' || str_contains($relative, '..') || !str_ends_with($relative, '.scss')) {
        return new WP_REST_Response(['ok' => false, 'message' => 'Invalid SCSS import path.'], 400);
    }

    $roots = [
        trailingslashit(get_stylesheet_directory()) . 'sass/',
        trailingslashit(get_template_directory()) . 'sass/',
    ];

    foreach ($roots as $index => $root) {
        $candidate = wp_normalize_path($root . $relative);

        if (is_readable($candidate)) {
            return new WP_REST_Response([
                'ok' => true,
                'normalized_path' => $relative,
                'contents' => file_get_contents($candidate),
                'origin' => $index === 0 ? 'child' : 'parent',
            ]);
        }
    }

    return new WP_REST_Response(['ok' => false, 'message' => 'SCSS import not found.'], 404);
}
```

- [ ] **Step 3: Add endpoint tests for invalid path and successful bundle store**

```php
function test_compile_source_rejects_parent_escape(): void {
    $api = lcfa_rest_api_service();
    $request = new WP_REST_Request('GET', '/lcfa/v1/picostrap/compile-source');
    $request->set_param('import_path', '../wp-config.php');

    $response = $api->get_picostrap_compile_source($request);
    lcfa_assert_same(400, $response->get_status(), 'Source endpoint should reject path traversal');
}
```

- [ ] **Step 4: Run the PHP harness and syntax checks**

Run:

```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/picostrap_compile_phase1.php
php -l /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-rest-api.php
```

Expected: REST endpoint tests pass; bridge compile tests still fail.

- [ ] **Step 5: Commit the REST surface**

```bash
cd /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai
git add includes/class-lcfa-rest-api.php tests/php/picostrap_compile_phase1.php
git commit -m "feat: add picostrap compile REST endpoints"
```

---

### Task 4: Add Bridge-Side WP Client Methods And Dart Sass Compiler Worker

**Files:**
- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/src/picostrap-compiler.js`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/src/wp-client.js`
- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/tests/picostrap-compiler.test.js`

- [ ] **Step 1: Extend the bridge WP client**

```js
async getPicostrapCompileManifest() {
  return this.request('GET', 'picostrap/compile-manifest')
}

async getPicostrapCompileSource(importPath) {
  return this.request('GET', 'picostrap/compile-source', {
    query: { import_path: importPath }
  })
}

async storePicostrapBundle(css) {
  return this.request('POST', 'picostrap/bundle', {
    body: { css }
  })
}
```

- [ ] **Step 2: Implement the compiler worker with local + remote importers**

```js
const sass = require('sass')

async function compilePicostrapBundle(client, options = {}) {
  const manifest = await client.getPicostrapCompileManifest()
  const importCache = new Map()

  const result = await sass.compileStringAsync(manifest.main_sass, {
    style: 'expanded',
    importers: [createPicostrapImporter(client, manifest, importCache)],
    sourceMap: false,
    url: new URL('file:///main.scss')
  })

  const stored = await client.storePicostrapBundle(result.css)
  return {
    ok: !!stored.ok,
    action: 'picostrap_compile_bundle',
    build_strategy: 'bridge_dart_sass',
    build_executed: !!stored.ok,
    bundle_url: stored.bundle_url,
    bundle_version: stored.bundle_version,
    compiled_at: stored.compiled_at
  }
}
```

- [ ] **Step 3: Write the Node test for newline-free remote source resolution**

```js
test('compilePicostrapBundle stores CSS after resolving remote imports', async () => {
  const client = createFakeClient({
    manifest: {
      framework: 'picostrap',
      main_sass: '$primary:#ff2d55; @import "main";',
      import_roots: []
    },
    sources: {
      'main.scss': 'body{color:$primary;}'
    }
  })

  const result = await compilePicostrapBundle(client)
  assert.equal(result.ok, true)
  assert.equal(result.build_executed, true)
  assert.match(client.storedCss, /#ff2d55/)
})
```

- [ ] **Step 4: Run the Node tests**

Run:

```bash
node /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/tests/picostrap-compiler.test.js
```

Expected: `PASS`

- [ ] **Step 5: Commit the bridge compiler worker**

```bash
cd /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai
git add mcp/src/wp-client.js mcp/src/picostrap-compiler.js mcp/tests/picostrap-compiler.test.js
git commit -m "feat: add bridge picostrap sass compiler"
```

---

### Task 5: Expose A First-Class Bridge Tool And Local PHP Bridge Hook

**Files:**
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/src/tool-registry.js`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-local-mcp-bridge.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/picostrap_compile_phase1.php`

- [ ] **Step 1: Register a dedicated MCP tool**

```js
{
  name: 'compile_picostrap_bundle',
  description: 'Compile the active Picostrap stylesheet bundle through the bridge and store it back into WordPress.',
  inputSchema: {
    type: 'object',
    properties: {
      force: { type: 'boolean' },
      label: { type: 'string' }
    }
  },
  invoke: async (argumentsMap = {}) => compilePicostrapBundle(client, argumentsMap)
}
```

- [ ] **Step 2: Add a PHP helper mirroring the WindPress local bridge shape**

```php
public function build_picostrap_bundle(array $arguments = []): array {
    return $this->run_tool('compile_picostrap_bundle', $arguments);
}
```

- [ ] **Step 3: Add the PHP contract test for bridge result mapping**

```php
function test_local_bridge_exposes_picostrap_bundle_builder(): void {
    $bridge = new LCFA_Local_MCP_Bridge(new LCFA_Environment());
    lcfa_assert_true(method_exists($bridge, 'build_picostrap_bundle'), 'Local bridge should expose Picostrap bundle compile helper');
}
```

- [ ] **Step 4: Run the mixed test set**

Run:

```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/picostrap_compile_phase1.php
node /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/tests/picostrap-compiler.test.js
node /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/tests/tool-registry.test.js
```

Expected: all pass.

- [ ] **Step 5: Commit the bridge tool exposure**

```bash
cd /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai
git add mcp/src/tool-registry.js includes/class-lcfa-local-mcp-bridge.php tests/php/picostrap_compile_phase1.php
git commit -m "feat: expose picostrap bundle compile bridge tool"
```

---

### Task 6: Implement The PHP Compile Service And Command Deck Action

**Files:**
- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-picostrap-compile-service.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-command-deck.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-plugin.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/picostrap_compile_phase1.php`

- [ ] **Step 1: Implement the PHP compile service**

```php
<?php

defined('ABSPATH') || exit;

final class LCFA_Picostrap_Compile_Service {
    public function __construct(
        private LCFA_Environment $environment,
        private LCFA_Picostrap_Compile_Manifest $manifest,
        private LCFA_Picostrap_Bundle_Store $store,
        private LCFA_Local_MCP_Bridge $local_mcp_bridge
    ) {}

    public function run(array $payload, bool $dry_run): array {
        $manifest = $this->manifest->build();

        if ($dry_run) {
            return [
                'ok' => true,
                'action' => 'picostrap_compile_bundle',
                'mode' => 'preview',
                'target_stack' => 'picostrap',
                'build_strategy' => 'bridge_dart_sass',
                'build_required' => true,
                'build_executed' => false,
                'data' => ['manifest' => $manifest],
            ];
        }

        $result = $this->local_mcp_bridge->build_picostrap_bundle($payload);
        if (empty($result['ok'])) {
            return [
                'ok' => false,
                'action' => 'picostrap_compile_bundle',
                'mode' => 'apply',
                'target_stack' => 'picostrap',
                'build_strategy' => 'bridge_dart_sass',
                'build_required' => true,
                'build_executed' => false,
                'warnings' => [(string) ($result['message'] ?? 'Bridge compiler unavailable.')],
                'compile_url' => home_url('/?compile_sass=1&sass_nocache=1'),
                'data' => ['manifest' => $manifest, 'bridge_result' => $result],
            ];
        }

        return array_merge([
            'action' => 'picostrap_compile_bundle',
            'mode' => 'apply',
            'target_stack' => 'picostrap',
            'build_strategy' => 'bridge_dart_sass',
            'build_required' => true,
        ], $result);
    }
}
```

- [ ] **Step 2: Expose the command deck action**

```php
'picostrap_compile_bundle' => [
    'label' => __('Compile Picostrap bundle', 'livecanvas-forge-ai'),
    'mode' => 'write',
],
```

and route execution:

```php
if ($action === 'picostrap_compile_bundle') {
    return $this->picostrap_compile_service->run($payload, $dry_run);
}
```

- [ ] **Step 3: Inject the new service in plugin bootstrap**

```php
$this->picostrap_compile_service = new LCFA_Picostrap_Compile_Service(
    $this->environment,
    new LCFA_Picostrap_Compile_Manifest($this->environment),
    new LCFA_Picostrap_Bundle_Store($this->environment),
    $this->local_mcp_bridge
);
```

- [ ] **Step 4: Run the PHP harness**

Run:

```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/picostrap_compile_phase1.php
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/foundation_contract_phase1.php
```

Expected: `PASS`

- [ ] **Step 5: Commit the PHP compile service**

```bash
cd /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai
git add includes/class-lcfa-picostrap-compile-service.php includes/class-lcfa-command-deck.php includes/class-lcfa-plugin.php tests/php/picostrap_compile_phase1.php
git commit -m "feat: add picostrap compile service and command action"
```

---

### Task 7: Integrate Automatic Compile Into `design_system_compose`

**Files:**
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-design-system-compose.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/design_system_compose_phase1.php`

- [ ] **Step 1: Inject the compile service into `design_system_compose`**

```php
public function __construct(
    LCFA_Environment $environment,
    LCFA_Design_System_Picostrap_Composer $picostrap_composer,
    LCFA_Design_System_Apply $design_system_apply,
    ?LCFA_Design_System_Preview $design_system_preview = null,
    ?LCFA_Picostrap_Compile_Service $picostrap_compile_service = null
) {
    $this->picostrap_compile_service = $picostrap_compile_service;
}
```

- [ ] **Step 2: After `auto_apply`, run the compile service and merge bundle metadata**

```php
$compile_result = $this->picostrap_compile_service
    ? $this->picostrap_compile_service->run(['action' => 'picostrap_compile_bundle'], false)
    : ['ok' => false, 'build_executed' => false, 'warnings' => ['Picostrap compiler unavailable.']];

return array_merge($composed, $apply_result, $compile_result, [
    'action' => 'design_system_compose',
    'mode' => 'apply',
    'preview_url' => $preview_url,
    'frontend_url' => home_url('/'),
]);
```

- [ ] **Step 3: Update the compose test to assert real compile result fields**

```php
lcfa_assert_true(isset($result['build_executed']), 'Auto-apply should report compile execution state');
lcfa_assert_true(isset($result['bundle_url']) || isset($result['compile_url']), 'Auto-apply should expose either bundle URL or explicit fallback');
```

- [ ] **Step 4: Run compose + foundation tests**

Run:

```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/design_system_compose_phase1.php
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/foundation_contract_phase1.php
```

Expected: `PASS`

- [ ] **Step 5: Commit the compose integration**

```bash
cd /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai
git add includes/class-lcfa-design-system-compose.php tests/php/design_system_compose_phase1.php
git commit -m "feat: compile picostrap bundle after auto apply"
```

---

### Task 8: End-To-End Verification For Local And Remote Semantics

**Files:**
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/picostrap_compile_phase1.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/tests/picostrap-compiler.test.js`

- [ ] **Step 1: Add an end-to-end local smoke assertion**

```php
function test_local_bundle_url_matches_active_stylesheet(): void {
    $service = lcfa_picostrap_compile_service();
    $result = $service->run(['action' => 'picostrap_compile_bundle'], false);

    lcfa_assert_true(!empty($result['bundle_url']), 'Compile should expose bundle URL');
    lcfa_assert_true(str_contains($result['bundle_url'], 'picostrap5-child-base/css-output/bundle.css'), 'Compile should point at active child stylesheet bundle');
}
```

- [ ] **Step 2: Add a remote-source importer test in Node**

```js
test('remote importer requests SCSS over REST and stores bundle', async () => {
  const client = createRemoteFakeClient()
  const result = await compilePicostrapBundle(client, { execution_target: 'remote' })
  assert.equal(result.ok, true)
  assert.equal(client.fetchCount > 0, true)
  assert.match(client.storedCss, /body/)
})
```

- [ ] **Step 3: Run the full verification matrix**

Run:

```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/picostrap_compile_phase1.php
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/design_system_compose_phase1.php
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/design_system_apply_phase1.php
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/foundation_contract_phase1.php
node /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/tests/picostrap-compiler.test.js
node /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/tests/tool-registry.test.js
node /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/tests/stdio-startup.test.js
node /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/tests/workspace-root-sync.test.js
```

Expected:
- all PHP harnesses: `PASS`
- all Node tests: `PASS`

- [ ] **Step 4: Run the manual smoke against the local site**

Run:

```bash
curl -s http://localhost:8887/ | rg 'picostrap-styles-css|css-output/bundle.css'
php -r 'define("WP_USE_THEMES", false); require "/Users/commander/Studio/consultala/wp-load.php"; echo get_theme_mod("css_bundle_version_number");'
```

Expected:
- home page references child-theme bundle URL
- theme mod version increments after compile

- [ ] **Step 5: Commit the verification updates**

```bash
cd /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai
git add tests/php/picostrap_compile_phase1.php mcp/tests/picostrap-compiler.test.js
git commit -m "test: verify picostrap headless compile end to end"
```

---

## Self-Review

### Spec coverage
- Manifest generation: Task 2
- Safe SCSS source loading: Task 3 and Task 4
- Bridge-side Dart Sass compiler: Task 4
- Dedicated compile action: Task 6
- Compose auto-apply integration: Task 7
- Local and remote semantics: Task 8

### Placeholder scan
- No `TODO`, `TBD`, or “implement later” placeholders remain.
- All code-changing steps include concrete code blocks.
- All test steps include exact commands and expected outcomes.

### Type consistency
- PHP action name is consistently `picostrap_compile_bundle`
- Bridge tool name is consistently `compile_picostrap_bundle`
- REST endpoints consistently use:
  - `picostrap/compile-manifest`
  - `picostrap/compile-source`
  - `picostrap/bundle`

