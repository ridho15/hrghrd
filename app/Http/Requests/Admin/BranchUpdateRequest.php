<?php

namespace App\Http\Requests\Admin;

use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;

class BranchUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::admin();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_m' => 'required|integer|between:20,1000',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama cabang wajib diisi.',
            'latitude.required' => 'Titik lintang wajib diisi.',
            'latitude.between' => 'Titik lintang harus berada di antara -90 dan 90.',
            'longitude.required' => 'Titik bujur wajib diisi.',
            'longitude.between' => 'Titik bujur harus berada di antara -180 dan 180.',
            'radius_m.required' => 'Radius area wajib diisi.',
            'radius_m.between' => 'Radius area harus antara 20 hingga 1.000 meter.',
        ];
    }
}
