<?php

namespace App\Http\Requests\Scans;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreScanRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_name' => ['required', 'string', 'max:255'],
            'work_date' => ['nullable', 'date'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'employee_name' => 'nama karyawan',
            'work_date' => 'tanggal kerja',
        ];
    }
}
