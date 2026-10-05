@extends('layouts.app')
@section('title','Izin & sakit')
@section('content')
<div class="page-head"><div><div class="eyebrow">TAHAP 4</div><h1>Izin & sakit</h1><p>Izin biasa minimal H-{{ \App\Support\Rules::int('leave_notice_days') }}. Sakit wajib menyertakan surat dan menunggu persetujuan.</p></div></div>
<section class="card"><h2>Ajukan</h2><form method="post" action="{{ route('leave.store') }}" enctype="multipart/form-data" class="form-grid leave-form">@csrf
@if(in_array(auth()->user()->role,['admin','manager']))<label>Karyawan<select name="user_id"><option value="{{ auth()->id() }}">Saya sendiri</option>@foreach($people as $p)@if($p->id!==auth()->id())<option value="{{ $p->id }}">{{ $p->name }}</option>@endif @endforeach</select></label>@endif
<label>Jenis<select name="type" id="leave-type"><option value="leave">Izin</option><option value="sick">Sakit</option></select></label><label>Tanggal mulai<input type="date" name="start_date" required></label><label>Tanggal selesai<input type="date" name="end_date" required></label>
<label class="full">Alasan<textarea name="reason" minlength="10" maxlength="1000" required placeholder="Jelaskan alasan pengajuan"></textarea></label>
<label class="full">Surat keterangan sakit <span class="hint">PDF/JPG/PNG, maks. 5 MB; wajib untuk sakit.</span><input type="file" name="certificate" accept=".pdf,.jpg,.jpeg,.png"></label><button class="button primary">Kirim pengajuan</button></form></section>
<section class="card"><div class="section-head"><h2>Pengajuan</h2><span class="badge">{{ $requests->count() }}</span></div>
@forelse($requests as $r)<article class="request-card"><div class="request-top"><div><strong>{{ $r->type==='sick'?'Sakit':'Izin' }} · {{ $r->employee_name }}</strong><small>{{ $r->start_date }} s.d. {{ $r->end_date }} · diajukan {{ substr($r->created_at,0,16) }}</small></div><span class="badge {{ $r->status==='approved'?'good':($r->status==='rejected'?'danger':'') }}">{{ ['pending'=>'Menunggu','approved'=>'Disetujui','partial'=>'Sebagian','rejected'=>'Ditolak'][$r->status] }}</span></div><p>{{ $r->reason }}</p>
@if($r->certificate_path)<a href="{{ route('leave.certificate',$r->id) }}">Lihat surat keterangan ↗</a>@endif
<div class="day-chips">@foreach($days[$r->id] ?? [] as $d)<span class="badge {{ $d->status==='approved'?'good':($d->status==='rejected'?'danger':'') }}">{{ $d->date }}: {{ $d->status==='approved'?($d->paid?'dibayar':'tidak dibayar'):($d->status==='rejected'?'ditolak':'menunggu') }}</span>@endforeach</div>
@if($r->review_note)<p class="hint">Catatan peninjau: {{ $r->review_note }}</p>@endif
@if(in_array(auth()->user()->role,['admin','manager']) && $r->status==='pending' && $r->created_by!==auth()->id())<details><summary>Putuskan per tanggal</summary><form method="post" action="{{ route('leave.review',$r->id) }}" class="stack compact">@csrf
<p class="hint">Centang tanggal yang disetujui. Tanggal lain ditolak. Untuk sakit, dua hari pertama yang disetujui dibayar otomatis.</p>
@foreach($days[$r->id] ?? [] as $d)<div class="date-choice"><label class="check"><input type="checkbox" name="approved_dates[]" value="{{ $d->date }}">Setujui {{ $d->date }}</label>@if($r->type==='leave')<label class="check"><input type="checkbox" name="paid_dates[]" value="{{ $d->date }}">Dibayar</label>@endif</div>@endforeach
<label>Catatan keputusan<textarea name="review_note" required minlength="5"></textarea></label><button class="button primary small">Simpan keputusan</button></form></details>@endif</article>
@empty<p class="empty">Belum ada pengajuan.</p>@endforelse</section>
@endsection
