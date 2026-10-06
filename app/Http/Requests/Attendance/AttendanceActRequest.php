<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceActRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && (bool) auth()->user()->active;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('qr_code')) {
            $this->merge([
                'qr_code' => strtoupper(trim((string) $this->input('qr_code'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'action' => 'required|in:in,out',
            'qr_code' => 'nullable|string|max:20',
            'challenge' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0|max:10000',
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Aksi presensi (check-in/check-out) wajib ditentukan.',
            'action.in' => 'Tindakan presensi tidak valid.',
            'latitude.between' => 'Koordinat lintang di luar batas valid.',
            'longitude.between' => 'Koordinat bujur di luar batas valid.',
        ];
    }
}
