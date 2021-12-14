<?php

namespace Aloko\Keycloak;

use Aloko\Keycloak\Exceptions\FetchTokenFailedException;
use Aloko\Keycloak\Token\JWTParser;
use Aloko\Keycloak\Token\Token;
use Aloko\Keycloak\Token\TokenBag;
use Aloko\Keycloak\Token\TokenManager;
use Illuminate\Support\Arr;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Stevenmaguire\OAuth2\Client\Provider\Keycloak;

class KeycloakManager
{
    protected Keycloak $provider;

    private TokenManager $tokenManager;

    public function __construct(array $config, TokenManager $tokenManager)
    {
        $this->provider = new Keycloak([
            'authServerUrl' => $config['server_url'],
            'realm' => $config['realm'],
            'clientId' => $config['client_id'],
            'clientSecret' => $config['client_secret'],
            'redirectUri' => $this->isUrl($config['redirect_uri']) ? $config['redirect_uri'] : url($config['redirect_uri']),
            'encryptionAlgorithm' => $config['realm_encryption_algo'],
            'encryptionKey' => $config['realm_public_key']
        ]);

        $this->tokenManager = $tokenManager;
    }

    public function getAuthorizationUrl(array $options = []): string
    {
        return $this->provider->getAuthorizationUrl(
            array_merge(['scope' => ['openid', 'profile', 'email']], $options)
        );
    }

    public function getState(): string
    {
        return $this->provider->getState();
    }

    /**
     * @throws \Aloko\Keycloak\Exceptions\FetchTokenFailedException
     */
    public function fetchToken($code): TokenBag
    {
        try {
            $token = $this->provider->getAccessToken('authorization_code', [
                'code' => $code
            ]);

            return $this->tokenManager->createBag($token);
        } catch (IdentityProviderException $e) {
            throw new FetchTokenFailedException('Fetching access token failed: ' . $e->getMessage());
        }
    }

    /**
     * @throws \Aloko\Keycloak\Exceptions\FetchTokenFailedException
     */
    public function refreshToken(TokenBag $oldTokenBag): TokenBag
    {
        try {
            $newToken = $this->provider->getAccessToken('refresh_token', [
                'refresh_token' => $oldTokenBag->refreshToken()->encoded()
            ]);

            return $this->tokenManager->createBag($newToken);
        } catch (IdentityProviderException $e) {
            throw new FetchTokenFailedException('Fetching refresh token failed: ' . $e->getMessage());
        }
    }

    public function getLogoutUrl(array $options = []): string
    {
        return $this->provider->getLogoutUrl(
            array_merge([
                'redirect_uri' => Arr::pull($options, 'redirect_uri', '/auth/logout/callback')
            ], $options)
        );
    }

    /**
     * @throws \Aloko\Keycloak\Exceptions\TokenSignatureVerificationFailedException
     */
    public function verifyTokenSignature(TokenBag $tokenBag)
    {
        $this->tokenManager->verifySignature($tokenBag->accessToken()->encoded());
    }

    public function unserializeToken(array $token): TokenBag
    {
        return $this->tokenManager->unserializeToken($token);
    }

    protected function isUrl(string $string)
    {
        return filter_var($string, FILTER_VALIDATE_URL);
    }
}
