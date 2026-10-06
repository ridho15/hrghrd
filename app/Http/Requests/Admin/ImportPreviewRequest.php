<?php

namespace App\Http\Requests\Admin;

use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;

class ImportPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::admin();
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|max:5120|mimes:csv,txt,xlsx',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Berkas data karyawan wajib diunggah.',
            'file.max' => 'Ukuran berkas maksimal 5 MB.',
            'file.mimes' => 'Format berkas harus berupa CSV atau XLSX.',
        ];
    }
}
