<?php

namespace App\Http\Requests\Admin;

use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;

class PersonStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::admin();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:10',
            'role' => 'required|in:admin,manager,employee',
            'branch_id' => 'nullable|required_unless:role,admin|exists:branches,id',
            'position_id' => 'nullable|exists:positions,id',
            'hired_at' => 'required|date',
            'ended_at' => 'nullable|date|after_or_equal:hired_at',
            'base_salary' => 'required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.unique' => 'Email sudah terdaftar.',
            'password.required' => 'Kata sandi sementara wajib diisi.',
            'password.min' => 'Kata sandi minimal 10 karakter.',
            'role.required' => 'Peran pengguna wajib dipilih.',
            'role.in' => 'Peran tidak valid.',
            'branch_id.required_unless' => 'Cabang wajib dipilih untuk peran selain admin.',
            'branch_id.exists' => 'Cabang tidak ditemukan.',
            'hired_at.required' => 'Tanggal mulai bekerja wajib diisi.',
            'ended_at.after_or_equal' => 'Tanggal akhir kerja harus sama atau setelah tanggal mulai.',
            'base_salary.required' => 'Gaji bulanan wajib diisi.',
            'base_salary.min' => 'Gaji bulanan tidak boleh bernilai negatif.',
        ];
    }
}
