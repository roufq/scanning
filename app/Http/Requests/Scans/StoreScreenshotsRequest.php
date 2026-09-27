<?php

namespace App\Http\Requests\Scans;

use App\Enums\ScanStatus;
use App\Models\Scan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreScreenshotsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'max:'.config('scanning.files_per_upload_request')],
            'files.*' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:'.config('scanning.max_file_size_kb')],
            'modified_at' => ['nullable', 'array'],
            'modified_at.*' => ['nullable', 'date_format:Y-m-d\TH:i:s'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Scan $scan */
                $scan = $this->route('scan');

                if ($scan->status !== ScanStatus::Uploading) {
                    $validator->errors()->add('files', 'Scan ini sudah dianalisis, buat scan baru untuk menambah screenshot.');

                    return;
                }

                $limit = (int) config('scanning.max_screenshots_per_scan');

                if ($scan->screenshots()->count() + count((array) $this->file('files')) > $limit) {
                    $validator->errors()->add('files', "Maksimal {$limit} screenshot per scan.");
                }
            },
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.*.mimes' => 'File :attribute harus berformat JPG, JPEG, atau PNG.',
            'files.*.max' => 'File :attribute terlalu besar.',
        ];
    }
}
