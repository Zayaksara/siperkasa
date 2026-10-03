<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Bmn\BmnValue;
use App\Support\Spreadsheet\RowReader;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ImportUsers
{
    public const MAX_ROWS = 500;

    /** @return array{created: int, updated: int, failed: int, errors: list<array{line: int, message: string}>, generated: list<array{nim_nip: string, password: string}>} */
    public function handle(string $path, string $extension, User $actor): array
    {
        $result = ['created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => [], 'generated' => []];

        $rows = iterator_to_array(RowReader::assoc($path, $extension));

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'Maksimal '.self::MAX_ROWS.' baris per file.']);
        }

        $nimNips = array_filter(array_map(fn ($row) => BmnValue::string($row['nim_nip'] ?? null), $rows));
        $existingUsers = User::whereIn('nim_nip', $nimNips)->get()->keyBy('nim_nip');

        foreach ($rows as $line => $row) {
            $data = [
                'nim_nip' => BmnValue::string($row['nim_nip'] ?? null),
                'name' => BmnValue::string($row['name'] ?? null),
                'email' => BmnValue::string($row['email'] ?? null),
                'role' => strtolower((string) BmnValue::string($row['role'] ?? null)),
                'password' => BmnValue::string($row['password'] ?? null),
            ];

            if (! array_filter($data)) {
                continue; // blank line
            }

            $existing = $data['nim_nip'] === null ? null : $existingUsers->get($data['nim_nip']);

            $validator = Validator::make($data, [
                'nim_nip' => ['required', 'string', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($existing?->id)],
                'role' => ['required', Rule::enum(UserRole::class)],
                'password' => ['nullable', 'string', 'min:8', 'max:255'],
            ], [], ['role' => 'peran']);

            if ($validator->fails()) {
                $result['failed']++;
                $result['errors'][] = ['line' => $line, 'message' => $validator->errors()->first()];

                continue;
            }

            if ($existing) {
                if ($existing->is($actor) && $data['role'] !== UserRole::Admin->value) {
                    $result['failed']++;
                    $result['errors'][] = ['line' => $line, 'message' => 'Tidak bisa mengubah peran akun sendiri.'];

                    continue;
                }

                // Password is only set on create; re-importing a roster must not reset existing passwords.
                $existing->update(array_filter([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'role' => $data['role'],
                ], fn ($value) => $value !== null));
                $existing->clearDefaultPicIfNotPic();
                $result['updated']++;

                continue;
            }

            $password = $data['password'] ?? Str::password(10, symbols: false);

            User::create([
                'nim_nip' => $data['nim_nip'],
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
                'password' => $password,
                'is_active' => true,
            ]);

            $result['created']++;

            if ($data['password'] === null) {
                $result['generated'][] = ['nim_nip' => $data['nim_nip'], 'password' => $password];
            }
        }

        return $result;
    }
}
