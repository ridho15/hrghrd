<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ShiftExchangeOpenSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'type'               => 'required|in:change_shift,change_off_day',
            'target_shift_id'    => 'required|integer|exists:shifts,id',
            'requester_shift_id' => 'required_if:type,change_shift|nullable|integer|exists:shifts,id',
            'off_date'           => 'nullable|date',
            'reason'             => 'required|string|min:5|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'                 => 'Tipe permohonan wajib dipilih (change_shift atau change_off_day).',
            'type.in'                       => 'Tipe permohonan harus change_shift (ganti shift) atau change_off_day (ganti libur).',
            'target_shift_id.required'      => 'Jadwal kosong tujuan wajib dipilih.',
            'target_shift_id.exists'        => 'Jadwal kosong tidak ditemukan di sistem.',
            'requester_shift_id.required_if'=> 'Jadwal shift asal wajib dipilih untuk permohonan ganti shift.',
            'requester_shift_id.exists'     => 'Jadwal shift asal tidak ditemukan.',
            'reason.required'               => 'Alasan pergantian wajib diisi.',
            'reason.min'                    => 'Alasan minimal 5 karakter.',
            'reason.max'                    => 'Alasan maksimal 500 karakter.',
        ];
    }
}
