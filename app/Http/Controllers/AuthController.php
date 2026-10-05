<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function loginForm() { return view('login'); }

    public function login(Request $request)
    {
        $data=$request->validate(['email'=>'required|email','password'=>'required|string']);
        if (!Auth::attempt(['email'=>$data['email'],'password'=>$data['password'],'active'=>1]))
            return back()->withErrors(['email'=>'Email atau sandi salah, atau akun tidak aktif.']);
        $request->session()->regenerate();
        if (!$request->cookie('hrd_device')) {
            $token=Str::random(48);
            return redirect()->route('home')->cookie('hrd_device',$token,60*24*365,'/',null,$request->isSecure(),true,false,'Lax');
        }
        return redirect()->route('home');
    }

    public function logout(Request $request)
    {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
