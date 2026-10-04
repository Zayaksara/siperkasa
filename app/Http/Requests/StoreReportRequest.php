<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Enums\ReportStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReportRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $photos = config('siperkasa.photos');

        return [
            'title' => ['required', 'string', 'min:10', 'max:120'],
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
            'title.min' => 'Judul minimal 10 karakter. Sebut barang, kerusakan, dan lokasi singkat.',
            'photos.required' => 'Minimal 1 foto kerusakan.',
            'photos.min' => 'Minimal 1 foto kerusakan.',
            'photos.max' => 'Maksimal 3 foto.',
            'photos.*.image' => 'Berkas harus berupa gambar.',
            'photos.*.mimes' => 'Foto harus berformat jpg, png, atau webp.',
            'photos.*.max' => 'Ukuran foto maksimal 2 MB.',
            'photos.*.uploaded' => 'Foto gagal diunggah. Coba lagi.',
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator) {
            $pendingCount = $this->user()->reports()
                ->where('status', ReportStatus::Pending)
                ->count();

            if ($pendingCount >= 5) {
                $validator->errors()->add('title', 'Kamu masih punya 5 laporan menunggu validasi. Tunggu sebagian divalidasi atau batalkan salah satunya.');
            }
        }];
    }
}
