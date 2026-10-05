@extends('layouts.app')
@section('title','QR cabang')
@section('content')
<div class="page-head"><div><div class="eyebrow">KODE LOKASI</div><h1>QR cabang</h1><p>Tampilkan layar ini di cabang. Kode berubah setiap 30 detik dan memakai waktu server.</p></div></div>
<section class="card qr-card"><form method="get" action="{{ route('qr.page') }}" class="inline-form"><label>Cabang<select name="branch_id" onchange="this.form.submit()">@foreach($branches as $b)<option value="{{ $b->id }}" @selected($branch->id==$b->id)>{{ $b->name }}</option>@endforeach</select></label></form>
<div class="qr-display" data-qr-url="{{ route('qr.code',['branch_id'=>$branch->id]) }}"><canvas width="256" height="256" aria-label="QR kode cabang"></canvas><strong class="qr-code">Memuat...</strong><span class="qr-timer"></span></div><p class="hint">Karyawan dapat memindai QR atau memasukkan delapan karakter di bawahnya. Lokasi GPS dan perangkat tetap diperiksa.</p></section>
@endsection
