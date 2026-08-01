<?php

defined('ABSPATH') || exit;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

final class LCFA_OAuth_Client_Repository implements ClientRepositoryInterface {
    public function getClientEntity($clientIdentifier) {
        $client = LCFA_OAuth_Storage::get_client((string) $clientIdentifier);
        if (!is_array($client)) {
            return null;
        }

        return new LCFA_OAuth_Client_Entity(
            (string) $client['client_id'],
            (string) $client['client_name'],
            (array) $client['redirect_uris']
        );
    }

    public function validateClient($clientIdentifier, $clientSecret, $grantType) {
        if ($clientSecret !== null && $clientSecret !== '') {
            return false;
        }

        if (!in_array((string) $grantType, ['authorization_code', 'refresh_token'], true)) {
            return false;
        }

        return $this->getClientEntity((string) $clientIdentifier) instanceof ClientEntityInterface;
    }
}

final class LCFA_OAuth_Scope_Repository implements ScopeRepositoryInterface {
    private const ALLOWED_SCOPES = ['mcp'];

    public function getScopeEntityByIdentifier($identifier) {
        $identifier = sanitize_key((string) $identifier);

        return in_array($identifier, self::ALLOWED_SCOPES, true)
            ? new LCFA_OAuth_Scope_Entity($identifier)
            : null;
    }

    public function finalizeScopes(
        array $scopes,
        $grantType,
        ClientEntityInterface $clientEntity,
        $userIdentifier = null
    ) {
        if ($scopes === []) {
            return [new LCFA_OAuth_Scope_Entity('mcp')];
        }

        return array_values(array_filter(
            $scopes,
            static function ($scope): bool {
                return is_object($scope)
                    && method_exists($scope, 'getIdentifier')
                    && in_array((string) $scope->getIdentifier(), self::ALLOWED_SCOPES, true);
            }
        ));
    }
}

final class LCFA_OAuth_Access_Token_Repository implements AccessTokenRepositoryInterface {
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null) {
        $token = new LCFA_OAuth_Access_Token_Entity(
            LCFA_OAuth_Storage::resource_url(),
            LCFA_OAuth_Storage::issuer_url(),
            LCFA_OAuth_Storage::site_fingerprint()
        );
        $token->setClient($clientEntity);
        $token->setUserIdentifier($userIdentifier);
        foreach ($scopes as $scope) {
            $token->addScope($scope);
        }

        return $token;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity) {
        $scopes = array_map(
            static function ($scope): string {
                return (string) $scope->getIdentifier();
            },
            $accessTokenEntity->getScopes()
        );
        $persisted = LCFA_OAuth_Storage::persist_access_token(
            (string) $accessTokenEntity->getIdentifier(),
            (string) $accessTokenEntity->getClient()->getIdentifier(),
            (int) $accessTokenEntity->getUserIdentifier(),
            $scopes,
            $accessTokenEntity->getExpiryDateTime()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')
        );

        if (!$persisted) {
            throw UniqueTokenIdentifierConstraintViolationException::create();
        }
    }

    public function revokeAccessToken($tokenId) {
        LCFA_OAuth_Storage::revoke_access_token((string) $tokenId);
    }

    public function isAccessTokenRevoked($tokenId) {
        return LCFA_OAuth_Storage::is_access_token_revoked((string) $tokenId);
    }
}

final class LCFA_OAuth_Auth_Code_Repository implements AuthCodeRepositoryInterface {
    public function getNewAuthCode() {
        return new LCFA_OAuth_Auth_Code_Entity();
    }

    public function persistNewAuthCode(\League\OAuth2\Server\Entities\AuthCodeEntityInterface $authCodeEntity) {
        $persisted = LCFA_OAuth_Storage::persist_auth_code(
            (string) $authCodeEntity->getIdentifier(),
            (string) $authCodeEntity->getClient()->getIdentifier(),
            (int) $authCodeEntity->getUserIdentifier(),
            $authCodeEntity->getExpiryDateTime()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')
        );

        if (!$persisted) {
            throw UniqueTokenIdentifierConstraintViolationException::create();
        }
    }

    public function revokeAuthCode($codeId) {
        LCFA_OAuth_Storage::revoke_auth_code((string) $codeId);
    }

    public function isAuthCodeRevoked($codeId) {
        return LCFA_OAuth_Storage::is_auth_code_revoked((string) $codeId);
    }
}

final class LCFA_OAuth_Refresh_Token_Repository implements RefreshTokenRepositoryInterface {
    public function getNewRefreshToken() {
        return new LCFA_OAuth_Refresh_Token_Entity();
    }

    public function persistNewRefreshToken(\League\OAuth2\Server\Entities\RefreshTokenEntityInterface $refreshTokenEntity) {
        $access_token = $refreshTokenEntity->getAccessToken();
        $persisted = LCFA_OAuth_Storage::persist_refresh_token(
            (string) $refreshTokenEntity->getIdentifier(),
            (string) $access_token->getIdentifier(),
            (string) $access_token->getClient()->getIdentifier(),
            (int) $access_token->getUserIdentifier(),
            $refreshTokenEntity->getExpiryDateTime()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')
        );

        if (!$persisted) {
            throw UniqueTokenIdentifierConstraintViolationException::create();
        }
    }

    public function revokeRefreshToken($tokenId) {
        LCFA_OAuth_Storage::revoke_refresh_token((string) $tokenId);
    }

    public function isRefreshTokenRevoked($tokenId) {
        return LCFA_OAuth_Storage::is_refresh_token_revoked((string) $tokenId);
    }
}
