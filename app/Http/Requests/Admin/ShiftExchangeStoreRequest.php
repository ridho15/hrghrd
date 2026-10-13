<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ShiftExchangeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'type'               => 'required|in:replacement,swap,change_shift,change_off_day',
            'replacement_id'     => 'required_if:type,replacement|nullable|integer|exists:users,id',
            'target_shift_id'    => 'required_if:type,swap,change_shift,change_off_day|nullable|integer|exists:shifts,id',
            'requester_shift_id' => 'nullable|integer|exists:shifts,id',
            'target_date'        => 'nullable|date',
            'reason'             => 'required|string|min:5|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'              => 'Tipe permohonan wajib dipilih (ganti jadwal, tukar shift, atau ganti jadwal kosong/libur).',
            'type.in'                    => 'Tipe permohonan tidak valid.',
            'replacement_id.required_if' => 'Pilih karyawan pengganti.',
            'target_shift_id.required_if'=> 'Pilih jadwal shift rekan atau jadwal kosong yang ingin diambil.',
            'reason.required'            => 'Alasan pergantian shift wajib diisi.',
            'reason.min'                 => 'Alasan pergantian minimal 5 karakter.',
            'reason.max'                 => 'Alasan pergantian maksimal 500 karakter.',
        ];
    }
}
