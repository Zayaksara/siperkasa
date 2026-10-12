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
            'nim_nip' => ['required', 'string', 'max:255', Rule::unique(User::class)->whereNull('deleted_at')->ignore($id)],
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

            $trashedQuery = User::onlyTrashed()->where('nim_nip', $this->input('nim_nip'));
            if ($user instanceof User) {
                $trashedQuery->whereKeyNot($user->id);
            }

            if ($trashedQuery->exists()) {
                $validator->errors()->add('nim_nip', 'NIM/NIP milik akun yang dihapus. Pulihkan akun tersebut.');
            }

            if ($user instanceof User && $user->is($this->user()) && $this->input('role') !== UserRole::Admin->value) {
                $validator->errors()->add('role', 'Anda tidak bisa mengubah peran akun sendiri.');
            }

            if ($user instanceof User && $user->isPortalAccount()) {
                $this->validatePortalAccount($validator, $user);
            }
        }];
    }

    /** Portal accounts stay reporters; their identity and credentials come from the campus portal. */
    private function validatePortalAccount(Validator $validator, User $user): void
    {
        if ($this->input('role') !== UserRole::User->value) {
            $validator->errors()->add('role', 'Akun portal kampus tidak bisa dijadikan PIC atau admin. Buat akun lokal terpisah.');
        }

        $message = 'Data akun portal mengikuti portal kampus.';

        if ((string) $this->input('name') !== $user->name) {
            $validator->errors()->add('name', $message);
        }

        if ((string) $this->input('email') !== (string) $user->email) {
            $validator->errors()->add('email', $message);
        }

        if (filled($this->input('password'))) {
            $validator->errors()->add('password', 'Password akun portal dikelola portal kampus.');
        }
    }
}
