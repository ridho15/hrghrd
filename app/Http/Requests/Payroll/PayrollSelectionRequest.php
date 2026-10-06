<?php

namespace App\Http\Requests\Payroll;

use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;

class PayrollSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::admin();
    }

    public function rules(): array
    {
        return [
            'branch_id' => 'required|exists:branches,id',
            'month' => 'required|date_format:Y-m',
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'Cabang wajib dipilih.',
            'branch_id.exists' => 'Cabang tidak ditemukan.',
            'month.required' => 'Bulan periode payroll wajib diisi.',
            'month.date_format' => 'Format periode bulan harus TTTT-BB (contoh: 2026-09).',
        ];
    }
}
