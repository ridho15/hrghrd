@extends('layouts.app')
@section('title','Masuk')
@section('content')
<div class="login-wrap"><section class="card login-card"><div class="eyebrow">PORTAL KARYAWAN</div><h1>Selamat datang.</h1><p>Masuk untuk melihat jadwal, absensi, izin, dan informasi tim Anda.</p>
<form method="post" action="{{ route('login') }}" class="stack">@csrf<label>Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></label>
<label>Kata sandi<input type="password" name="password" autocomplete="current-password" required></label><button class="button primary wide">Masuk</button></form></section>
<aside class="login-aside"><span class="hero-icon">↗</span><h2>Satu tempat untuk hari kerja.</h2><p>Lihat shift berikutnya, ajukan izin, dan catat kehadiran dengan bukti yang jelas.</p></aside></div>
@endsection
