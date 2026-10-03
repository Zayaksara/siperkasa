<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveUserRequest extends FormRequest
{
    use ProfileValidationRules;

    public function rules(): array
    {
        $user = $this->route('user');
        $id = $user instanceof User ? $user->id : null;

        return [
            'nim_nip' => $this->nimNipRules($id),
            'name' => $this->nameRules(),
            'email' => $this->emailRules($id),
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => [$id === null ? 'required' : 'nullable', 'string', 'min:8', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['nim_nip.unique' => 'NIM/NIP sudah terdaftar.'];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $user = $this->route('user');

            if ($user instanceof User && $user->is($this->user()) && $this->input('role') !== UserRole::Admin->value) {
                $validator->errors()->add('role', 'Anda tidak bisa mengubah peran akun sendiri.');
            }
        }];
    }
}
