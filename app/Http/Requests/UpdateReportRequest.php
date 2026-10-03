<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UpdateReportRequest extends StoreReportRequest
{
    /** Authorizes before validation so a stale form gets 403, not validation errors. */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('report'));
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'photos' => ['nullable', 'array', 'max:'.config('siperkasa.photos.max')],
            'remove_photo_ids' => ['nullable', 'array'],
            'remove_photo_ids.*' => ['integer'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $report = $this->route('report');
            $removed = collect($this->input('remove_photo_ids', []))->map(fn ($id) => (int) $id)->all();

            $kept = $report->damagePhotos()->whereNotIn('id', $removed)->count();
            $total = $kept + count($this->file('photos', []));

            if ($total < config('siperkasa.photos.min')) {
                $validator->errors()->add('photos', 'Minimal 1 foto kerusakan.');
            } elseif ($total > config('siperkasa.photos.max')) {
                $validator->errors()->add('photos', 'Maksimal 3 foto.');
            }
        }];
    }
}
