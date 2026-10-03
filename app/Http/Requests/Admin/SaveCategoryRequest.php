<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($this->route('category'))],
            'default_pic_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role', UserRole::Pic->value)->where('is_active', true),
            ],
        ];
    }
}
