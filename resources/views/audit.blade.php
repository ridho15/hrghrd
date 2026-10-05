@extends('layouts.app')
@section('title','Jejak audit')
@section('content')
<div class="page-head"><div><div class="eyebrow">KONTROL</div><h1>Jejak audit</h1><p>200 perubahan terbaru, termasuk alasan koreksi dan identitas pemberi persetujuan.</p></div></div>
<section class="card"><div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Pelaku</th><th>Objek</th><th>Tindakan</th><th>Alasan / detail</th></tr></thead><tbody>@forelse($events as $e)<tr><td>{{ $e->created_at }}</td><td>{{ $e->actor_name ?? 'Sistem' }}</td><td>{{ $e->subject_type }} #{{ $e->subject_id }}</td><td><strong>{{ $e->action }}</strong></td><td>{{ $e->reason ?? '—' }}@if($e->before || $e->after)<details><summary>Lihat perubahan</summary><small>Sebelum: {{ $e->before ?? '—' }}</small><small>Sesudah: {{ $e->after ?? '—' }}</small></details>@endif</td></tr>@empty<tr><td colspan="5" class="empty">Belum ada perubahan.</td></tr>@endforelse</tbody></table></div></section>
@endsection
