const assert = require('node:assert/strict')
const { formatToolResultForMcp } = require('../src/mcp-stdio-server')

const verboseHtml = '<section>' + 'content '.repeat(5000) + '</section>'

const preview = formatToolResultForMcp('content_patch_preview', {
  result: {
    ok: true,
    mode: 'preview',
    target_type: 'page',
    target_id: 11,
    target_title: 'Northstar Studio',
    operation: 'replace_text',
    match_count: 1,
    changed: true,
    existing_html: verboseHtml,
    patched_html: verboseHtml,
    diff_html: verboseHtml,
    framework_validation: {
      ok: true,
      data: {
        valid: true,
        framework: 'picowind',
        warnings: []
      }
    }
  }
})

assert.equal(preview.result.ok, true)
assert.equal(preview.result.mode, 'preview')
assert.equal(preview.result.match_count, 1)
assert.equal(preview.result.framework_validation.valid, true)
assert.equal(preview.result.framework_validation.framework, 'picowind')
assert.equal(preview.result.existing_html, undefined)
assert.equal(preview.result.patched_html, undefined)
assert.equal(preview.result.diff_html, undefined)
assert.ok(JSON.stringify(preview).length < 2000, 'preview response should stay compact')

const apply = formatToolResultForMcp('content_patch_apply', {
  result: {
    ok: true,
    action: 'update_page',
    mode: 'apply',
    target_type: 'page',
    target_id: 11,
    frontend_url: 'http://example.test/?page_id=11',
    edit_url: 'http://example.test/wp-admin/post.php?post=11&action=edit',
    existing_html: verboseHtml,
    patched_html: verboseHtml,
    diff_html: verboseHtml,
    content_patch: {
      operation: 'replace_text',
      match_count: 1,
      changed: true
    },
    data: {
      public_preview: {
        url: 'http://example.test/?page_id=11&lcfa_preview_token=test',
        expires_at: '2026-08-01T12:00:00+00:00'
      },
      audit: {
        id: 'audit-test123',
        rollback_available: true,
        rollback_reference: {
          available: true,
          type: 'previous_post_content',
          target_id: 11
        }
      }
    }
  }
})

assert.equal(apply.result.audit_id, 'audit-test123')
assert.equal(apply.result.rollback_available, true)
assert.equal(apply.result.rollback_reference.type, 'previous_post_content')
assert.equal(apply.result.preview_expires_at, '2026-08-01T12:00:00+00:00')
assert.equal(apply.result.match_count, 1)
assert.ok(JSON.stringify(apply).length < 2500, 'apply response should stay compact')

const untouched = { result: { html: verboseHtml } }
assert.equal(formatToolResultForMcp('get_page_html', untouched), untouched)

console.log('PASS')
