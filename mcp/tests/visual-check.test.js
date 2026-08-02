const assert = require('node:assert/strict')
const os = require('node:os')
const path = require('node:path')

const { VisualCheck } = require('../src/visual-check')

function createModuleLoader(chromium, version = '1.57.0') {
  return (request) => {
    if (request === 'playwright') {
      return { chromium }
    }
    if (request === 'playwright/package.json') {
      return { version }
    }
    throw new Error(`Unexpected module request: ${request}`)
  }
}

async function testMissingPlaywrightIsActionable() {
  const visualCheck = new VisualCheck({
    config: {},
    moduleLoader() {
      throw new Error('Cannot find module playwright')
    },
    existsSync: () => false,
    env: {}
  })
  const status = await visualCheck.getReadiness()

  assert.equal(status.ok, false)
  assert.equal(status.status, 'playwright_missing')
  assert.equal(status.package_available, false)
  assert.match(status.next_action, /npm install --save-dev playwright/)
  assert.equal(status.install_guidance.verify_tool, 'visual_check_status')
}

async function testManagedBrowserDetectionAndLaunchProbe() {
  let launches = 0
  const chromium = {
    executablePath: () => '/managed/chromium',
    async launch(options) {
      launches += 1
      assert.equal(options.executablePath, '/managed/chromium')
      return { async close() {} }
    }
  }
  const visualCheck = new VisualCheck({
    config: {},
    moduleLoader: createModuleLoader(chromium),
    existsSync: (candidate) => candidate === '/managed/chromium',
    env: {}
  })

  const detected = await visualCheck.getReadiness()
  assert.equal(detected.ready, true)
  assert.equal(detected.browser_source, 'playwright')
  assert.equal(detected.launch_verified, false)

  const verified = await visualCheck.getReadiness({ probe_launch: true })
  assert.equal(verified.ready, true)
  assert.equal(verified.launch_verified, true)
  assert.equal(launches, 1)
}

async function testConfiguredBrowserAndLaunchFailuresAreDistinct() {
  const chromium = {
    executablePath: () => '/managed/missing',
    async launch() {
      throw new Error('sandbox denied executable')
    }
  }
  const missing = new VisualCheck({
    config: {},
    moduleLoader: createModuleLoader(chromium),
    existsSync: () => false,
    env: { LCFA_PLAYWRIGHT_EXECUTABLE_PATH: '/configured/missing' }
  })
  const missingStatus = await missing.getReadiness()

  assert.equal(missingStatus.status, 'configured_browser_missing')
  assert.equal(missingStatus.executable_path, '/configured/missing')

  const failing = new VisualCheck({
    config: {},
    moduleLoader: createModuleLoader(chromium),
    existsSync: (candidate) => candidate === '/configured/chrome',
    env: { LCFA_PLAYWRIGHT_EXECUTABLE_PATH: '/configured/chrome' }
  })
  const failingStatus = await failing.getReadiness({ probe_launch: true })

  assert.equal(failingStatus.status, 'browser_launch_failed')
  assert.equal(failingStatus.launch_verified, true)
  assert.match(failingStatus.detail, /sandbox denied executable/)
}

async function testVisualCheckUsesFastNavigationAndReturnsDiagnostics() {
  const gotoCalls = []
  const screenshotCalls = []
  let pagesCreated = 0

  const chromium = {
    executablePath: () => '/configured/chrome',
    async launch() {
      return {
        async newPage({ viewport }) {
          pagesCreated += 1
          const listeners = {}
          return {
            on(event, callback) {
              listeners[event] = callback
            },
            async goto(url, options) {
              gotoCalls.push({ url, options, viewport })
              listeners.console?.({ type: () => 'error', text: () => 'console failure' })
              listeners.pageerror?.(new Error('page failure'))
            },
            async evaluate(callback, argument) {
              if (!Array.isArray(argument)) {
                return null
              }
              return {
                title: 'Fixture',
                overflow_x: false,
                overflow_y: true,
                scroll_width: viewport.width,
                client_width: viewport.width,
                scroll_height: 1800,
                client_height: viewport.height,
                shell: { headers: 1, mains: 1, footers: 1 },
                broken_images: [],
                selectors: {}
              }
            },
            async waitForTimeout() {},
            async screenshot(options) {
              screenshotCalls.push(options)
            },
            async close() {}
          }
        },
        async close() {}
      }
    }
  }
  const visualCheck = new VisualCheck({
    config: {},
    moduleLoader: createModuleLoader(chromium),
    existsSync: (candidate) => candidate === '/configured/chrome',
    env: { LCFA_PLAYWRIGHT_EXECUTABLE_PATH: '/configured/chrome' }
  })
  const result = await visualCheck.run({
    url: 'https://example.test/',
    output_directory: path.join(os.tmpdir(), 'lcfa-visual-check-test')
  })

  assert.equal(result.ok, true)
  assert.equal(result.status, 'completed')
  assert.equal(result.runtime.ready, true)
  assert.equal(pagesCreated, 2)
  assert.equal(gotoCalls[0].options.waitUntil, 'domcontentloaded')
  assert.equal(screenshotCalls.length, 2)
  assert.deepEqual(result.results[0].analysis.shell, { headers: 1, mains: 1, footers: 1 })
  assert.deepEqual(result.results[0].console_errors, ['console failure'])
  assert.deepEqual(result.results[0].page_errors, ['page failure'])
}

async function run() {
  await testMissingPlaywrightIsActionable()
  await testManagedBrowserDetectionAndLaunchProbe()
  await testConfiguredBrowserAndLaunchFailuresAreDistinct()
  await testVisualCheckUsesFastNavigationAndReturnsDiagnostics()
}

run()
  .then(() => {
    console.log('PASS')
  })
  .catch((error) => {
    console.error(error)
    process.exit(1)
  })
