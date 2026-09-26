<?php

defined('ABSPATH') || exit;

/** Owner-scoped conversations and leased requests, shared by every transport. */
final class LCFA_Private_Chat {
    private const LEASE_SECONDS = 120;

    public static function capabilities(): array {
        return ['storage' => 'owner_scoped_encrypted', 'queue_protocol' => 'post_claim_lease_v2', 'lease_seconds' => self::LEASE_SECONDS,
            'stop_protocol' => 'request_then_worker_acknowledgement', 'external_process_stop_verified' => false,
            'automatic_legacy_import' => false, 'desktop_binding_required' => true,
            'instruction' => 'Frontend requests belong to the authenticated site and user. Claim through the current bundled MCP runtime; it retains the private worker capability and sends it with tool requests. Inspect current target context before writes. On Stop, do not start more tools: the runtime waits for its in-flight Bridge tools and acknowledges only their stop. Shell and other external operations need separate cancellation. Expired work requires inspection before retrying. Queue ownership does not establish a desktop conversation binding.'];
    }

    public static function threads(array $default): array {
        if (!LCFA_Private_Store::owner()) return [];
        $threads = LCFA_Private_Store::listing('thread', 'active');
        foreach ($threads as &$thread) $thread = self::public_record($thread);
        unset($thread);
        if (!isset($threads['default'])) $threads['default'] = $default;
        return $threads;
    }

    public static function thread(string $id, array $default): array {
        if (!LCFA_Private_Store::owner()) return [];
        $thread = LCFA_Private_Store::read('thread', $id);
        if (!$thread) return $id === 'default' ? $default : [];
        return $thread['_state'] === 'active' ? self::public_record($thread) : [];
    }

    public static function create(array $thread): array {
        if (!LCFA_Private_Store::insert('thread', $thread['id'], $thread, 'active')) throw new RuntimeException('private_record_conflict: Conversation ID already exists.');
        return $thread;
    }

    public static function edit(string $id, string $operation, array $default, string $title = ''): array {
        return LCFA_Private_Store::locked('thread:' . $id, static function () use ($id, $operation, $default, $title) {
        if ($id === 'default' && !LCFA_Private_Store::read('thread', $id)) LCFA_Private_Store::insert('thread', $id, $default, 'active');
        if (in_array($operation, ['clear', 'delete'], true)) {
            foreach (self::unfinished() as $request) if ($request['thread_id'] === $id) throw new RuntimeException('private_work_unfinished: Stop this conversation\'s work and wait for worker acknowledgement before clearing or archiving it. Expired workers require review.');
        }
        $thread = LCFA_Private_Store::mutate('thread', $id, static function ($thread) use ($operation, $title) {
            if ($thread['_state'] !== 'active') return null;
            if ($operation === 'clear') { $thread['messages'] = []; $thread['message_epoch'] = (int) ($thread['message_epoch'] ?? 0) + 1; }
            if ($operation === 'rename') $thread['title'] = $title !== '' ? $title : $thread['title'];
            if ($operation === 'delete') $thread['_state'] = 'archived';
            $thread['updated_at'] = gmdate('Y-m-d H:i:s');
            return $thread;
        });
        if (!$thread) throw new RuntimeException('private_record_not_found: Conversation not found for this user and site.');
        return self::public_record($thread);
        });
    }

    public static function append(string $id, array $message, array $default): array {
        if ($id === 'default' && !LCFA_Private_Store::read('thread', $id)) LCFA_Private_Store::insert('thread', $id, $default, 'active');
        $thread = LCFA_Private_Store::mutate('thread', $id, static function ($thread) use ($message) {
            if ($thread['_state'] !== 'active') return null;
            $request_id = (string) ($message['meta']['request_id'] ?? '');
            if ($request_id === '' && str_starts_with($message['id'], 'prompt-request-')) $request_id = substr($message['id'], 7);
            if ($request_id === '' && str_starts_with($message['id'], 'result-request-')) $request_id = substr($message['id'], 7);
            if ($request_id !== '') {
                $request = LCFA_Private_Store::read('request', $request_id);
                if (!$request || $request['thread_id'] !== $thread['id'] || (int) ($request['message_epoch'] ?? 0) !== (int) ($thread['message_epoch'] ?? 0)) throw new RuntimeException('private_conversation_changed: The conversation was cleared. Its earlier result cannot be appended again.');
            }
            foreach ($thread['messages'] as $existing) {
                if ($existing['id'] !== $message['id']) continue;
                // A retried delivery may have a different delivery timestamp.
                unset($existing['time']); $comparison = $message; unset($comparison['time']);
                if ($existing !== $comparison) throw new RuntimeException('private_message_conflict: The message ID already has different content.');
                return $thread;
            }
            $thread['messages'][] = $message;
            $thread['updated_at'] = gmdate('Y-m-d H:i:s');
            return $thread;
        });
        if (!$thread) throw new RuntimeException('private_record_not_found: Conversation not found for this user and site.');
        return self::public_record($thread);
    }

    public static function enqueue(array $request, string $key): array {
        return LCFA_Private_Store::locked('thread:' . $request['thread_id'], static function () use ($request, $key) {
        $thread = LCFA_Private_Store::read('thread', $request['thread_id']);
        if (($thread && $thread['_state'] !== 'active') || (!$thread && $request['thread_id'] !== 'default')) throw new RuntimeException('private_record_not_found: Conversation not found for this user and site.');
        $request['message_epoch'] = (int) ($thread['message_epoch'] ?? 0);
        if ($key !== '' && !preg_match('/\A[a-zA-Z0-9_-]{8,96}\z/D', $key)) throw new RuntimeException('private_idempotency_invalid: Use a unique request key of 8 to 96 letters, digits, dashes or underscores.');
        $fingerprint = $request;
        foreach (['id', 'created_at', 'updated_at'] as $field) unset($fingerprint[$field]);
        $request['request_hash'] = hash('sha256', json_encode($fingerprint, JSON_THROW_ON_ERROR));
        if ($key !== '') $request['id'] = 'request-' . hash('sha256', $key);
        if (!LCFA_Private_Store::insert('request', $request['id'], $request, 'queued')) {
            $existing = LCFA_Private_Store::read('request', $request['id']);
            if (!hash_equals($existing['request_hash'], $request['request_hash'])) throw new RuntimeException('private_idempotency_conflict: This request key already belongs to a different prompt.');
            return self::public_record($existing);
        }
        return self::public_record($request);
        });
    }

    public static function requests(): array {
        return array_map([self::class, 'public_record'], LCFA_Private_Store::listing('request'));
    }

    public static function pending(): array {
        $items = [];
        foreach (self::unfinished() as $record) {
            $record = self::public_record($record);
            $items[] = array_intersect_key($record, array_flip(['id', 'thread_id', 'status', 'post_id', 'context_post_id', 'target_id', 'stop_requested_at', 'error']));
        }
        return $items;
    }

    public static function request(string $id): ?array {
        $record = $id !== '' ? LCFA_Private_Store::read('request', $id) : null;
        return $record ? self::public_record($record) : null;
    }

    public static function record_saved_changeset(string $changeset_id): void {
        // Evidence failure leaves a successful write saved, but prevents
        // automatic editor refresh. It must not turn a commit into a retry.
        try {
            $id = LCFA_MCP_Session_Manager::current_worker_request_id();
            if ($id === '') return;
            LCFA_Private_Store::mutate('request', $id, static function ($record) use ($changeset_id) {
                if (!in_array($record['_state'], ['running', 'stop_requested'], true) || ($record['worker_binding'] ?? '') !== self::worker($record['agent'])) return null;
                $record['saved_changesets'] = array_values(array_unique(array_merge((array) ($record['saved_changesets'] ?? []), [$changeset_id])));
                return $record;
            });
        } catch (Throwable $error) { /* Unknown evidence must never authorize refresh. */ }
    }

    public static function claim(string $id, string $agent): ?array {
        $worker = self::worker($agent);
        return LCFA_Private_Store::locked('worker:' . $worker, static function () use ($id, $agent, $worker) {
        foreach (self::unfinished() as $active) if (($active['worker_binding'] ?? '') === $worker) return null;
        $records = $id !== '' ? [$id => LCFA_Private_Store::read('request', $id)] : LCFA_Private_Store::listing('request', 'queued');
        foreach ($records as $candidate_id => $candidate) {
            if (!$candidate || $candidate['agent'] !== $agent || $candidate['_state'] !== 'queued') continue;
            $token = bin2hex(random_bytes(32));
            $record = LCFA_Private_Store::locked('thread:' . $candidate['thread_id'], static fn() => LCFA_Private_Store::mutate('request', $candidate_id, static function ($record) use ($agent, $token, $worker) {
                if ($record['_state'] !== 'queued' || $record['agent'] !== $agent) return null;
                self::check_thread($record);
                $record['status'] = $record['_state'] = 'running';
                $record['lease_hash'] = hash('sha256', $token);
                $record['worker_binding'] = $worker;
                $record['lease_expires'] = time() + self::LEASE_SECONDS;
                $record['claimed_at'] = $record['updated_at'] = gmdate('Y-m-d H:i:s');
                $record['claimed_by'] = $agent;
                return $record;
            }));
            if ($record) {
                try {
                    if (str_starts_with($worker, 'session:')) LCFA_MCP_Session_Manager::bind_worker_request($candidate_id);
                } catch (Throwable $error) {
                    LCFA_Private_Store::mutate('request', $candidate_id, static function ($failed) use ($token) {
                        if ($failed['_state'] !== 'running' || !hash_equals((string) ($failed['lease_hash'] ?? ''), hash('sha256', $token))) return null;
                        $failed['status'] = $failed['_state'] = 'queued';
                        unset($failed['lease_hash'], $failed['worker_binding'], $failed['lease_expires']);
                        return $failed;
                    });
                    throw $error;
                }
                return self::public_record($record) + ['lease_token' => $token];
            }
        }
        return null;
        });
    }

    public static function renew(string $id, string $token): ?array {
        $record = LCFA_Private_Store::mutate('request', $id, static function ($record) use ($token) {
            self::check_lease($record, $token);
            if ($record['_state'] === 'stop_requested') return $record;
            self::check_thread($record);
            if ($record['_state'] !== 'running' || $record['lease_expires'] <= time()) throw new RuntimeException('private_lease_expired: The worker lease expired. Inspect the operation before retrying.');
            $record['lease_expires'] = time() + self::LEASE_SECONDS;
            return $record;
        });
        return $record ? self::public_record($record) : null;
    }

    public static function finish(string $id, string $token, string $status, array $patch): ?array {
        $record = LCFA_Private_Store::mutate('request', $id, static function ($record) use ($token, $status, $patch) {
            self::check_lease($record, $token);
            self::check_thread($record);
            if (in_array($record['_state'], ['completed', 'failed'], true)) {
                if ($record['_state'] !== $status || ($record['result'] ?? null) !== ($patch['result'] ?? null)) throw new RuntimeException('private_terminal_conflict: This request already has a different final result.');
                return $record;
            }
            if ($record['_state'] !== 'running' || $record['lease_expires'] <= time()) throw new RuntimeException('private_lease_expired: A current worker lease is required to complete this request.');
            $record = array_replace($record, $patch);
            $record['_state'] = $record['status'] = $status;
            $record['completed_at'] = $record['updated_at'] = gmdate('Y-m-d H:i:s');
            return $record;
        });
        return $record ? self::public_record($record) : null;
    }

    /** Stop is monotonic and never claims to terminate an external agent process. */
    public static function cancel(string $id): ?array {
        $record = LCFA_Private_Store::mutate('request', $id, static function ($record) {
            if (in_array($record['_state'], ['cancelled', 'stopped', 'completed', 'failed', 'stop_requested'], true)) return $record;
            $record['status'] = $record['_state'] = $record['_state'] === 'queued' ? 'cancelled' : 'stop_requested';
            $record['stop_requested_at'] = $record['updated_at'] = gmdate('Y-m-d H:i:s');
            $record['external_process_stop_verified'] = false;
            return $record;
        });
        return $record ? self::public_record($record) : null;
    }

    public static function acknowledge_stop(string $id, string $token): ?array {
        $record = LCFA_Private_Store::mutate('request', $id, static function ($record) use ($token) {
            self::check_lease($record, $token);
            if ($record['_state'] === 'stopped') return $record;
            if ($record['_state'] !== 'stop_requested') throw new RuntimeException('private_stop_not_requested: A stop must be requested before worker acknowledgement.');
            $record['status'] = $record['_state'] = 'stopped';
            $record['bridge_worker_stopped_at'] = $record['updated_at'] = gmdate('Y-m-d H:i:s');
            $record['external_process_stop_verified'] = false;
            return $record;
        });
        return $record ? self::public_record($record) : null;
    }

    /** Runtime-bound session writes cannot omit a live worker capability. */
    public static function authorize_session_write(array $session, WP_REST_Request $request): bool {
        $route = rtrim($request->get_route(), '/');
        if ($request->get_method() === 'POST' && in_array($route, ['/lcfa/v1/agent/request/claim', '/lcfa/v1/agent/request/renew', '/lcfa/v1/agent/request/complete', '/lcfa/v1/agent/request/fail', '/lcfa/v1/agent/request/cancel', '/lcfa/v1/agent/request/acknowledge-stop'], true)) return true;
        $id = (string) ($session['worker_request_id'] ?? '');
        if ($id === '') return true;
        $record = LCFA_Private_Store::read('request', $id);
        if (!$record || ($record['worker_binding'] ?? '') !== 'session:' . hash('sha256', $session['session_id'])) return false;
        if (in_array($record['_state'], ['completed', 'failed'], true)) return true;
        if ($record['_state'] !== 'running' || $record['lease_expires'] <= time() || $request->get_header('x-lcfa-request-id') !== $id) return false;
        self::check_lease($record, (string) $request->get_header('x-lcfa-worker-lease'));
        self::check_thread($record);
        return true;
    }

    private static function unfinished(): array {
        $records = [];
        foreach (['queued', 'running', 'stop_requested'] as $state) {
            $batch = LCFA_Private_Store::listing('request', $state, 1000);
            // Never infer "no unfinished work" from a truncated index.
            if (count($batch) >= 1000) throw new RuntimeException('private_queue_review_required: Too many unfinished requests to make a safe lifecycle decision. Review the queue before continuing.');
            $records += $batch;
        }
        return $records;
    }

    private static function check_thread(array $record): void {
        $thread = LCFA_Private_Store::read('thread', $record['thread_id']);
        if ((!$thread && $record['thread_id'] !== 'default') || ($thread && ($thread['_state'] !== 'active' || (int) ($thread['message_epoch'] ?? 0) !== (int) ($record['message_epoch'] ?? 0)))) throw new RuntimeException('private_conversation_changed: This conversation was cleared or archived. Inspect the request before retrying.');
    }

    public static function runner(string $id, array $runner): ?array {
        $record = LCFA_Private_Store::mutate('request', $id, static function ($record) use ($runner) {
            if ($record['_state'] !== 'queued') return null;
            $record['runner'] = $runner;
            return $record;
        });
        return $record ? self::public_record($record) : null;
    }

    private static function worker(string $agent): string {
        $session = class_exists('LCFA_MCP_Session_Manager') ? LCFA_MCP_Session_Manager::current_worker_identity() : [];
        if ($session) {
            if (LCFA_MCP_Session_Manager::current_owner_user_id() !== LCFA_Private_Store::owner()) throw new RuntimeException('private_owner_mismatch: The worker connection belongs to another user.');
            if (LCFA_Agent_Registry::normalize($session['client']) !== $agent) throw new RuntimeException('private_agent_mismatch: This connection belongs to another coding agent.');
            return 'session:' . hash('sha256', $session['session_id']);
        }
        $owner = LCFA_Private_Store::owner();
        if (!$owner || !get_current_user_id()) throw new RuntimeException('private_owner_required: An authenticated worker identity is required.');
        return 'wordpress:' . $owner;
    }

    private static function check_lease(array $record, string $token): void {
        if ($token === '' || empty($record['lease_hash']) || !hash_equals($record['lease_hash'], hash('sha256', $token)) || ($record['worker_binding'] ?? '') !== self::worker($record['agent'])) throw new RuntimeException('private_lease_required: Only the worker that claimed this request can update it.');
    }

    private static function public_record(array $record): array {
        if (isset($record['agent'])) {
            $record['server_saved_targets'] = [];
            foreach ((array) ($record['saved_changesets'] ?? []) as $changeset_id) {
                $target = LCFA_Changesets::current_saved_target((string) $changeset_id);
                if ($target) $record['server_saved_targets'][] = $target;
            }
            $record['server_saved_targets'] = array_values(array_unique($record['server_saved_targets']));
        }
        unset($record['saved_changesets']);
        unset($record['_state'], $record['_revision'], $record['lease_hash'], $record['worker_binding'], $record['request_hash'], $record['message_epoch']);
        if (in_array($record['status'] ?? '', ['running', 'stop_requested'], true) && ($record['lease_expires'] ?? 0) <= time()) {
            $record['status'] = 'needs_attention';
            $record['error'] = 'The worker lease expired. Its stop or result is unconfirmed. Check the coding agent before retrying. No other worker will run this request automatically.';
        }
        return $record;
    }
}
