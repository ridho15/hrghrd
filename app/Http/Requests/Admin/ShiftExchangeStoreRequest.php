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
            'type'            => 'required|in:replacement,swap',
            'replacement_id'  => 'required_if:type,replacement|nullable|integer|exists:users,id',
            'target_shift_id' => 'required_if:type,swap|nullable|integer|exists:shifts,id',
            'reason'          => 'required|string|min:5|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'             => 'Tipe permohonan wajib dipilih (ganti jadwal atau tukar shift).',
            'replacement_id.required_if'=> 'Pilih karyawan pengganti.',
            'target_shift_id.required_if' => 'Pilih jadwal shift rekan yang ingin ditukar.',
            'reason.required'           => 'Alasan pergantian shift wajib diisi.',
            'reason.min'                => 'Alasan pergantian minimal 5 karakter.',
            'reason.max'                => 'Alasan pergantian maksimal 500 karakter.',
        ];
    }
}
