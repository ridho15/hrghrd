<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\PasswordUpdateRequest;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function loginForm()
    {
        return view('login');
    }

    public function login(LoginRequest $request)
    {
        $data = $request->validated();
        $input = trim((string) ($data['login'] ?? $data['email'] ?? ''));
        $loginField = filter_var($input, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (!Auth::attempt([$loginField => $input, 'password' => $data['password'], 'active' => 1])) {
            $altField = $loginField === 'email' ? 'username' : 'email';
            if (!Auth::attempt([$altField => $input, 'password' => $data['password'], 'active' => 1])) {
                return back()->withInput($request->only('login', 'email'))
                    ->withErrors([
                        'login' => 'Username/email atau kata sandi salah, atau akun tidak aktif.',
                        'email' => 'Username/email atau kata sandi salah, atau akun tidak aktif.',
                    ]);
            }
        }

        $request->session()->regenerate();

        if (!$request->cookie('hrd_device')) {
            $token = Str::random(48);
            return redirect()->route('home')->cookie('hrd_device', $token, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'Lax');
        }

        return redirect()->route('home');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function updatePassword(PasswordUpdateRequest $request)
    {
        $data = $request->validated();
        $targetId = (int) ($data['target_user_id'] ?? auth()->id());
        $user = User::findOrFail($targetId);

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        Audit::record('user', $user->id, 'password_change', 'Kata sandi berhasil diperbarui oleh ' . auth()->user()->name);

        return back()->with('ok', 'Kata sandi berhasil diperbarui.');
    }
}
