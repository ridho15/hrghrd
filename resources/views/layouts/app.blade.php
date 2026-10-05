<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0e766e"><title>@yield('title','HR Group') · HR Group</title>
@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body>
<header class="topbar"><div class="shell topbar-inner"><a class="brand" href="{{ route('home') }}"><span class="brand-mark">H</span><span>HR <b>Group</b></span></a>
@auth <div class="top-actions"><span class="user-chip">{{ auth()->user()->name }} <small>{{ ['admin'=>'Admin','manager'=>'Manager','employee'=>'Karyawan'][auth()->user()->role] ?? '' }}</small></span>
<form method="post" action="{{ route('logout') }}">@csrf<button class="text-button">Keluar</button></form></div>@endauth</div></header>
@auth <nav class="nav shell" aria-label="Menu utama"><a href="{{ route('home') }}" class="{{ request()->routeIs('home')?'selected':'' }}">Beranda</a><a href="{{ route('leave') }}" class="{{ request()->routeIs('leave*')?'selected':'' }}">Izin & sakit</a>
@if(in_array(auth()->user()->role,['admin','manager']))<a href="{{ route('shifts') }}" class="{{ request()->routeIs('shifts*')?'selected':'' }}">Shift</a><a href="{{ route('attendance.review') }}" class="{{ request()->routeIs('attendance.review')?'selected':'' }}">Tinjau absensi</a><a href="{{ route('qr.page') }}" class="{{ request()->routeIs('qr.*')?'selected':'' }}">QR cabang</a>@endif
@if(auth()->user()->role==='admin')<a href="{{ route('people') }}" class="{{ request()->routeIs('people*')?'selected':'' }}">Karyawan</a><a href="{{ route('import') }}" class="{{ request()->routeIs('import*')?'selected':'' }}">Impor</a><a href="{{ route('payroll.index') }}" class="{{ request()->routeIs('payroll*')?'selected':'' }}">Payroll</a><a href="{{ route('settings') }}" class="{{ request()->routeIs('settings*')?'selected':'' }}">Aturan</a><a href="{{ route('audit') }}" class="{{ request()->routeIs('audit')?'selected':'' }}">Audit</a>@endif</nav>@endauth
<main class="shell main">@if(session('ok'))<div class="alert success" role="status">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="alert error" role="alert"><strong>Periksa kembali:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')</main><footer class="shell footer">HR Group · waktu dan perhitungan menggunakan zona Asia/Jakarta</footer></body></html>
