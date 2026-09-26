# Edit a shared component

Inspect the inventory to locate the exact partial, block, section, header or footer. Read the object and resolve a representative affected URL. Explain which pages use it, or state that the complete usage set is unknown. Editing a shared object can change multiple pages even when the request names one URL.

Prepare context for the shared content ID and inspect its actual post type. Use the granular update_partial or appropriate discovered component action, never a broad site-foundation operation for a small edit. If only one page should change, propose a page-scoped override or separate component instead of changing the shared source.

Set acknowledge_shared only when the requested scope includes those effects. Preserve existing dynamic expressions and template bindings. Follow the detected asset pipeline; do not embed layout CSS in the component markup.

Validate the changed component and inspect representative consumers at desktop and mobile sizes. Report consumers that could not be checked. Keep the snapshot and resulting revision together so a later Undo can reject intervening changes.
