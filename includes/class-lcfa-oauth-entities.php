<?php

defined('ABSPATH') || exit;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Entities\Traits\AuthCodeTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;
use League\OAuth2\Server\Entities\Traits\ScopeTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

final class LCFA_OAuth_Client_Entity implements ClientEntityInterface {
    use EntityTrait;

    private string $name;
    private array $redirect_uris;

    public function __construct(string $identifier, string $name, array $redirect_uris) {
        $this->identifier = $identifier;
        $this->name = $name;
        $this->redirect_uris = array_values($redirect_uris);
    }

    public function getName(): string {
        return $this->name;
    }

    public function getRedirectUri(): array {
        return $this->redirect_uris;
    }

    public function isConfidential(): bool {
        return false;
    }
}

final class LCFA_OAuth_Scope_Entity implements ScopeEntityInterface {
    use EntityTrait;
    use ScopeTrait;

    public function __construct(string $identifier) {
        $this->identifier = $identifier;
    }
}

final class LCFA_OAuth_User_Entity implements UserEntityInterface {
    use EntityTrait;

    public function __construct(int $user_id) {
        $this->identifier = $user_id;
    }
}

final class LCFA_OAuth_Auth_Code_Entity implements AuthCodeEntityInterface {
    use EntityTrait;
    use TokenEntityTrait;
    use AuthCodeTrait;
}

final class LCFA_OAuth_Refresh_Token_Entity implements RefreshTokenEntityInterface {
    use EntityTrait;
    use RefreshTokenTrait;
}

final class LCFA_OAuth_Access_Token_Entity implements AccessTokenEntityInterface {
    use EntityTrait;
    use TokenEntityTrait;

    private ?CryptKey $private_key = null;
    private string $resource;
    private string $issuer;
    private string $site_fingerprint;

    public function __construct(string $resource, string $issuer, string $site_fingerprint) {
        $this->resource = $resource;
        $this->issuer = $issuer;
        $this->site_fingerprint = $site_fingerprint;
    }

    public function setPrivateKey(CryptKey $private_key): void {
        $this->private_key = $private_key;
    }

    public function __toString(): string {
        if (!$this->private_key instanceof CryptKey) {
            throw new LogicException('OAuth access token signing key is missing.');
        }

        $configuration = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText(
                $this->private_key->getKeyContents(),
                $this->private_key->getPassPhrase() ?? ''
            ),
            InMemory::plainText('unused-public-key')
        );

        $scope_ids = array_map(
            static function (ScopeEntityInterface $scope): string {
                return (string) $scope->getIdentifier();
            },
            $this->getScopes()
        );

        return $configuration->builder()
            ->issuedBy($this->issuer)
            ->permittedFor($this->resource)
            ->identifiedBy((string) $this->getIdentifier())
            ->issuedAt(new \DateTimeImmutable())
            ->canOnlyBeUsedAfter(new \DateTimeImmutable())
            ->expiresAt($this->getExpiryDateTime())
            ->relatedTo((string) $this->getUserIdentifier())
            ->withClaim('client_id', (string) $this->getClient()->getIdentifier())
            ->withClaim('scopes', $scope_ids)
            ->withClaim('site_fingerprint', $this->site_fingerprint)
            ->getToken($configuration->signer(), $configuration->signingKey())
            ->toString();
    }
}
