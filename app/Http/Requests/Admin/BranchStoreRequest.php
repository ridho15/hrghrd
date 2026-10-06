<?php

namespace App\Http\Requests\Admin;

use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;

class BranchStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::admin();
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:20|unique:branches,code',
            'name' => 'required|string|max:100',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_m' => 'required|integer|between:20,1000',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode cabang wajib diisi.',
            'code.unique' => 'Kode cabang sudah digunakan.',
            'name.required' => 'Nama cabang wajib diisi.',
            'latitude.required' => 'Titik lintang (latitude) wajib diisi.',
            'latitude.between' => 'Titik lintang harus berada di antara -90 dan 90.',
            'longitude.required' => 'Titik bujur (longitude) wajib diisi.',
            'longitude.between' => 'Titik bujur harus berada di antara -180 dan 180.',
            'radius_m.required' => 'Radius area wajib diisi.',
            'radius_m.between' => 'Radius area harus antara 20 hingga 1.000 meter.',
        ];
    }
}
