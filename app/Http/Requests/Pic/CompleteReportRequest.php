<?php

namespace App\Http\Requests\Pic;

use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;

class CompleteReportRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $photos = config('siperkasa.photos');
        $report = $this->route('report');
        $created = $report instanceof Report ? $report->created_at->toDateString() : now()->toDateString();

        return [
            'repair_action' => ['required', 'string', 'min:5', 'max:2000'],
            'completed_on' => ['required', 'date', 'before_or_equal:today', "after_or_equal:{$created}"],
            'repair_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'vendor_name' => ['nullable', 'string', 'max:150'],
            'photos' => ['required', 'array', "min:{$photos['min']}", "max:{$photos['max']}"],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$photos['max_kb']}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'repair_action.required' => 'Tindakan perbaikan wajib diisi.',
            'repair_action.min' => 'Tindakan perbaikan minimal 5 karakter.',
            'completed_on.before_or_equal' => 'Tanggal selesai tidak boleh di masa depan.',
            'completed_on.after_or_equal' => 'Tanggal selesai tidak boleh sebelum laporan dibuat.',
            'repair_cost.min' => 'Biaya tidak boleh negatif.',
            'repair_cost.numeric' => 'Biaya harus berupa angka.',
            'photos.required' => 'Minimal 1 foto bukti pengerjaan.',
            'photos.min' => 'Minimal 1 foto bukti pengerjaan.',
            'photos.max' => 'Maksimal 3 foto.',
            'photos.*.image' => 'Berkas harus berupa gambar.',
            'photos.*.mimes' => 'Foto harus berformat jpg, png, atau webp.',
            'photos.*.max' => 'Ukuran foto maksimal 2 MB.',
            'photos.*.uploaded' => 'Foto gagal diunggah. Coba lagi.',
        ];
    }
}
