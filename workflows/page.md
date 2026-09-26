# Create or edit a page

Resolve the URL or content ID before editing. If the requested layout belongs to a Dynamic Template, shared partial or theme fallback, use that workflow instead of overwriting post_content. Read the existing page and its managed assets before replacing a section.

For a new page, prepare target_type=new_page, choose a unique title/slug and draft status, and use the discovered page action through run_lc_command. Do not invent the action schema: inspect list_command_actions when needed. For an existing page, prefer content_patch_preview/content_patch_apply for a small change; preserve unrelated sections, metadata and managed assets.

Use the context's actual framework and the site's design conventions. Preview with the same current write_context that will be applied. Verify that managed CSS and JavaScript are supported for this target before using page_css/page_js. Never put a style or script block into body_html to avoid the asset pipeline.

After saving, read back the page and its asset fields. Compile when new classes or source assets require it, using fresh context and source revision. Inspect desktop and mobile rendering when the browser is available. If the editor has unsaved manual changes, ask to save or discard them before applying a server-side edit. Do not overwrite an editor buffer based on stale persisted content.
