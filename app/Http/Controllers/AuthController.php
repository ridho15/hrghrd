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

        if (!Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'active' => 1])) {
            return back()->withErrors(['email' => 'Email atau sandi salah, atau akun tidak aktif.']);
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
