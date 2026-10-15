<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;

class PersonUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::admin();
    }

    protected function prepareForValidation(): void
    {
        $id = (int) $this->route('id');
        $currentUser = User::find($id);

        $merges = [];

        // Proteksi: Jika akun yang diedit adalah akun admin sendiri, pastikan tetap admin dan aktif
        if ($id === (int) auth()->id()) {
            $merges['role'] = 'admin';
            $merges['active'] = true;
        } elseif (! $this->has('active')) {
            // Jika field active tidak dikirimkan, gunakan status akun saat ini
            $merges['active'] = $currentUser ? (bool) $currentUser->active : true;
        }

        if (! empty($merges)) {
            $this->merge($merges);
        }
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name' => 'required|string|max:120',
            'username' => 'nullable|string|max:60|unique:users,username,' . $id,
            'email' => 'required|email|unique:users,email,' . $id,
            'role' => 'required|in:admin,manager,employee',
            'schedule_type' => 'nullable|in:shift,office_hours',
            'branch_id' => 'nullable|required_unless:role,admin|exists:branches,id',
            'position_id' => 'nullable|exists:positions,id',
            'bank_name' => 'nullable|string|max:60',
            'bank_account_number' => 'nullable|string|max:60',
            'bank_account_name' => 'nullable|string|max:120',
            'hired_at' => 'required|date',
            'ended_at' => 'nullable|date|after_or_equal:hired_at',
            'base_salary' => 'required|integer|min:0',
            'active' => 'required|boolean',
            'password' => 'nullable|string|min:8',
            'reset_device' => 'nullable|boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $id = (int) $this->route('id');
            if ($id === (int) auth()->id() && ($this->input('role') !== 'admin' || !$this->boolean('active'))) {
                $validator->errors()->add('role', 'Akun admin sendiri tidak boleh dinonaktifkan atau diturunkan.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.unique' => 'Email sudah digunakan oleh akun lain.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'branch_id.required_unless' => 'Cabang wajib dipilih untuk peran selain admin.',
            'ended_at.after_or_equal' => 'Tanggal akhir kerja harus sama atau setelah tanggal mulai.',
            'base_salary.min' => 'Gaji bulanan tidak boleh bernilai negatif.',
            'active.required' => 'Status keaktifan akun wajib ditentukan.',
            'active.boolean' => 'Status keaktifan akun harus bernilai aktif atau nonaktif.',
        ];
    }
}
