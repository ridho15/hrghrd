<?php

namespace App\Services\HrGroup;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HrGroupClient
{
    public function __construct(
        public readonly string $issuer,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $redirectUri,
        public readonly bool $enabled = true
    ) {}

    public static function fromConfig(): ?self
    {
        $enabled = config('services.hrgroup.enabled', env('HRG_ENABLED', false));
        if (!filter_var($enabled, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $issuer = rtrim(config('services.hrgroup.issuer', env('HRG_ISSUER', 'http://localhost:8080/realms/hrgroup')), '/');
        $clientId = config('services.hrgroup.client_id', env('HRG_CLIENT_ID', 'hrg-hrd'));
        $clientSecret = config('services.hrgroup.client_secret', env('HRG_CLIENT_SECRET', 'hrd_secret_local_2026'));
        $redirectUri = config('services.hrgroup.redirect_uri', env('HRG_REDIRECT_URI', 'http://localhost:8000/auth/hr-group/callback'));

        return new self($issuer, $clientId, $clientSecret, $redirectUri, true);
    }

    public static function generateCodeVerifier(): string
    {
        return Str::random(64);
    }

    public static function generateCodeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public static function generateState(): string
    {
        return Str::random(32);
    }

    public function buildAuthorizationUrl(string $state, string $codeChallenge): string
    {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'scope' => 'openid profile email roles',
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return "{$this->issuer}/protocol/openid-connect/auth?{$params}";
    }

    public function exchangeCode(string $code, string $codeVerifier): array
    {
        $tokenUrl = "{$this->issuer}/protocol/openid-connect/token";

        $response = Http::asForm()->timeout(15)->post($tokenUrl, [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'code_verifier' => $codeVerifier,
        ]);

        if (!$response->successful()) {
            throw new Exception("Gagal menukar kode otorisasi OIDC: {$response->status()} - {$response->body()}");
        }

        return $response->json();
    }

    public function parseJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return [];
        }

        $base64 = strtr($parts[1], '-_', '+/');
        $json = base64_decode($base64);

        return json_decode($json, true) ?? [];
    }

    public function buildEndSessionUrl(?string $idTokenHint = null): string
    {
        $params = [
            'client_id' => $this->clientId,
            'post_logout_redirect_uri' => url('/login'),
        ];

        if ($idTokenHint) {
            $params['id_token_hint'] = $idTokenHint;
        }

        return "{$this->issuer}/protocol/openid-connect/logout?" . http_build_query($params);
    }
}
