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
            'user_id' => 'nullable|exists:users,id',
            'type' => 'required|in:leave,sick',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|min:10|max:1000',
            'certificate' => 'required_if:type,sick|nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
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

                if ($this->input('type') === 'leave') {
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
                            $validator->errors()->add('end_date', "Sisa kuota cuti tahunan tidak mencukupi (tersisa {$remaining} hari, diajukan {$requestedDays} hari).");
                        }
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Jenis pengajuan izin wajib dipilih.',
            'type.in' => 'Jenis pengajuan harus berupa izin atau sakit.',
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
