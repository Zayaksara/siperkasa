<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBuildingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('buildings', 'code')->ignore($this->route('building'))],
            'name' => ['required', 'string', 'max:150'],
        ];
    }
}
