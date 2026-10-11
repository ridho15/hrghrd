<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user) {
            $requiresChange = $user->must_change_password || Hash::check('12345678', $user->password);

            if ($requiresChange) {
                if (
                    $request->is('logout') ||
                    $request->routeIs('logout') ||
                    $request->is('password/force-change') ||
                    $request->routeIs('password.force_change') ||
                    $request->routeIs('password.force_change.update') ||
                    $request->routeIs('password.update')
                ) {
                    return $next($request);
                }

                return redirect()->route('password.force_change')
                    ->with('warning', 'Kata sandi akun Anda masih menggunakan kata sandi default/reset. Demi keamanan, Anda wajib membuat kata sandi baru terlebih dahulu.');
            }
        }

        return $next($request);
    }
}
