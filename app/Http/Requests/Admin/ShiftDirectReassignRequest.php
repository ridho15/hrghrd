<?php

namespace App\Http\Requests\Admin;

use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;

class ShiftDirectReassignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::manager();
    }

    public function rules(): array
    {
        return [
            'replacement_id' => 'required|integer|exists:users,id',
            'reason'         => 'required|string|min:5|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'replacement_id.required' => 'Pilih karyawan pengganti.',
            'replacement_id.exists'   => 'Karyawan pengganti tidak valid.',
            'reason.required'         => 'Alasan penugasan langsung wajib diisi.',
            'reason.min'              => 'Alasan penugasan minimal 5 karakter.',
            'reason.max'              => 'Alasan penugasan maksimal 500 karakter.',
        ];
    }
}
