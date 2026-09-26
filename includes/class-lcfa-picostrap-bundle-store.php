<?php
defined('ABSPATH') || exit;

final class LCFA_Picostrap_Bundle_Store {
    public function __construct(LCFA_Environment $environment, ?LCFA_Theme_Files_Bridge $theme_files_bridge = null) {}

    public function store(string $css, array $metadata = []): array {
        if (!class_exists('LCFA_Write_Contract') || !class_exists('LCFA_Changesets')) {
            return ['ok' => false, 'code' => 'context_unavailable', 'message' => 'The context validator and private changeset coordinator are required.'];
        }
        // Site context authorizes only the framework-derived bundle destination,
        // not an arbitrary caller path or an internally fabricated file proof.
        return LCFA_Changesets::run_picostrap_bundle($css, $metadata);
    }

    public function restore(array $snapshot, bool $dry_run = false): array {
        return ['ok' => false, 'code' => 'legacy_restore_review_required',
            'message' => 'Legacy caller-supplied bundle snapshots cannot be restored. Use undo_changeset with the private changeset ID and fresh site context.'];
    }
}
