<?php

namespace App\Http\Requests\Scans;

use App\Models\ScanSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateScanSettingsRequest extends FormRequest
{
    /**
     * Only team owners and admins may change how scans are analyzed.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('current_team'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ScanSetting::EDITABLE;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'low_change_ratio.gte' => 'Batas aktivitas rendah harus lebih besar atau sama dengan batas layar diam.',
        ];
    }
}
