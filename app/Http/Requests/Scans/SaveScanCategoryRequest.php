<?php

namespace App\Http\Requests\Scans;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveScanCategoryRequest extends FormRequest
{
    /**
     * Only team owners and admins may change the categories.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('current_team'));
    }

    /**
     * Accept keywords as a comma-separated string from the form (an empty field arrives as null).
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('keywords') === null || is_string($this->input('keywords'))) {
            $this->merge([
                'keywords' => collect(explode(',', (string) $this->input('keywords')))
                    ->map(fn (string $keyword) => mb_strtolower(trim($keyword)))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'prompt' => ['required', 'string', 'max:255'],
            'keywords' => ['present', 'array', 'max:50'],
            'keywords.*' => ['string', 'max:60'],
            'is_productive' => ['required', 'boolean'],
            'is_enabled' => ['required', 'boolean'],
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
            'name' => 'nama kategori',
            'prompt' => 'deskripsi AI',
            'keywords' => 'kata kunci',
        ];
    }
}
