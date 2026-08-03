<?php

defined('ABSPATH') || exit;

use Defuse\Crypto\Crypto;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;
use Nyholm\Psr7\Response as Psr7Response;
use Nyholm\Psr7\ServerRequest as Psr7ServerRequest;

final class LCFA_OAuth_Server {
    private const REGISTRATION_RATE_LIMIT = 10;
    private const TOKEN_RATE_LIMIT = 30;
    private const ACCESS_TOKEN_TTL = 'PT1H';
    private const REFRESH_TOKEN_TTL = 'P14D';
    private const AUTH_CODE_TTL = 'PT1M';

    private ?LCFA_OAuth_Client_Repository $client_repository = null;
    private ?LCFA_OAuth_Access_Token_Repository $access_token_repository = null;
    private ?LCFA_OAuth_Scope_Repository $scope_repository = null;
    private ?LCFA_OAuth_Auth_Code_Repository $auth_code_repository = null;
    private ?LCFA_OAuth_Refresh_Token_Repository $refresh_token_repository = null;
    private ?AuthorizationServer $authorization_server = null;
    private ?ResourceServer $resource_server = null;
    private ?WP_Error $request_auth_error = null;
    private array $masked_authorization_headers = [];
    private static array $current_identity = [];

    public function hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('admin_menu', [$this, 'register_authorization_page']);
        add_action('template_redirect', [$this, 'serve_well_known_metadata'], -100);
        add_action(LCFA_OAuth_Storage::cleanup_hook(), [LCFA_OAuth_Storage::class, 'cleanup']);
        add_action('admin_post_lcfa_oauth_revoke_client', [$this, 'handle_revoke_client']);
        add_filter('determine_current_user', [$this, 'authenticate_bearer'], 30);
        add_filter('rest_authentication_errors', [$this, 'enforce_bearer_error'], 5);
        add_filter('rest_pre_dispatch', [$this, 'mask_oauth_bearer_for_legacy_rest_filters'], 1, 3);
        add_filter('rest_pre_dispatch', [$this, 'restore_oauth_bearer_after_legacy_rest_filters'], PHP_INT_MAX, 3);
        add_filter('rest_request_before_callbacks', [$this, 'protect_mcp_route'], 5, 3);
        add_filter('rest_post_dispatch', [$this, 'attach_authenticate_challenge'], 10, 3);
    }

    public function register_routes(): void {
        register_rest_route('lcfa/v1', '/oauth/register', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'register_client'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('lcfa/v1', '/oauth/token', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'issue_token'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('lcfa/v1', '/oauth/revoke', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'revoke_token'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('lcfa/v1', '/oauth/protected-resource', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => function (): WP_REST_Response {
                return new WP_REST_Response($this->get_protected_resource_metadata(), 200);
            },
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('lcfa/v1', '/oauth/authorization-server', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => function (): WP_REST_Response {
                return new WP_REST_Response($this->get_authorization_server_metadata(), 200);
            },
            'permission_callback' => '__return_true',
        ]);
    }

    public function register_authorization_page(): void {
        add_submenu_page(
            null,
            __('Authorize LiveCanvas AI Bridge', 'livecanvas-forge-ai'),
            __('Authorize AI Bridge', 'livecanvas-forge-ai'),
            'manage_options',
            'lcfa-oauth-authorize',
            [$this, 'render_authorization_page']
        );
    }

    public function register_client(WP_REST_Request $request): WP_REST_Response {
        $availability = $this->get_status();
        if (empty($availability['available'])) {
            return new WP_REST_Response([
                'error' => 'temporarily_unavailable',
                'error_description' => (string) ($availability['message'] ?? __('OAuth Direct Mode is unavailable.', 'livecanvas-forge-ai')),
            ], 503);
        }

        $rate_limit = $this->check_rate_limit('register', self::REGISTRATION_RATE_LIMIT, HOUR_IN_SECONDS);
        if (is_wp_error($rate_limit)) {
            return $this->error_to_rest_response($rate_limit);
        }

        $registration = LCFA_OAuth_Client_Registration::normalize($this->get_request_payload($request));
        if (is_wp_error($registration)) {
            return $this->error_to_rest_response($registration);
        }

        $ip_hash = LCFA_OAuth_Storage::hash_ip($this->request_ip());
        $site_limit = max(1, (int) apply_filters('lcfa_oauth_max_clients', 50));
        $ip_limit = max(1, (int) apply_filters('lcfa_oauth_max_clients_per_ip', 10));
        if (
            LCFA_OAuth_Storage::count_active_clients() >= $site_limit
            || LCFA_OAuth_Storage::count_clients_for_ip($ip_hash) >= $ip_limit
        ) {
            return new WP_REST_Response([
                'error' => 'registration_limit_reached',
                'error_description' => __('The OAuth client registration limit has been reached. Revoke an unused connected app and retry.', 'livecanvas-forge-ai'),
            ], 429);
        }

        $client = LCFA_OAuth_Storage::create_client($registration, $ip_hash);
        if (is_wp_error($client)) {
            return $this->error_to_rest_response($client);
        }

        $response = new WP_REST_Response([
            'client_id' => (string) $client['client_id'],
            'client_id_issued_at' => (int) $client['client_id_issued_at'],
            'client_name' => (string) $client['client_name'],
            'redirect_uris' => array_values((array) $client['redirect_uris']),
            'grant_types' => array_values((array) $client['grant_types']),
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ], 201);
        $response->header('Cache-Control', 'no-store');

        return $response;
    }

    public function issue_token(WP_REST_Request $request): WP_REST_Response {
        $rate_limit = $this->check_rate_limit('token', self::TOKEN_RATE_LIMIT, MINUTE_IN_SECONDS);
        if (is_wp_error($rate_limit)) {
            return $this->error_to_rest_response($rate_limit);
        }

        try {
            $server = $this->authorization_server();
            $psr_response = $server->respondToAccessTokenRequest(
                $this->psr_request_from_rest($request),
                new Psr7Response()
            );

            return $this->psr_response_to_rest($psr_response);
        } catch (OAuthServerException $exception) {
            return $this->psr_response_to_rest($exception->generateHttpResponse(new Psr7Response()));
        } catch (Throwable $exception) {
            $this->log_exception('token', $exception);

            return new WP_REST_Response([
                'error' => 'server_error',
                'error_description' => __('The OAuth token request could not be completed.', 'livecanvas-forge-ai'),
            ], 500);
        }
    }

    public function revoke_token(WP_REST_Request $request): WP_REST_Response {
        $rate_limit = $this->check_rate_limit('revoke', self::TOKEN_RATE_LIMIT, MINUTE_IN_SECONDS);
        if (is_wp_error($rate_limit)) {
            return $this->error_to_rest_response($rate_limit);
        }

        $payload = $this->get_request_payload($request);
        $token = trim((string) ($payload['token'] ?? ''));
        $client_id = trim((string) ($payload['client_id'] ?? ''));

        if ($token !== '') {
            $access_identifier = $this->get_jwt_identifier($token);
            if ($access_identifier !== '') {
                $record = LCFA_OAuth_Storage::get_access_token($access_identifier);
                if ($client_id === '' || (is_array($record) && hash_equals($client_id, (string) ($record['client_id'] ?? '')))) {
                    LCFA_OAuth_Storage::revoke_access_token($access_identifier);
                }
            } else {
                $refresh = $this->decode_refresh_token($token);
                if (
                    is_array($refresh)
                    && (!isset($refresh['client_id']) || $client_id === '' || hash_equals($client_id, (string) $refresh['client_id']))
                ) {
                    if (!empty($refresh['refresh_token_id'])) {
                        LCFA_OAuth_Storage::revoke_refresh_token((string) $refresh['refresh_token_id']);
                    }
                    if (!empty($refresh['access_token_id'])) {
                        LCFA_OAuth_Storage::revoke_access_token((string) $refresh['access_token_id']);
                    }
                }
            }
        }

        $response = new WP_REST_Response(null, 200);
        $response->header('Cache-Control', 'no-store');

        return $response;
    }

    public function render_authorization_page(): void {
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url($this->current_authorization_url()));
            exit;
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Only a WordPress administrator can authorize a coding agent.', 'livecanvas-forge-ai'));
        }

        try {
            $parameters = $this->authorization_parameters();
            $authorization_request = $this->validate_authorization_request($parameters);

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                check_admin_referer('lcfa_oauth_authorize');
                $decision = sanitize_key((string) ($_POST['lcfa_oauth_decision'] ?? 'deny'));
                $authorization_request->setUser(new LCFA_OAuth_User_Entity(get_current_user_id()));
                $authorization_request->setAuthorizationApproved($decision === 'approve');
                $response = $this->authorization_server()->completeAuthorizationRequest(
                    $authorization_request,
                    new Psr7Response()
                );
                $location = $response->getHeaderLine('Location');
                if ($location === '') {
                    throw new RuntimeException('OAuth authorization response did not include a redirect URI.');
                }
                wp_redirect($location, $response->getStatusCode());
                exit;
            }
        } catch (OAuthServerException $exception) {
            $this->render_oauth_exception($exception);
            return;
        } catch (Throwable $exception) {
            $this->log_exception('authorize', $exception);
            wp_die(esc_html__('The OAuth authorization request could not be validated.', 'livecanvas-forge-ai'));
        }

        $client = $authorization_request->getClient();
        $scope_labels = array_map(
            static function ($scope): string {
                return (string) $scope->getIdentifier();
            },
            $authorization_request->getScopes()
        );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Authorize LiveCanvas AI Bridge', 'livecanvas-forge-ai'); ?></h1>
            <div class="notice notice-warning inline">
                <p><strong><?php echo esc_html__('Approve only the Codex project you are using now.', 'livecanvas-forge-ai'); ?></strong></p>
                <p><?php echo esc_html__('This grant is limited to this WordPress site and can be revoked from AI Bridge Connections. Write tools still follow the AI Bridge master switch and ability allowlist.', 'livecanvas-forge-ai'); ?></p>
            </div>
            <table class="widefat striped" style="max-width: 760px; margin: 24px 0;">
                <tbody>
                    <tr>
                        <th><?php echo esc_html__('Application', 'livecanvas-forge-ai'); ?></th>
                        <td><?php echo esc_html((string) $client->getName()); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('WordPress site', 'livecanvas-forge-ai'); ?></th>
                        <td><code><?php echo esc_html(home_url('/')); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Site fingerprint', 'livecanvas-forge-ai'); ?></th>
                        <td><code><?php echo esc_html(LCFA_OAuth_Storage::site_fingerprint()); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('OAuth scope', 'livecanvas-forge-ai'); ?></th>
                        <td><?php echo esc_html(implode(', ', $scope_labels)); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Redirect URI', 'livecanvas-forge-ai'); ?></th>
                        <td><code><?php echo esc_html((string) ($parameters['redirect_uri'] ?? '')); ?></code></td>
                    </tr>
                </tbody>
            </table>
            <form method="post" action="<?php echo esc_url(LCFA_OAuth_Storage::authorization_url()); ?>">
                <?php wp_nonce_field('lcfa_oauth_authorize'); ?>
                <?php foreach ($parameters as $key => $value) : ?>
                    <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string) $value); ?>">
                <?php endforeach; ?>
                <p>
                    <button type="submit" name="lcfa_oauth_decision" value="approve" class="button button-primary button-hero">
                        <?php echo esc_html__('Authorize Codex', 'livecanvas-forge-ai'); ?>
                    </button>
                    <button type="submit" name="lcfa_oauth_decision" value="deny" class="button button-secondary button-hero">
                        <?php echo esc_html__('Deny', 'livecanvas-forge-ai'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    public function authenticate_bearer($user_id) {
        if (!empty($user_id)) {
            return $user_id;
        }

        $authorization = $this->authorization_header();
        $token = $this->extract_bearer_token($authorization);
        if (!$this->looks_like_oauth_access_token($token)) {
            return $user_id;
        }

        if (!$this->is_current_request_mcp_route()) {
            $this->request_auth_error = $this->auth_error(
                'lcfa_oauth_route_scope',
                __('This AI Bridge OAuth token is valid only for the LiveCanvas MCP endpoint.', 'livecanvas-forge-ai'),
                403
            );
            return 0;
        }

        try {
            $psr_request = new Psr7ServerRequest(
                strtoupper(sanitize_key((string) ($_SERVER['REQUEST_METHOD'] ?? 'POST')) ?: 'POST'),
                $this->current_request_url(),
                ['Authorization' => $authorization]
            );
            $validated = $this->resource_server()->validateAuthenticatedRequest($psr_request);
            if (!hash_equals(LCFA_OAuth_Storage::resource_url(), (string) $validated->getAttribute('oauth_client_id'))) {
                throw OAuthServerException::accessDenied('OAuth token audience does not match this MCP resource.');
            }

            $token_id = (string) $validated->getAttribute('oauth_access_token_id');
            $record = LCFA_OAuth_Storage::get_access_token($token_id);
            if (!is_array($record) || LCFA_OAuth_Storage::is_access_token_revoked($token_id)) {
                throw OAuthServerException::accessDenied('OAuth token is revoked, expired, or belongs to a different site identity.');
            }

            $validated_user_id = (int) ($record['user_id'] ?? 0);
            $user = $validated_user_id > 0 ? get_user_by('id', $validated_user_id) : false;
            if (!$user || !user_can($user, 'edit_pages')) {
                throw OAuthServerException::accessDenied('The WordPress user linked to this OAuth grant is no longer allowed to edit pages.');
            }

            self::$current_identity = [
                'auth_method' => 'oauth_direct',
                'access_token_id' => $token_id,
                'client_id' => (string) ($record['client_id'] ?? ''),
                'user_id' => $validated_user_id,
                'scopes' => (array) ($record['scopes'] ?? []),
                'site_fingerprint' => (string) ($record['site_fingerprint'] ?? ''),
            ];
            LCFA_OAuth_Storage::touch_access_token($token_id);
            LCFA_OAuth_Storage::touch_client((string) ($record['client_id'] ?? ''));
            $this->mark_connection_ready();

            return $validated_user_id;
        } catch (OAuthServerException $exception) {
            $this->request_auth_error = $this->auth_error(
                'lcfa_oauth_access_denied',
                $exception->getMessage(),
                401
            );
        } catch (Throwable $exception) {
            $this->log_exception('resource', $exception);
            $this->request_auth_error = $this->auth_error(
                'lcfa_oauth_validation_failed',
                __('The AI Bridge OAuth token could not be validated.', 'livecanvas-forge-ai'),
                401
            );
        }

        return 0;
    }

    public function enforce_bearer_error($error) {
        return $this->request_auth_error instanceof WP_Error ? $this->request_auth_error : $error;
    }

    public function mask_oauth_bearer_for_legacy_rest_filters($response, $server, WP_REST_Request $request) {
        if (!$this->is_mcp_route((string) $request->get_route()) || self::$current_identity === []) {
            return $response;
        }

        $this->masked_authorization_headers = [];
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $header) {
            if (!array_key_exists($header, $_SERVER)) {
                continue;
            }

            $this->masked_authorization_headers[$header] = $_SERVER[$header];
            unset($_SERVER[$header]);
        }

        return $response;
    }

    public function restore_oauth_bearer_after_legacy_rest_filters($response, $server, WP_REST_Request $request) {
        if ($this->masked_authorization_headers === []) {
            return $response;
        }

        foreach ($this->masked_authorization_headers as $header => $value) {
            $_SERVER[$header] = $value;
        }
        $this->masked_authorization_headers = [];

        return $response;
    }

    public function protect_mcp_route($response, $handler, WP_REST_Request $request) {
        if ($response !== null || !$this->is_mcp_route((string) $request->get_route())) {
            return $response;
        }

        if (get_current_user_id() > 0) {
            return $response;
        }

        if ($this->request_auth_error instanceof WP_Error) {
            return $this->request_auth_error;
        }

        return $this->auth_error(
            'lcfa_oauth_required',
            __('Authenticate this remote MCP connection with AI Bridge OAuth.', 'livecanvas-forge-ai'),
            401
        );
    }

    public function attach_authenticate_challenge($response, WP_REST_Server $server, WP_REST_Request $request) {
        if (!$this->is_mcp_route((string) $request->get_route()) || !is_object($response) || !method_exists($response, 'get_status')) {
            return $response;
        }

        if ((int) $response->get_status() === 401 && method_exists($response, 'header')) {
            $response->header('WWW-Authenticate', $this->authenticate_challenge());
        }

        return $response;
    }

    public function serve_well_known_metadata(): void {
        $path = (string) wp_parse_url($this->current_request_url(), PHP_URL_PATH);
        $protected_path = (string) wp_parse_url(LCFA_OAuth_Storage::protected_resource_metadata_url(), PHP_URL_PATH);
        $authorization_path = (string) wp_parse_url(LCFA_OAuth_Storage::authorization_server_metadata_url(), PHP_URL_PATH);

        if ($path !== $protected_path && $path !== $authorization_path) {
            return;
        }

        $payload = $path === $protected_path
            ? $this->get_protected_resource_metadata()
            : $this->get_authorization_server_metadata();

        status_header(200);
        nocache_headers();
        header('Content-Type: application/json; charset=' . get_option('blog_charset', 'UTF-8'));
        header('Access-Control-Allow-Origin: *');
        echo wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function get_protected_resource_metadata(): array {
        return [
            'resource' => LCFA_OAuth_Storage::resource_url(),
            'authorization_servers' => [LCFA_OAuth_Storage::issuer_url()],
            'bearer_methods_supported' => ['header'],
            'scopes_supported' => ['mcp'],
            'resource_documentation' => admin_url('admin.php?page=lcfa-dashboard&tab=connections'),
        ];
    }

    public function get_authorization_server_metadata(): array {
        return [
            'issuer' => LCFA_OAuth_Storage::issuer_url(),
            'authorization_endpoint' => LCFA_OAuth_Storage::authorization_url(),
            'token_endpoint' => LCFA_OAuth_Storage::token_url(),
            'registration_endpoint' => LCFA_OAuth_Storage::registration_url(),
            'revocation_endpoint' => LCFA_OAuth_Storage::revocation_url(),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'revocation_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => ['mcp'],
            'service_documentation' => admin_url('admin.php?page=lcfa-dashboard&tab=connections'),
        ];
    }

    public function get_status(?array $adapter_status = null): array {
        $site_url = home_url('/');
        $https = strtolower((string) wp_parse_url($site_url, PHP_URL_SCHEME)) === 'https';
        $public_https = class_exists('LCFA_Settings', false)
            && method_exists('LCFA_Settings', 'is_public_https_site_url')
            && LCFA_Settings::is_public_https_site_url($site_url);
        $dependencies = PHP_VERSION_ID >= 80000
            && class_exists(AuthorizationServer::class)
            && class_exists(Psr7ServerRequest::class)
            && function_exists('openssl_pkey_new');

        if ($adapter_status === null) {
            $adapter_status = [
                'available' => function_exists('wp_register_ability')
                    && class_exists('WP\\MCP\\Core\\McpAdapter')
                    && class_exists('WP\\MCP\\Transport\\HttpTransport'),
            ];
        }
        $adapter_available = !empty($adapter_status['available']);
        $runtime_ready = $public_https && $dependencies;
        $ready = $runtime_ready && $adapter_available;

        if (!$https) {
            $message = __('Direct OAuth requires a public HTTPS WordPress URL. Secure pairing remains available as fallback.', 'livecanvas-forge-ai');
        } elseif (!$public_https) {
            $message = __('Direct OAuth is reserved for public HTTPS sites. Local and private sites continue to use secure pairing or the advanced local runtime.', 'livecanvas-forge-ai');
        } elseif (!$dependencies) {
            $message = __('The bundled OAuth runtime is incomplete. Reinstall the current AI Bridge release.', 'livecanvas-forge-ai');
        } elseif (!$adapter_available) {
            $message = __('Install and activate the official WordPress MCP Adapter to use Direct OAuth.', 'livecanvas-forge-ai');
        } else {
            $message = __('Direct OAuth is available for the WordPress MCP endpoint.', 'livecanvas-forge-ai');
        }

        return [
            'available' => $ready,
            'oauth_runtime_ready' => $runtime_ready,
            'https' => $https,
            'public_https' => $public_https,
            'dependencies' => $dependencies,
            'mcp_adapter_available' => $adapter_available,
            'strategy' => $ready ? 'oauth-direct' : 'ai-bridge-session',
            'resource_url' => LCFA_OAuth_Storage::resource_url(),
            'protected_resource_metadata_url' => LCFA_OAuth_Storage::protected_resource_metadata_url(),
            'authorization_server_metadata_url' => LCFA_OAuth_Storage::authorization_server_metadata_url(),
            'message' => $message,
        ];
    }

    public function get_connected_apps(): array {
        $schema = LCFA_OAuth_Storage::ensure_schema();

        return is_wp_error($schema) ? [] : LCFA_OAuth_Storage::get_connected_apps();
    }

    public function handle_revoke_client(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'livecanvas-forge-ai'));
        }
        check_admin_referer('lcfa_oauth_revoke_client');

        $client_id = sanitize_text_field((string) ($_POST['client_id'] ?? ''));
        if ($client_id !== '') {
            LCFA_OAuth_Storage::revoke_client($client_id);
            LCFA_Settings::set_notice(__('The Codex OAuth grant was revoked.', 'livecanvas-forge-ai'));
        }

        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=connections#lcfa-codex-connected-apps'));
        exit;
    }

    public static function get_current_identity(): array {
        return self::$current_identity;
    }

    private function authorization_server(): AuthorizationServer {
        if ($this->authorization_server instanceof AuthorizationServer) {
            return $this->authorization_server;
        }

        $keys = LCFA_OAuth_Storage::ensure_schema();
        if (is_wp_error($keys)) {
            throw new RuntimeException($keys->get_error_message());
        }

        $this->client_repository = new LCFA_OAuth_Client_Repository();
        $this->access_token_repository = new LCFA_OAuth_Access_Token_Repository();
        $this->scope_repository = new LCFA_OAuth_Scope_Repository();
        $this->auth_code_repository = new LCFA_OAuth_Auth_Code_Repository();
        $this->refresh_token_repository = new LCFA_OAuth_Refresh_Token_Repository();

        $server = new AuthorizationServer(
            $this->client_repository,
            $this->access_token_repository,
            $this->scope_repository,
            new CryptKey((string) $keys['private_key'], null, false),
            (string) $keys['encryption_key']
        );
        $server->setDefaultScope('mcp');

        $auth_code_grant = new AuthCodeGrant(
            $this->auth_code_repository,
            $this->refresh_token_repository,
            new \DateInterval(self::AUTH_CODE_TTL)
        );
        $auth_code_grant->setRefreshTokenTTL(new \DateInterval(self::REFRESH_TOKEN_TTL));
        $server->enableGrantType($auth_code_grant, new \DateInterval(self::ACCESS_TOKEN_TTL));

        $refresh_grant = new RefreshTokenGrant($this->refresh_token_repository);
        $refresh_grant->setRefreshTokenTTL(new \DateInterval(self::REFRESH_TOKEN_TTL));
        $server->enableGrantType($refresh_grant, new \DateInterval(self::ACCESS_TOKEN_TTL));
        $server->revokeRefreshTokens(true);

        $this->authorization_server = $server;

        return $server;
    }

    private function resource_server(): ResourceServer {
        if ($this->resource_server instanceof ResourceServer) {
            return $this->resource_server;
        }

        $keys = LCFA_OAuth_Storage::ensure_schema();
        if (is_wp_error($keys)) {
            throw new RuntimeException($keys->get_error_message());
        }
        if (!$this->access_token_repository instanceof LCFA_OAuth_Access_Token_Repository) {
            $this->access_token_repository = new LCFA_OAuth_Access_Token_Repository();
        }

        $this->resource_server = new ResourceServer(
            $this->access_token_repository,
            new CryptKey((string) $keys['public_key'], null, false)
        );

        return $this->resource_server;
    }

    private function validate_authorization_request(array $parameters) {
        $challenge = trim((string) ($parameters['code_challenge'] ?? ''));
        $method = strtoupper(trim((string) ($parameters['code_challenge_method'] ?? '')));
        if ($challenge === '' || $method !== 'S256') {
            throw OAuthServerException::invalidRequest(
                'code_challenge_method',
                'LiveCanvas AI Bridge requires PKCE with the S256 code challenge method.'
            );
        }

        $resource = trim((string) ($parameters['resource'] ?? ''));
        if ($resource !== '' && !hash_equals(LCFA_OAuth_Storage::resource_url(), untrailingslashit($resource))) {
            throw OAuthServerException::invalidRequest('resource', 'The OAuth resource does not match this LiveCanvas MCP endpoint.');
        }

        $psr_request = new Psr7ServerRequest(
            'GET',
            add_query_arg($parameters, LCFA_OAuth_Storage::authorization_url())
        );
        $psr_request = $psr_request->withQueryParams($parameters);

        return $this->authorization_server()->validateAuthorizationRequest($psr_request);
    }

    private function authorization_parameters(): array {
        $source = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $allowed = [
            'response_type',
            'client_id',
            'redirect_uri',
            'scope',
            'state',
            'code_challenge',
            'code_challenge_method',
            'resource',
        ];
        $parameters = [];
        foreach ($allowed as $key) {
            if (!isset($source[$key]) || !is_scalar($source[$key])) {
                continue;
            }
            $parameters[$key] = sanitize_text_field(wp_unslash((string) $source[$key]));
        }
        if (empty($parameters['scope'])) {
            $parameters['scope'] = 'mcp';
        }

        return $parameters;
    }

    private function psr_request_from_rest(WP_REST_Request $request): Psr7ServerRequest {
        $headers = [];
        foreach ($request->get_headers() as $name => $values) {
            $headers[$name] = $values;
        }

        $url = add_query_arg($request->get_query_params(), rest_url(ltrim($request->get_route(), '/')));
        $psr_request = new Psr7ServerRequest(
            $request->get_method(),
            $url,
            $headers,
            $request->get_body(),
            '1.1',
            $_SERVER
        );
        $payload = $this->get_request_payload($request);

        return $psr_request
            ->withQueryParams($request->get_query_params())
            ->withParsedBody($payload);
    }

    private function psr_response_to_rest($response): WP_REST_Response {
        $body = (string) $response->getBody();
        $decoded = $body !== '' ? json_decode($body, true) : null;
        $data = is_array($decoded) ? $decoded : ($body !== '' ? $body : null);
        $rest_response = new WP_REST_Response($data, (int) $response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $rest_response->header($name, $value);
            }
        }

        return $rest_response;
    }

    private function get_request_payload(WP_REST_Request $request): array {
        $json = $request->get_json_params();
        if (is_array($json) && $json !== []) {
            return $json;
        }

        $body = $request->get_body_params();

        return is_array($body) ? $body : [];
    }

    private function check_rate_limit(string $bucket, int $limit, int $window) {
        $key = 'lcfa_oauth_rate_' . md5($bucket . '|' . $this->request_ip());
        $record = get_transient($key);
        $record = is_array($record) ? $record : ['count' => 0, 'started_at' => time()];
        $record['count'] = (int) ($record['count'] ?? 0) + 1;
        set_transient($key, $record, $window);

        if ($record['count'] > $limit) {
            return new WP_Error(
                'lcfa_oauth_rate_limited',
                __('Too many OAuth requests. Wait and retry.', 'livecanvas-forge-ai'),
                ['status' => 429]
            );
        }

        return true;
    }

    private function request_ip(): string {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return $ip !== '' ? $ip : 'unknown';
    }

    private function current_request_url(): string {
        $scheme = is_ssl() ? 'https' : 'http';
        $host = sanitize_text_field((string) ($_SERVER['HTTP_HOST'] ?? wp_parse_url(home_url('/'), PHP_URL_HOST)));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

        return $scheme . '://' . $host . $uri;
    }

    private function current_authorization_url(): string {
        return add_query_arg($this->authorization_parameters(), LCFA_OAuth_Storage::authorization_url());
    }

    private function is_current_request_mcp_route(): bool {
        return LCFA_OAuth_Storage::request_targets_resource($this->current_request_url());
    }

    private function is_mcp_route(string $route): bool {
        return $route === '/livecanvas-forge-ai/mcp'
            || strpos($route, '/livecanvas-forge-ai/mcp/') === 0;
    }

    private function authorization_header(): string {
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return trim((string) $_SERVER['HTTP_AUTHORIZATION']);
        }
        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return trim((string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }

        return '';
    }

    private function extract_bearer_token(string $authorization): string {
        return preg_match('/^\s*Bearer\s+(.+)\s*$/i', $authorization, $matches)
            ? trim((string) $matches[1])
            : '';
    }

    private function looks_like_oauth_access_token(string $token): bool {
        return $token !== '' && substr_count($token, '.') === 2;
    }

    private function get_jwt_identifier(string $token): string {
        if (!$this->looks_like_oauth_access_token($token)) {
            return '';
        }

        $segments = explode('.', $token);
        $payload = json_decode($this->base64url_decode((string) ($segments[1] ?? '')), true);

        return is_array($payload) ? sanitize_text_field((string) ($payload['jti'] ?? '')) : '';
    }

    private function decode_refresh_token(string $token): ?array {
        $keys = LCFA_OAuth_Storage::ensure_keys();
        if (is_wp_error($keys)) {
            return null;
        }

        try {
            $decoded = Crypto::decryptWithPassword($token, (string) $keys['encryption_key']);
            $payload = json_decode($decoded, true);

            return is_array($payload) ? $payload : null;
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function base64url_decode(string $value): string {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return is_string($decoded) ? $decoded : '';
    }

    private function auth_error(string $code, string $message, int $status): WP_Error {
        return new WP_Error($code, $message, [
            'status' => $status,
            'headers' => [
                'WWW-Authenticate' => $this->authenticate_challenge(),
            ],
        ]);
    }

    private function authenticate_challenge(): string {
        return 'Bearer resource_metadata="' . esc_url_raw(LCFA_OAuth_Storage::protected_resource_metadata_url()) . '", scope="mcp"';
    }

    private function error_to_rest_response(WP_Error $error): WP_REST_Response {
        $data = $error->get_error_data();
        $status = is_array($data) ? (int) ($data['status'] ?? 400) : 400;

        return new WP_REST_Response([
            'error' => $error->get_error_code(),
            'error_description' => $error->get_error_message(),
        ], $status);
    }

    private function render_oauth_exception(OAuthServerException $exception): void {
        $response = $exception->generateHttpResponse(new Psr7Response());
        $location = $response->getHeaderLine('Location');
        if ($location !== '') {
            wp_redirect($location, $response->getStatusCode());
            exit;
        }

        wp_die(
            esc_html($exception->getMessage()),
            esc_html__('OAuth authorization failed', 'livecanvas-forge-ai'),
            ['response' => $response->getStatusCode()]
        );
    }

    private function mark_connection_ready(): void {
        if (!class_exists('LCFA_Settings', false)) {
            return;
        }

        $connections = LCFA_Settings::get_connections();
        $connections['preferred_client'] = 'codex';
        $connections['connection_mode'] = 'remote';
        $connections['connection_strategy'] = 'oauth-direct';
        $connections['connection_status'] = 'ready';
        $connections['connection_current_step'] = 'ready';
        $connections['connection_last_verified_at'] = current_time('mysql');
        $connections['connection_last_error'] = '';
        LCFA_Settings::update_connections($connections);
    }

    private function log_exception(string $context, Throwable $exception): void {
        $trace = array_map(
            static function (array $frame): string {
                $location = (string) ($frame['file'] ?? '[internal]') . ':' . (int) ($frame['line'] ?? 0);
                $call = (string) ($frame['class'] ?? '') . (string) ($frame['type'] ?? '') . (string) ($frame['function'] ?? '');

                return $location . ' ' . $call;
            },
            array_slice($exception->getTrace(), 0, 6)
        );
        error_log(
            sprintf(
                '[LiveCanvas AI Bridge OAuth:%s] %s: %s in %s:%d; trace=%s',
                $context,
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                implode(' <- ', $trace)
            )
        );
    }
}
