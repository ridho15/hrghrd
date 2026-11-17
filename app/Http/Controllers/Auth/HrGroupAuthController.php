<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\HrGroup\AccountResolver;
use App\Services\HrGroup\HrGroupClient;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class HrGroupAuthController extends Controller
{
    public function redirect(Request $request)
    {
        $client = HrGroupClient::fromConfig();
        if (!$client) {
            return redirect()->route('login')->with('error', 'Integrasi SSO Keycloak sedang dinonaktifkan.');
        }

        $verifier = HrGroupClient::generateCodeVerifier();
        $challenge = HrGroupClient::generateCodeChallenge($verifier);
        $state = HrGroupClient::generateState();

        // Simpan di sesi untuk proteksi CSRF dan PKCE
        session([
            'hrg_pkce_verifier' => $verifier,
            'hrg_oauth_state' => $state,
        ]);

        $authUrl = $client->buildAuthorizationUrl($state, $challenge);

        return redirect()->away($authUrl);
    }

    public function callback(Request $request, AccountResolver $resolver)
    {
        $client = HrGroupClient::fromConfig();
        if (!$client) {
            return redirect()->route('login')->with('error', 'Layanan SSO Keycloak tidak tersedia.');
        }

        if ($request->has('error')) {
            $errorMsg = $request->get('error_description') ?: $request->get('error');
            return redirect()->route('login')->with('error', "Autentikasi SSO dibatalkan: {$errorMsg}");
        }

        $code = $request->get('code');
        $state = $request->get('state');
        $savedState = session('hrg_oauth_state');
        $verifier = session('hrg_pkce_verifier');

        if (!$code || !$state || $state !== $savedState || !$verifier) {
            return redirect()->route('login')->with('error', 'Sesi autentikasi SSO kedaluwarsa atau tidak valid (State mismatch).');
        }

        try {
            // Tukar authorization code dengan tokens
            $tokens = $client->exchangeCode($code, $verifier);
            $accessPayload = $client->parseJwtPayload($tokens['access_token'] ?? '');
            $idPayload = $client->parseJwtPayload($tokens['id_token'] ?? '');

            // Gabungkan klaim
            $claims = array_merge($idPayload, $accessPayload);

            // Resolusi ke pengguna lokal
            $user = $resolver->resolve($claims);

            // Bersihkan sesi handshake
            session()->forget(['hrg_pkce_verifier', 'hrg_oauth_state']);
            session(['hrg_id_token' => $tokens['id_token'] ?? null]);

            // Login ke sesi Laravel
            Auth::login($user, true);

            return redirect()->intended(route('home'))->with('success', "Selamat datang kembali, {$user->name}!");
        } catch (Exception $e) {
            Log::error('HR Group SSO Callback Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->route('login')->with('error', "Gagal masuk via SSO: {$e->getMessage()}");
        }
    }

    public function logout(Request $request)
    {
        $idToken = session('hrg_id_token');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $client = HrGroupClient::fromConfig();
        if ($client) {
            return redirect()->away($client->buildEndSessionUrl($idToken));
        }

        return redirect()->route('login');
    }
}
