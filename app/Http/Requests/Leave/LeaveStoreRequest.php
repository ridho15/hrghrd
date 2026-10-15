<?php

namespace App\Http\Requests\Leave;

use App\Support\Access;
use App\Support\Rules;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class LeaveStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $userId = (int) ($this->input('user_id') ?: auth()->id());

        if (!Access::employee($userId)) {
            return false;
        }

        if ($userId !== auth()->id() && !Access::manager()) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'           => 'nullable|exists:users,id',
            'original_leave_id' => 'required_if:type,leave_exchange|nullable|exists:leave_requests,id',
            'type'              => 'required|in:leave,unpaid_leave,sick,day_off,leave_exchange',
            'start_date'        => 'required|date',
            'end_date'          => 'required|date|after_or_equal:start_date',
            'reason'            => 'required|string|min:10|max:1000',
            'certificate'       => 'required_if:type,sick|nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('start_date') && $this->has('end_date')) {
                $start = Carbon::parse($this->input('start_date'), 'Asia/Jakarta');
                $end = Carbon::parse($this->input('end_date'), 'Asia/Jakarta');

                if ($start->diffInDays($end) > 30) {
                    $validator->errors()->add('end_date', 'Maksimal 31 hari per pengajuan.');
                }

                $type = $this->input('type');

                if (in_array($type, ['day_off', 'leave_exchange'], true) && !Access::manager()) {
                    $validator->errors()->add('type', 'Pengaturan hari libur dan pergantian cuti saat ini dikelola terpusat oleh Super Admin / HRD.');
                }

                if ($type === 'leave') {
                    $minDate = now('Asia/Jakarta')->startOfDay()->addDays(Rules::int('leave_notice_days'));
                    if ($start->lt($minDate)) {
                        $validator->errors()->add('start_date', 'Izin biasa harus diajukan minimal H-' . Rules::int('leave_notice_days') . '.');
                    }

                    $userId = (int) ($this->input('user_id') ?: auth()->id());
                    $user = \App\Models\User::find($userId);
                    if ($user) {
                        $requestedDays = (int) $start->copy()->daysUntil($end)->count();
                        $remaining = $user->remainingLeaveDays($start->year);
                        if ($requestedDays > $remaining) {
                            $msg = $user->role === 'employee'
                                ? 'Pengajuan cuti tahunan tidak dapat diproses karena melebihi batas yang diizinkan perusahaan. Silakan koordinasikan dengan atasan atau HRD.'
                                : "Sisa kuota cuti tahunan tidak mencukupi (tersisa {$remaining} hari, diajukan {$requestedDays} hari).";
                            $validator->errors()->add('end_date', $msg);
                        }
                    }
                } elseif ($type === 'unpaid_leave') {
                    if ($start->lt(now('Asia/Jakarta')->startOfDay())) {
                        $validator->errors()->add('start_date', 'Cuti tidak berbayar tidak dapat diajukan untuk tanggal yang telah lewat.');
                    }
                } elseif ($type === 'sick') {
                    $minSickDate = now('Asia/Jakarta')->subDays(3)->startOfDay();
                    if ($start->lt($minSickDate)) {
                        $validator->errors()->add('start_date', 'Pengajuan izin sakit maksimal diajukan untuk 3 hari ke belakang.');
                    }
                } elseif ($type === 'leave_exchange') {
                    $origId = $this->input('original_leave_id');
                    $userId = (int) ($this->input('user_id') ?: auth()->id());
                    if ($origId) {
                        $orig = \App\Models\LeaveRequest::find($origId);
                        if ($orig && (int) $orig->user_id !== $userId) {
                            $validator->errors()->add('original_leave_id', 'Pengajuan cuti yang dipilih bukan milik karyawan ini.');
                        }
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Jenis pengajuan perizinan/libur wajib dipilih.',
            'type.in' => 'Jenis pengajuan harus berupa cuti tahunan, cuti tidak berbayar, sakit, hari libur, atau pergantian cuti.',
            'original_leave_id.required_if' => 'Permohonan cuti awal yang ingin diganti wajib dipilih.',
            'start_date.required' => 'Tanggal mulai izin wajib diisi.',
            'end_date.required' => 'Tanggal selesai izin wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'reason.required' => 'Alasan perizinan wajib diisi.',
            'reason.min' => 'Alasan perizinan minimal 10 karakter.',
            'certificate.required_if' => 'Surat keterangan dokter wajib dilampirkan untuk izin sakit.',
            'certificate.max' => 'Ukuran berkas surat dokter maksimal 5 MB.',
            'certificate.mimes' => 'Format surat dokter harus PDF, JPG, JPEG, atau PNG.',
        ];
    }
}
