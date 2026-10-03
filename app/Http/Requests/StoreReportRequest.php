<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $photos = config('siperkasa.photos');

        return [
            'title' => ['required', 'string', 'max:120'],
            'category_id' => ['required', Rule::exists('categories', 'id')],
            'room_id' => ['required', Rule::exists('rooms', 'id')],
            'asset_id' => ['nullable', Rule::exists('assets', 'id')],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'user_priority' => ['required', Rule::enum(Priority::class)],
            'photos' => ['required', 'array', "min:{$photos['min']}", "max:{$photos['max']}"],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$photos['max_kb']}"],
        ];
    }

    public function messages(): array
    {
        return [
            'photos.required' => 'Minimal 1 foto kerusakan.',
            'photos.min' => 'Minimal 1 foto kerusakan.',
            'photos.max' => 'Maksimal 3 foto.',
            'photos.*.image' => 'Berkas harus berupa gambar.',
            'photos.*.mimes' => 'Foto harus berformat jpg, png, atau webp.',
            'photos.*.max' => 'Ukuran foto maksimal 2 MB.',
            'photos.*.uploaded' => 'Foto gagal diunggah. Coba lagi.',
        ];
    }
}
