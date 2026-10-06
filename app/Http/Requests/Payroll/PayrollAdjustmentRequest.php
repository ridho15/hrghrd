<?php

namespace App\Http\Requests\Payroll;

use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;

class PayrollAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::admin();
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'month' => 'required|date_format:Y-m',
            'amount' => 'required|integer|between:-100000000,100000000',
            'reason' => 'required|string|min:10|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Karyawan wajib dipilih.',
            'user_id.exists' => 'Karyawan tidak ditemukan.',
            'month.required' => 'Bulan periode koreksi wajib diisi.',
            'month.date_format' => 'Format bulan harus TTTT-BB.',
            'amount.required' => 'Nominal koreksi wajib diisi.',
            'amount.between' => 'Nominal penyesuaian harus antara -100.000.000 hingga 100.000.000 Rupiah.',
            'reason.required' => 'Alasan penyesuaian wajib diisi.',
            'reason.min' => 'Alasan penyesuaian minimal 10 karakter.',
        ];
    }
}
