<?php

namespace App\Http\Requests\Admin;

use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;

class ImportCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::admin();
    }

    public function rules(): array
    {
        $fields = ['name', 'email', 'branch', 'position', 'hired_at', 'base_salary', 'active'];

        return array_fill_keys($fields, 'required|integer|min:0|max:100');
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $fields = ['name', 'email', 'branch', 'position', 'hired_at', 'base_salary', 'active'];
            $values = [];
            foreach ($fields as $field) {
                if ($this->has($field)) {
                    $values[] = $this->input($field);
                }
            }

            if (count(array_unique($values)) !== count($values)) {
                $validator->errors()->add('mapping', 'Satu kolom file tidak boleh dipetakan untuk dua field berbeda.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'required' => 'Kolom pemetaan wajib dipilih.',
            'integer' => 'Indeks kolom harus berupa bilangan bulat.',
        ];
    }
}
