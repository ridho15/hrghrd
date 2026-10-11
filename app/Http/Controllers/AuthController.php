<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ForcePasswordUpdateRequest;
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

        $user = Auth::user();
        $requiresPasswordChange = $user && ($user->must_change_password || Hash::check('12345678', $user->password));
        $redirectUrl = $requiresPasswordChange ? route('password.force_change') : route('home');

        if (!$request->cookie('hrd_device')) {
            $token = Str::random(48);
            return redirect($redirectUrl)->cookie('hrd_device', $token, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'Lax');
        }

        return redirect($redirectUrl);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function forceChangePasswordView()
    {
        $user = auth()->user();
        $requiresPasswordChange = $user && ($user->must_change_password || Hash::check('12345678', $user->password));

        if (! $requiresPasswordChange) {
            return redirect()->route('home');
        }

        return view('password-force-change', compact('user'));
    }

    public function forceChangePasswordUpdate(ForcePasswordUpdateRequest $request)
    {
        $user = auth()->user();
        $user->update([
            'password' => Hash::make($request->input('password')),
            'must_change_password' => false,
        ]);

        Audit::record('user', $user->id, 'force_password_change', 'Karyawan ' . $user->name . ' berhasil memperbarui kata sandi wajib ganti pertama kali.');

        return redirect()->route('home')->with('ok', 'Kata sandi Anda berhasil diperbarui. Selamat datang di sistem HRG HRD!');
    }

    public function updatePassword(PasswordUpdateRequest $request)
    {
        $data = $request->validated();
        $targetId = (int) ($data['target_user_id'] ?? auth()->id());
        $user = User::findOrFail($targetId);

        $user->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ]);

        Audit::record('user', $user->id, 'password_change', 'Kata sandi berhasil diperbarui oleh ' . auth()->user()->name);

        return back()->with('ok', 'Kata sandi berhasil diperbarui.');
    }
}
