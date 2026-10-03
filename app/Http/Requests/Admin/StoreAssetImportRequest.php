<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetImportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'Berkas maksimal 20 MB.',
            'file.uploaded' => 'Berkas gagal diunggah. Ukuran maksimal 20 MB.',
            'file.mimes' => 'Berkas harus berformat .xlsx.',
        ];
    }
}
