# SiPerkasa Plan 1 — Foundation (Test Infra, Auth & Role, Design System)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A working, tested login by NIM/NIP with three role areas, a UINSSC-branded mobile-first shell, and a green test suite on MySQL.

**Architecture:** Fortify handles auth with `nim_nip` as username. A `role` middleware (existing `CheckRole`) plus an `active` middleware guard three route groups (`user.*`, `admin.*`, `pic.*`). Design tokens live in `resources/css/app.css` as CSS variables mapped to Tailwind; layout is the starter sidebar layout plus a mobile bottom nav.

**Tech Stack:** Laravel 13, PHP 8.5 (project requires ^8.3), Inertia 3, Vue 3 + TypeScript, Tailwind 4, Fortify, Wayfinder, Pest 5, MySQL 8.

**Spec:** `C:\Users\Lenovo\Documents\Obsidian Vault\40 - PROJECTS\SiPerkasa\04-Spec.md` (also `00-PRD.md`, `03-Tasks/F01-Auth-Role.md`, `03-Tasks/F11-Design-System.md`, `D:\siperkasa\.agents\rules\siperkasa.md`). Index of all plans: `2026-10-03-siperkasa-00-index.md`.

## Global Constraints

- Stack stays: Laravel 13, Inertia 3 + Vue 3 + TS, Fortify, Wayfinder, Pest 5, Larastan level 7, Pint.
- One role per account: `user`, `admin`, `pic`. Registration is closed. Login uses `nim_nip` + password.
- Unauthorized access returns 403.
- UI language is Bahasa Indonesia. Validation messages are Indonesian.
- Timezone `Asia/Jakarta`, locale `id`.
- Brand tokens: primary `#0A5E3D`, primary-strong `#0F443E`, accent `#BB8F32` (3.0:1 on white, **never body text**), accent-strong `#8A5E3C`. Font DM Sans.
- WCAG 2.2 AA: text contrast ≥ 4.5:1, visible focus, touch targets ≥ 44×44 px.
- Components must not use raw hex values; use tokens.
- Commit messages must not contain any Claude attribution or `Co-Authored-By` trailer.

## Review Focus

- `nim_nip` typed with surrounding spaces must still log in (Task 3, `AuthenticationTest`).
- `nim_nip` with leading zeros (`0012345`) must stay a string and match exactly (Task 3).
- A passkey-only account (`password` null) must fail a password login cleanly, not crash (Task 3).
- An account deactivated while logged in must be logged out on its next request (Task 4).
- A guest requesting any role area must be sent to the login page, not shown a 403 (Task 4).

## File Structure

| File | Responsibility |
|---|---|
| `app/Enums/UserRole.php` | Role values, label, dashboard route name |
| `app/Http/Middleware/EnsureUserIsActive.php` | Logs out deactivated users |
| `app/Http/Middleware/CheckRole.php` (modify) | Compares enum role to allowed roles |
| `app/Http/Controllers/DashboardController.php` | `/dashboard` → role dashboard redirect |
| `resources/js/composables/useRoleNav.ts` | Sidebar/bottom-nav items per role (later plans extend) |
| `resources/js/components/BottomNav.vue` | Mobile bottom navigation |
| `resources/js/components/StatusBadge.vue` | Status chip (text + color) |
| `resources/js/components/{EmptyState,PageHeader,ConfirmDialog,Pagination,BrandLogo}.vue` | Shared UI |
| `resources/js/components/ui/{textarea,native-select}/*` | Form controls |
| `scripts/check-contrast.mjs` | Verifies token contrast ≥ 4.5:1 |

---

### Task 1: Test infrastructure, locale, and first commit

**Files:**
- Modify: `phpunit.xml`, `tests/Pest.php`, `config/app.php`, `.env`, `.env.example`, `.gitignore`
- Create: `lang/id/validation.php`, `lang/id/auth.php`, `tests/Feature/LocaleTest.php`

**Interfaces:**
- Produces: a MySQL test database `siperkasa_test`, `RefreshDatabase` on all Feature tests, locale `id`, timezone `Asia/Jakarta`.

Context: `php.ini` has `pdo_sqlite` disabled, so the current suite (`DB_CONNECTION=sqlite`) cannot run. We switch tests to a separate MySQL database. `RefreshDatabase` is currently commented out in `tests/Pest.php`.

- [ ] **Step 1: Create the test database** (run in Git Bash)

```bash
php -r '(new PDO("mysql:host=127.0.0.1;port=3306", "root", ""))->exec("CREATE DATABASE IF NOT EXISTS siperkasa_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");'
```

Expected: no output, exit code 0.

- [ ] **Step 2: Point PHPUnit at it.** In `phpunit.xml`, replace the two lines `DB_CONNECTION` (sqlite) and `DB_DATABASE` (`:memory:`) with:

```xml
        <env name="DB_CONNECTION" value="mysql"/>
        <env name="DB_HOST" value="127.0.0.1"/>
        <env name="DB_PORT" value="3306"/>
        <env name="DB_DATABASE" value="siperkasa_test"/>
        <env name="DB_USERNAME" value="root"/>
        <env name="DB_PASSWORD" value=""/>
        <env name="APP_LOCALE" value="id"/>
        <env name="APP_FALLBACK_LOCALE" value="en"/>
```

Keep `<env name="DB_URL" value=""/>`.

- [ ] **Step 3: Enable `RefreshDatabase`.** In `tests/Pest.php` replace the `pest()->extend(...)` block with:

```php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
```

- [ ] **Step 4: Timezone and locale.** In `config/app.php` change `'timezone' => 'UTC',` to:

```php
    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),
```

In `.env` and `.env.example` set `APP_LOCALE=id`, add `APP_TIMEZONE=Asia/Jakarta`, and set `APP_NAME=SiPerkasa`.

- [ ] **Step 5: Write the failing locale test** `tests/Feature/LocaleTest.php`

```php
<?php

test('validation messages are Indonesian', function () {
    expect(trans('validation.required', ['attribute' => 'judul']))
        ->toBe('Judul wajib diisi.');
});

test('failed login message is Indonesian', function () {
    expect(trans('auth.failed'))->toBe('NIM/NIP atau password salah.');
});

test('the application runs in the Jakarta timezone', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta');
});
```

- [ ] **Step 6: Run it to verify it fails**

Run: `php artisan test tests/Feature/LocaleTest.php`
Expected: FAIL (translations missing: the key is returned as-is).

- [ ] **Step 7: Create `lang/id/auth.php`**

```php
<?php

return [
    'failed' => 'NIM/NIP atau password salah.',
    'password' => 'Password salah.',
    'throttle' => 'Terlalu banyak percobaan masuk. Coba lagi dalam :seconds detik.',
];
```

- [ ] **Step 8: Create `lang/id/validation.php`**

```php
<?php

return [
    'accepted' => ':Attribute harus disetujui.',
    'array' => ':Attribute harus berupa daftar.',
    'after' => ':Attribute harus setelah :date.',
    'before_or_equal' => ':Attribute tidak boleh setelah :date.',
    'boolean' => ':Attribute harus benar atau salah.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'current_password' => 'Password salah.',
    'before' => ':Attribute harus sebelum :date.',
    'between' => [
        'array' => ':Attribute harus antara :min dan :max item.',
        'file' => ':Attribute harus antara :min dan :max KB.',
        'numeric' => ':Attribute harus antara :min dan :max.',
        'string' => ':Attribute harus antara :min dan :max karakter.',
    ],
    'after_or_equal' => ':Attribute harus sama dengan atau setelah :date.',
    'date' => ':Attribute bukan tanggal yang valid.',
    'email' => ':Attribute harus berupa alamat email yang valid.',
    'enum' => ':Attribute yang dipilih tidak valid.',
    'exists' => ':Attribute yang dipilih tidak valid.',
    'file' => ':Attribute harus berupa berkas.',
    'image' => ':Attribute harus berupa gambar.',
    'in' => ':Attribute yang dipilih tidak valid.',
    'integer' => ':Attribute harus berupa angka bulat.',
    'max' => [
        'array' => ':Attribute maksimal :max item.',
        'file' => ':Attribute maksimal :max KB.',
        'numeric' => ':Attribute maksimal :max.',
        'string' => ':Attribute maksimal :max karakter.',
    ],
    'mimes' => ':Attribute harus berjenis: :values.',
    'min' => [
        'array' => ':Attribute minimal :min item.',
        'file' => ':Attribute minimal :min KB.',
        'numeric' => ':Attribute minimal :min.',
        'string' => ':Attribute minimal :min karakter.',
    ],
    'numeric' => ':Attribute harus berupa angka.',
    'required' => ':Attribute wajib diisi.',
    'required_with' => ':Attribute wajib diisi.',
    'string' => ':Attribute harus berupa teks.',
    'unique' => ':Attribute sudah terdaftar.',
    'uploaded' => ':Attribute gagal diunggah.',

    'attributes' => [
        'nim_nip' => 'NIM/NIP',
        'name' => 'nama',
        'email' => 'email',
        'password' => 'password',
        'role' => 'peran',
        'title' => 'judul',
        'description' => 'deskripsi',
        'category_id' => 'kategori',
        'room_id' => 'ruang',
        'asset_id' => 'aset',
        'user_priority' => 'urgensi',
        'admin_priority' => 'prioritas',
        'sla_date' => 'SLA',
        'assigned_pic_id' => 'PIC',
        'rejection_reason' => 'alasan penolakan',
        'hold_reason' => 'alasan penundaan',
        'repair_action' => 'tindakan perbaikan',
        'completed_on' => 'tanggal selesai',
        'repair_cost' => 'biaya',
        'vendor_name' => 'vendor',
        'photos' => 'foto',
        'photos.*' => 'foto',
        'file' => 'berkas',
    ],
];
```

- [ ] **Step 9: Run the locale test again**

Run: `php artisan test tests/Feature/LocaleTest.php`
Expected: 3 passed.

- [ ] **Step 10: Ignore the large BMN spreadsheet.** Append to `.gitignore`:

```
/data/*.xlsx
```

- [ ] **Step 11: Record the baseline.**

Run: `php artisan test`
Expected: the suite now reaches the database. `RegistrationTest` ("new users can register") is already failing in the current code (the form sends no `nim_nip`); it is deleted in Task 3. Every other starter test passes. If anything else fails, stop and report the failing names before changing anything.

- [ ] **Step 12: First commit**

```bash
git status --short | head -20
git add -A
git commit -m "chore: initial scaffold, MySQL test database, Indonesian locale"
```

Check `git status` shows no `.env`, `vendor/` or `node_modules/` staged (all are in `.gitignore`).

---

### Task 2: `UserRole` enum, `is_active`, factory states

**Files:**
- Create: `app/Enums/UserRole.php`, `tests/Feature/Auth/UserModelTest.php`
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php`, `app/Models/User.php`, `database/factories/UserFactory.php`

**Interfaces:**
- Produces:
  - `App\Enums\UserRole` — cases `User='user'`, `Admin='admin'`, `Pic='pic'`; `label(): string`; `dashboardRoute(): string`.
  - `User::$role: UserRole`, `User::$is_active: bool`.
  - `UserFactory` states: `reporter()`, `admin()`, `pic()`, `inactive()`.
  - Default factory user: role `user`, active, numeric `nim_nip` (10 digits).

- [ ] **Step 1: Write the failing test** `tests/Feature/Auth/UserModelTest.php`

```php
<?php

use App\Enums\UserRole;
use App\Models\User;

test('the factory builds users with the right role', function () {
    expect(User::factory()->create()->role)->toBe(UserRole::User);
    expect(User::factory()->reporter()->create()->role)->toBe(UserRole::User);
    expect(User::factory()->admin()->create()->role)->toBe(UserRole::Admin);
    expect(User::factory()->pic()->create()->role)->toBe(UserRole::Pic);
});

test('users are active by default and can be built inactive', function () {
    expect(User::factory()->create()->is_active)->toBeTrue();
    expect(User::factory()->inactive()->create()->is_active)->toBeFalse();
});

test('every role knows its dashboard route and label', function () {
    expect(UserRole::User->dashboardRoute())->toBe('user.dashboard');
    expect(UserRole::Admin->dashboardRoute())->toBe('admin.dashboard');
    expect(UserRole::Pic->dashboardRoute())->toBe('pic.dashboard');
    expect(UserRole::Pic->label())->toBe('PIC');
});

test('nim_nip keeps leading zeros', function () {
    $user = User::factory()->create(['nim_nip' => '0012345']);

    expect($user->fresh()->nim_nip)->toBe('0012345');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Feature/Auth/UserModelTest.php`
Expected: FAIL (`Class "App\Enums\UserRole" not found`).

- [ ] **Step 3: Create `app/Enums/UserRole.php`**

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';
    case Admin = 'admin';
    case Pic = 'pic';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Pelapor',
            self::Admin => 'Admin',
            self::Pic => 'PIC',
        };
    }

    /** Named route of the dashboard that belongs to this role. */
    public function dashboardRoute(): string
    {
        return match ($this) {
            self::User => 'user.dashboard',
            self::Admin => 'admin.dashboard',
            self::Pic => 'pic.dashboard',
        };
    }
}
```

- [ ] **Step 4: Add `is_active` to the users migration.** In `0001_01_01_000000_create_users_table.php`, after the `role` enum line add:

```php
            $table->boolean('is_active')->default(true);
```

(This repo has no released data, so the migration is edited in place; Step 8 refreshes the dev database.)

- [ ] **Step 5: Update `app/Models/User.php`.**
  - Remove `use Illuminate\Contracts\Auth\MustVerifyEmail;` and change the class line to `class User extends Authenticatable implements PasskeyUser`.
  - Add `use App\Enums\UserRole;`.
  - In the docblock change `@property string $role` to `@property UserRole $role` and add `@property bool $is_active`; change `@property string|null $email` stays.
  - Change `#[Fillable([...])]` to `#[Fillable(['nim_nip', 'name', 'email', 'password', 'role', 'is_active'])]`.
  - In `casts()` add:

```php
            'role' => UserRole::class,
            'is_active' => 'boolean',
```

  - Add the helper method before `picAssets()`:

```php
    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }
```

- [ ] **Step 6: Replace `database/factories/UserFactory.php` `definition()` and add states.** Add `use App\Enums\UserRole;` and replace the returned array and add methods:

```php
    public function definition(): array
    {
        return [
            'nim_nip' => fake()->unique()->numerify('##########'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::User,
            'is_active' => true,
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    public function reporter(): static
    {
        return $this->state(fn () => ['role' => UserRole::User]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin]);
    }

    public function pic(): static
    {
        return $this->state(fn () => ['role' => UserRole::Pic]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
```

Keep the existing `unverified()` and `withTwoFactor()` methods.

- [ ] **Step 7: Run the test**

Run: `php artisan test tests/Feature/Auth/UserModelTest.php`
Expected: 4 passed.

- [ ] **Step 8: Refresh the dev database**

Run: `php artisan migrate:fresh`
Expected: all migrations run. (The dev DB only held the seeded super admin; Task 4 re-seeds it.)

- [ ] **Step 9: Commit**

```bash
git add app/Enums app/Models/User.php database tests/Feature/Auth/UserModelTest.php
git commit -m "feat: add UserRole enum, is_active flag and role factory states"
```

---

### Task 3: Login by NIM/NIP, closed registration, no self-delete

**Files:**
- Modify: `config/fortify.php`, `app/Providers/AppServiceProvider.php`, `app/Providers/FortifyServiceProvider.php`, `app/Http/Controllers/Settings/ProfileController.php`, `app/Concerns/ProfileValidationRules.php`, `app/Http/Requests/Settings/ProfileUpdateRequest.php`, `routes/settings.php`, `tests/Feature/Auth/AuthenticationTest.php`, `tests/Feature/Auth/TwoFactorChallengeTest.php`, `tests/Feature/Settings/ProfileUpdateTest.php`
- Delete: `app/Actions/Fortify/CreateNewUser.php`, `app/Actions/Fortify/ResetUserPassword.php`, `app/Http/Requests/Settings/ProfileDeleteRequest.php`, `resources/js/pages/auth/{Register,ForgotPassword,ResetPassword,VerifyEmail}.vue`, `resources/js/components/DeleteUser.vue`, `tests/Feature/Auth/{RegistrationTest,PasswordResetTest,EmailVerificationTest,VerificationNotificationTest}.php`
- Frontend edits: `resources/js/pages/auth/Login.vue`, `resources/js/pages/settings/Profile.vue`, `resources/js/types/auth.ts`

**Interfaces:**
- Produces: login form field `nim_nip`; `POST route('login.store')` with `nim_nip` + `password`; inactive accounts rejected with an error on `nim_nip`.

- [ ] **Step 1: Rewrite `tests/Feature/Auth/AuthenticationTest.php`**

```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $this->get(route('login'))->assertOk();
});

test('users authenticate with nim_nip and password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'nim_nip' => $user->nim_nip,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('surrounding spaces in the nim_nip are ignored', function () {
    $user = User::factory()->create(['nim_nip' => '198501012010011001']);

    $this->post(route('login.store'), [
        'nim_nip' => '  198501012010011001 ',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('a nim_nip with leading zeros only matches exactly', function () {
    $user = User::factory()->create(['nim_nip' => '0012345']);

    $this->post(route('login.store'), ['nim_nip' => '12345', 'password' => 'password']);
    $this->assertGuest();

    $this->post(route('login.store'), ['nim_nip' => '0012345', 'password' => 'password']);
    $this->assertAuthenticatedAs($user);
});

test('the email address can not be used to log in', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'nim_nip' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('users can not authenticate with an invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'nim_nip' => $user->nim_nip,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('nim_nip');

    $this->assertGuest();
});

test('an account without a password fails a password login cleanly', function () {
    $user = User::factory()->create(['password' => null]);

    $this->post(route('login.store'), [
        'nim_nip' => $user->nim_nip,
        'password' => 'anything',
    ])->assertSessionHasErrors('nim_nip');

    $this->assertGuest();
});

test('inactive accounts can not log in', function () {
    $user = User::factory()->inactive()->create();

    $this->post(route('login.store'), [
        'nim_nip' => $user->nim_nip,
        'password' => 'password',
    ])->assertSessionHasErrors(['nim_nip' => 'Akun Anda nonaktif. Hubungi admin.']);

    $this->assertGuest();
});

test('registration is closed', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});

test('users with two factor enabled are redirected to the challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login'), [
        'nim_nip' => $user->nim_nip,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->nim_nip, '127.0.0.1'])), amount: 5);

    $this->post(route('login.store'), [
        'nim_nip' => $user->nim_nip,
        'password' => 'wrong-password',
    ])->assertTooManyRequests();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test tests/Feature/Auth/AuthenticationTest.php`
Expected: FAIL (login still uses `email`; `/register` exists).

- [ ] **Step 3: Fortify config.** In `config/fortify.php` set `'username' => 'nim_nip',` and replace the `'features'` array with:

```php
    'features' => [
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
            // 'window' => 0
        ]),
        Features::passkeys([
            'confirmPassword' => true,
        ]),
    ],
```

- [ ] **Step 4: Authenticate by `nim_nip` and block inactive accounts.** In `app/Providers/AppServiceProvider.php` add `use Illuminate\Validation\ValidationException;` and replace the `Fortify::authenticateUsing(...)` call with:

```php
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('nim_nip', trim((string) $request->input('nim_nip')))->first();

            // blank() guards passkey-only accounts: Hash::check() throws on an empty hash.
            if (! $user || blank($user->password)
                || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            if (! $user->is_active) {
                throw ValidationException::withMessages([
                    'nim_nip' => 'Akun Anda nonaktif. Hubungi admin.',
                ]);
            }

            return $user;
        });
```

- [ ] **Step 5: Replace `app/Providers/FortifyServiceProvider.php` entirely**

```php
<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureViews();
        $this->configureRateLimiting();
    }

    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
```

- [ ] **Step 6: Delete the closed flows**

```bash
git rm app/Actions/Fortify/CreateNewUser.php app/Actions/Fortify/ResetUserPassword.php \
  app/Http/Requests/Settings/ProfileDeleteRequest.php \
  resources/js/pages/auth/Register.vue resources/js/pages/auth/ForgotPassword.vue \
  resources/js/pages/auth/ResetPassword.vue resources/js/pages/auth/VerifyEmail.vue \
  resources/js/components/DeleteUser.vue \
  tests/Feature/Auth/RegistrationTest.php tests/Feature/Auth/PasswordResetTest.php \
  tests/Feature/Auth/EmailVerificationTest.php tests/Feature/Auth/VerificationNotificationTest.php
```

- [ ] **Step 7: Profile edits.** Replace `app/Http/Controllers/Settings/ProfileController.php` entirely:

```php
<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/Profile');
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profil diperbarui.']);

        return to_route('profile.edit');
    }
}
```

Replace `rules()` in `ProfileUpdateRequest.php`:

```php
    public function rules(): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($this->user()->id),
        ];
    }
```

In `app/Concerns/ProfileValidationRules.php`, in `emailRules()` replace `'required',` with `'nullable',`.

Replace `routes/settings.php` entirely:

```php
<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
```

(Task 4 adds the `active` middleware to this group once the alias exists.)

- [ ] **Step 8: Update the other tests.**
  - `tests/Feature/Auth/TwoFactorChallengeTest.php`: replace `'email' => $user->email,` with `'nim_nip' => $user->nim_nip,`.
  - Replace `tests/Feature/Settings/ProfileUpdateTest.php` entirely:

```php
<?php

use App\Models\User;

test('profile page is displayed', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk();
});

test('name and email can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Test User', 'email' => 'test@example.com'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
});

test('email is optional', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Test User', 'email' => null])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBeNull();
});

test('nim_nip and role can not be changed from the profile', function () {
    $user = User::factory()->create(['nim_nip' => '111']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Test User',
        'email' => $user->email,
        'nim_nip' => '999',
        'role' => 'admin',
    ]);

    expect($user->refresh()->nim_nip)->toBe('111');
    expect($user->role->value)->toBe('user');
});

test('there is no self-service account deletion', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertStatus(405);

    expect($user->fresh())->not->toBeNull();
});
```

- [ ] **Step 9: Rewrite `Login.vue`** (`resources/js/pages/auth/Login.vue`)

```vue
<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';

defineOptions({
    layout: {
        title: 'Masuk ke SiPerkasa',
        description: 'Gunakan NIM/NIP dan password yang diberikan admin.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Masuk" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-status-closed"
        role="status"
    >
        {{ status }}
    </div>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="nim_nip">NIM/NIP</Label>
                <Input
                    id="nim_nip"
                    type="text"
                    name="nim_nip"
                    inputmode="numeric"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="username"
                    placeholder="Masukkan NIM atau NIP"
                    class="h-11"
                />
                <InputError :message="errors.nim_nip" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <Label for="remember" class="flex items-center gap-3">
                <Checkbox id="remember" name="remember" :tabindex="3" />
                <span>Ingat saya</span>
            </Label>

            <Button
                type="submit"
                class="h-11 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Masuk
            </Button>
        </div>
    </Form>
</template>
```

- [ ] **Step 10: Rewrite `Profile.vue`** (`resources/js/pages/settings/Profile.vue`)

```vue
<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Profil', href: edit() }],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Profil" />

    <h1 class="sr-only">Profil</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profil"
            description="NIM/NIP dan peran diatur oleh admin. Anda bisa mengubah nama dan email."
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="nim_nip">NIM/NIP</Label>
                <Input id="nim_nip" :model-value="user.nim_nip" disabled />
            </div>

            <div class="grid gap-2">
                <Label for="name">Nama</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    class="h-11"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email (opsional)</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    :default-value="user.email ?? ''"
                    autocomplete="email"
                    class="h-11"
                />
                <InputError :message="errors.email" />
            </div>

            <Button
                :disabled="processing"
                class="h-11"
                data-test="update-profile-button"
            >
                Simpan
            </Button>
        </Form>
    </div>
</template>
```

- [ ] **Step 11: Update `resources/js/types/auth.ts`.** Replace the `User` type:

```ts
export type Role = 'user' | 'admin' | 'pic';

export type User = {
    id: number;
    nim_nip: string;
    name: string;
    email: string | null;
    role: Role;
    is_active: boolean;
    avatar?: string;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};
```

- [ ] **Step 12: Run the whole suite**

Run: `php artisan test`
Expected: all pass (the old `verified` middleware on `/dashboard` lets non-`MustVerifyEmail` users through; Task 4 replaces that route anyway).

- [ ] **Step 13: Commit**

```bash
git add -A
git commit -m "feat: log in by NIM/NIP, close registration and self-service deletion"
```

---

### Task 4: Role areas, dashboards redirect, `active` guard, seeder

**Files:**
- Create: `app/Http/Middleware/EnsureUserIsActive.php`, `app/Http/Controllers/DashboardController.php`, `resources/js/pages/{user,admin,pic}/Dashboard.vue`, `tests/Feature/Auth/RoleAccessTest.php`
- Modify: `app/Http/Middleware/CheckRole.php`, `bootstrap/app.php`, `routes/web.php`, `database/seeders/DatabaseSeeder.php`, `tests/Feature/DashboardTest.php`
- Delete: `resources/js/pages/Dashboard.vue`

**Interfaces:**
- Consumes: `UserRole::dashboardRoute()`, factory states (Task 2).
- Produces: route names `dashboard`, `user.dashboard`, `admin.dashboard`, `pic.dashboard`; middleware aliases `role:` and `active`. Later plans add routes inside these three groups.

- [ ] **Step 1: Write the failing tests** `tests/Feature/Auth/RoleAccessTest.php`

```php
<?php

use App\Models\User;

it('lets each role into its own area only', function (string $state, string $routeName, int $expected) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get(route($routeName))->assertStatus($expected);
})->with([
    ['reporter', 'user.dashboard', 200],
    ['reporter', 'admin.dashboard', 403],
    ['reporter', 'pic.dashboard', 403],
    ['admin', 'admin.dashboard', 200],
    ['admin', 'user.dashboard', 403],
    ['admin', 'pic.dashboard', 403],
    ['pic', 'pic.dashboard', 200],
    ['pic', 'user.dashboard', 403],
    ['pic', 'admin.dashboard', 403],
]);

it('sends guests to the login page for every role area', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with(['user.dashboard', 'admin.dashboard', 'pic.dashboard', 'dashboard']);

it('redirects /dashboard to the dashboard of the role', function (string $state, string $routeName) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route($routeName));
})->with([
    ['reporter', 'user.dashboard'],
    ['admin', 'admin.dashboard'],
    ['pic', 'pic.dashboard'],
]);

it('exposes the role to the frontend', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/Dashboard')
            ->where('auth.user.role', 'admin'));
});

it('logs out an account that is deactivated mid-session', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user->update(['is_active' => false]);

    $this->get(route('user.dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});
```

Replace `tests/Feature/DashboardTest.php`:

```php
<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users are redirected to their dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('user.dashboard'));
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test tests/Feature/Auth/RoleAccessTest.php tests/Feature/DashboardTest.php`
Expected: FAIL (`Route [user.dashboard] not defined`).

- [ ] **Step 3: Update `CheckRole`** (`app/Http/Middleware/CheckRole.php`) — replace `handle()`:

```php
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role->value, $roles, true)) {
            abort(403, 'Anda tidak berhak mengakses halaman ini.');
        }

        return $next($request);
    }
```

- [ ] **Step 4: Create `app/Http/Middleware/EnsureUserIsActive.php`**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['nim_nip' => 'Akun Anda nonaktif. Hubungi admin.']);
        }

        return $next($request);
    }
}
```

- [ ] **Step 5: Register the alias** in `bootstrap/app.php` inside `$middleware->alias([...])`:

```php
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
        ]);
```

- [ ] **Step 6: Create `app/Http/Controllers/DashboardController.php`**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->role->dashboardRoute());
    }
}
```

- [ ] **Step 7: Replace `routes/web.php`**

```php
<?php

use App\Http\Controllers\DashboardController;
use App\Models\Asset;
use App\Models\Report;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return inertia('Welcome', [
        'stats' => [
            'total_reports' => Report::count(),
            'total_completed' => Report::where('status', 'Closed')->count(),
            'total_in_progress' => Report::whereIn('status', ['Assigned', 'In_Progress'])->count(),
            'total_assets' => Asset::count(),
        ],
    ]);
})->name('home');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:user')->prefix('user')->name('user.')->group(function () {
        Route::inertia('dashboard', 'user/Dashboard')->name('dashboard');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::inertia('dashboard', 'admin/Dashboard')->name('dashboard');
    });

    Route::middleware('role:pic')->prefix('pic')->name('pic.')->group(function () {
        Route::inertia('dashboard', 'pic/Dashboard')->name('dashboard');
    });
});

require __DIR__.'/settings.php';
```

- [ ] **Step 8: Placeholder dashboard pages.** Delete `resources/js/pages/Dashboard.vue` (`git rm`). Create the three pages; Plan 5 replaces their content. `resources/js/pages/user/Dashboard.vue`:

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { dashboard } from '@/routes/user';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Beranda', href: dashboard() }] },
});
</script>

<template>
    <Head title="Beranda" />
    <div class="p-4">
        <h1 class="text-xl font-semibold">Beranda</h1>
        <p class="text-sm text-muted-foreground">Ringkasan laporan Anda.</p>
    </div>
</template>
```

`resources/js/pages/pic/Dashboard.vue`:

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { dashboard } from '@/routes/pic';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Beranda', href: dashboard() }] },
});
</script>

<template>
    <Head title="Beranda" />
    <div class="p-4">
        <h1 class="text-xl font-semibold">Beranda</h1>
        <p class="text-sm text-muted-foreground">Ringkasan tugas Anda.</p>
    </div>
</template>
```

`resources/js/pages/admin/Dashboard.vue`:

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { dashboard } from '@/routes/admin';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Beranda', href: dashboard() }] },
});
</script>

<template>
    <Head title="Beranda" />
    <div class="p-4">
        <h1 class="text-xl font-semibold">Beranda</h1>
        <p class="text-sm text-muted-foreground">Ringkasan seluruh laporan.</p>
    </div>
</template>
```

(These pages are placeholders; Plan 5 Task 2 replaces all three with the real dashboards.)

Also add the `active` guard to the settings routes: in `routes/settings.php` change `Route::middleware(['auth'])->group(` to `Route::middleware(['auth', 'active'])->group(`.

- [ ] **Step 9: Update `DatabaseSeeder`**

```php
<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['nim_nip' => env('SUPER_ADMIN_NIP', 'admin')],
            [
                'name' => 'Super Admin',
                'email' => 'admin@siperkasa.local',
                'password' => env('SUPER_ADMIN_PASSWORD', 'password'),
                'role' => UserRole::Admin,
                'is_active' => true,
            ],
        );

        if (! app()->environment('local')) {
            return;
        }

        $demo = [
            ['pic001', 'PIC Elektronik', UserRole::Pic],
            ['pic002', 'PIC Furnitur', UserRole::Pic],
            ['2010001', 'Mahasiswa Contoh', UserRole::User],
            ['198501012010011001', 'Dosen Contoh', UserRole::User],
        ];

        foreach ($demo as [$nimNip, $name, $role]) {
            User::updateOrCreate(
                ['nim_nip' => $nimNip],
                ['name' => $name, 'password' => 'password', 'role' => $role, 'is_active' => true],
            );
        }
    }
}
```

- [ ] **Step 10: Run the full suite**

Run: `php artisan test`
Expected: all pass. If the Inertia assertion fails because the page file does not exist yet (`@vite` only runs at render time in feature tests, so it does not), re-run after Task 7.

- [ ] **Step 11: Lint and static analysis**

Run: `composer lint:check && composer types:check`
Expected: no errors. Fix with `composer lint` if Pint reports style issues.

- [ ] **Step 12: Commit**

```bash
git add -A
git commit -m "feat: role areas, dashboard redirect and inactive-account guard"
```

---

### Task 5: Brand tokens, DM Sans, logo, contrast check, error page

**Files:**
- Create: `scripts/check-contrast.mjs`, `public/images/logo-uinssc.png`, `resources/js/components/BrandLogo.vue`, `resources/js/pages/Error.vue`
- Modify: `resources/css/app.css`, `vite.config.ts`, `resources/js/components/AppLogo.vue`, `resources/js/layouts/auth/AuthSimpleLayout.vue`, `resources/js/app.ts`, `bootstrap/app.php`

**Interfaces:**
- Produces Tailwind utilities: `bg-primary`, `text-brand-accent-strong`, `bg-status-{pending|assigned|in-progress|on-hold|closed|rejected|cancelled}`, `text-status-*`, `bg-status-*-bg`. Component `BrandLogo` (prop `size`: number, px width).

- [ ] **Step 1: Write the contrast check first** `scripts/check-contrast.mjs`

```js
// Verifies the design-token pairs meet WCAG AA (4.5:1). Run: node scripts/check-contrast.mjs
const pairs = [
    // [label, foreground, background]
    ['text on page', '#07110b', '#ffffff'],
    ['muted text on page', '#565c59', '#ffffff'],
    ['muted text on muted', '#565c59', '#f5f7f6'],
    ['primary button', '#ffffff', '#0a5e3d'],
    ['accent-strong text', '#8a5e3c', '#ffffff'],
    ['destructive button', '#ffffff', '#b42318'],
    ['sidebar text', '#e7efec', '#0f443e'],
    ['sidebar primary', '#07110b', '#bb8f32'],
    ['status pending', '#565c59', '#eceeed'],
    ['status assigned', '#1d4e89', '#e3edf8'],
    ['status in-progress', '#8a5e3c', '#f6efe0'],
    ['status on-hold', '#9a4a00', '#fdebd7'],
    ['status closed', '#0a5e3d', '#dff1e8'],
    ['status rejected', '#b42318', '#fde7e5'],
    ['status cancelled', '#3f4542', '#e2e5e3'],
    // dark mode
    ['dark text', '#e7eae9', '#0b1410'],
    ['dark muted text', '#a3adaa', '#0b1410'],
    ['dark primary button', '#04150e', '#3fb98a'],
    ['dark destructive', '#1a0705', '#f0786d'],
    ['dark sidebar text', '#d7e3df', '#0a1f1b'],
    ['dark status pending', '#c4cbc8', '#252c29'],
    ['dark status assigned', '#8fb8ea', '#14263d'],
    ['dark status in-progress', '#e0b878', '#3a2d17'],
    ['dark status on-hold', '#f0a867', '#3a2410'],
    ['dark status closed', '#6fd3a7', '#0f3326'],
    ['dark status rejected', '#f59a92', '#3d1511'],
    ['dark status cancelled', '#aab1ae', '#26302c'],
];

const lum = (hex) => {
    const [r, g, b] = [1, 3, 5].map((i) => {
        const c = parseInt(hex.slice(i, i + 2), 16) / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

let failed = 0;
for (const [label, fg, bg] of pairs) {
    const [a, b] = [lum(fg), lum(bg)].sort((x, y) => y - x);
    const ratio = (a + 0.05) / (b + 0.05);
    const ok = ratio >= 4.5;
    if (!ok) failed++;
    console.log(`${ok ? 'PASS' : 'FAIL'}  ${ratio.toFixed(2)}  ${label}  ${fg} on ${bg}`);
}
process.exit(failed ? 1 : 0);
```

- [ ] **Step 2: Run it**

Run: `node scripts/check-contrast.mjs`
Expected: every line `PASS`, exit code 0 (all 27 pairs were checked while writing this plan; the lowest is 4.90 for "status in-progress"). If you change a colour later, re-run this script.

- [ ] **Step 3: Copy the logo**

```bash
mkdir -p public/images
cp "storage/app/public/Logo UINSSC (Primary - Gradient).png" public/images/logo-uinssc.png
```

- [ ] **Step 4: Replace the theme blocks in `resources/css/app.css`.**
  - In the `@theme inline { ... }` block, change both font stacks from `Instrument Sans` to `DM Sans` (also the two `--font-sans` in `@layer utilities`), and append before the closing `}` of `@theme inline`:

```css
    --color-brand-accent: var(--brand-accent);
    --color-brand-accent-strong: var(--brand-accent-strong);

    --color-status-pending: var(--status-pending);
    --color-status-pending-bg: var(--status-pending-bg);
    --color-status-assigned: var(--status-assigned);
    --color-status-assigned-bg: var(--status-assigned-bg);
    --color-status-in-progress: var(--status-in-progress);
    --color-status-in-progress-bg: var(--status-in-progress-bg);
    --color-status-on-hold: var(--status-on-hold);
    --color-status-on-hold-bg: var(--status-on-hold-bg);
    --color-status-closed: var(--status-closed);
    --color-status-closed-bg: var(--status-closed-bg);
    --color-status-rejected: var(--status-rejected);
    --color-status-rejected-bg: var(--status-rejected-bg);
    --color-status-cancelled: var(--status-cancelled);
    --color-status-cancelled-bg: var(--status-cancelled-bg);
```

  - Replace the whole `:root { ... }` block and the `.dark { ... }` block (lines 92–163) with:

```css
:root {
    --background: #ffffff;
    --foreground: #07110b;
    --card: #ffffff;
    --card-foreground: #07110b;
    --popover: #ffffff;
    --popover-foreground: #07110b;
    --primary: #0a5e3d;
    --primary-foreground: #ffffff;
    --secondary: #eef2f0;
    --secondary-foreground: #0f443e;
    --muted: #f5f7f6;
    --muted-foreground: #565c59;
    --accent: #f6efe0;
    --accent-foreground: #07110b;
    --destructive: #b42318;
    --destructive-foreground: #ffffff;
    --border: #e6e8e7;
    --input: #d5dad7;
    --ring: #0a5e3d;
    --chart-1: #0a5e3d;
    --chart-2: #bb8f32;
    --chart-3: #0f443e;
    --chart-4: #8a5e3c;
    --chart-5: #3b82a0;
    --radius: 0.625rem;

    --brand-accent: #bb8f32;
    --brand-accent-strong: #8a5e3c;

    --sidebar-background: #0f443e;
    --sidebar-foreground: #e7efec;
    --sidebar-primary: #bb8f32;
    --sidebar-primary-foreground: #07110b;
    --sidebar-accent: #16574f;
    --sidebar-accent-foreground: #ffffff;
    --sidebar-border: #1c5a52;
    --sidebar-ring: #bb8f32;
    --sidebar: #0f443e;

    --status-pending: #565c59;
    --status-pending-bg: #eceeed;
    --status-assigned: #1d4e89;
    --status-assigned-bg: #e3edf8;
    --status-in-progress: #8a5e3c;
    --status-in-progress-bg: #f6efe0;
    --status-on-hold: #9a4a00;
    --status-on-hold-bg: #fdebd7;
    --status-closed: #0a5e3d;
    --status-closed-bg: #dff1e8;
    --status-rejected: #b42318;
    --status-rejected-bg: #fde7e5;
    --status-cancelled: #3f4542;
    --status-cancelled-bg: #e2e5e3;
}

.dark {
    --background: #0b1410;
    --foreground: #e7eae9;
    --card: #101c17;
    --card-foreground: #e7eae9;
    --popover: #101c17;
    --popover-foreground: #e7eae9;
    --primary: #3fb98a;
    --primary-foreground: #04150e;
    --secondary: #17261f;
    --secondary-foreground: #e7eae9;
    --muted: #17261f;
    --muted-foreground: #a3adaa;
    --accent: #1c3027;
    --accent-foreground: #e7eae9;
    --destructive: #f0786d;
    --destructive-foreground: #1a0705;
    --border: #25362f;
    --input: #2d4037;
    --ring: #3fb98a;
    --chart-1: #3fb98a;
    --chart-2: #d4a94a;
    --chart-3: #5bb0a6;
    --chart-4: #c08a62;
    --chart-5: #6aa8c4;

    --brand-accent: #d4a94a;
    --brand-accent-strong: #e0b878;

    --sidebar-background: #0a1f1b;
    --sidebar-foreground: #d7e3df;
    --sidebar-primary: #d4a94a;
    --sidebar-primary-foreground: #07110b;
    --sidebar-accent: #12332d;
    --sidebar-accent-foreground: #ffffff;
    --sidebar-border: #1a3b34;
    --sidebar-ring: #d4a94a;
    --sidebar: #0a1f1b;

    --status-pending: #c4cbc8;
    --status-pending-bg: #252c29;
    --status-assigned: #8fb8ea;
    --status-assigned-bg: #14263d;
    --status-in-progress: #e0b878;
    --status-in-progress-bg: #3a2d17;
    --status-on-hold: #f0a867;
    --status-on-hold-bg: #3a2410;
    --status-closed: #6fd3a7;
    --status-closed-bg: #0f3326;
    --status-rejected: #f59a92;
    --status-rejected-bg: #3d1511;
    --status-cancelled: #aab1ae;
    --status-cancelled-bg: #26302c;
}
```

- [ ] **Step 5: Swap the font in `vite.config.ts`.** Replace `bunny('Instrument Sans', { weights: [400, 500, 600] })` with:

```ts
                bunny('DM Sans', {
                    weights: [400, 500, 600, 700],
                }),
```

- [ ] **Step 6: `BrandLogo.vue`** (`resources/js/components/BrandLogo.vue`)

```vue
<script setup lang="ts">
withDefaults(defineProps<{ size?: number }>(), { size: 40 });
</script>

<template>
    <img
        src="/images/logo-uinssc.png"
        alt="Logo UIN Siber Syekh Nurjati Cirebon"
        :width="size"
        :height="Math.round(size * 1.286)"
        class="h-auto object-contain"
        :style="{ width: `${size}px` }"
    />
</template>
```

- [ ] **Step 7: Use the logo in `AppLogo.vue`** (white tile so the green wordmark stays visible on the dark sidebar):

```vue
<script setup lang="ts">
import BrandLogo from '@/components/BrandLogo.vue';
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-white p-0.5"
    >
        <BrandLogo :size="22" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">SiPerkasa</span>
    </div>
</template>
```

In `resources/js/layouts/auth/AuthSimpleLayout.vue` replace the `AppLogoIcon` import with `import BrandLogo from '@/components/BrandLogo.vue';` and replace the inner `<div class="mb-1 flex h-9 w-9 ...">…</div>` block with `<BrandLogo :size="72" />`.

- [ ] **Step 8: Error page.** `resources/js/pages/Error.vue`

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import BrandLogo from '@/components/BrandLogo.vue';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

const props = defineProps<{ status: number }>();

const copy = computed(
    () =>
        ({
            403: ['Akses ditolak', 'Anda tidak berhak membuka halaman ini.'],
            404: ['Halaman tidak ditemukan', 'Alamat yang Anda tuju tidak ada.'],
            500: ['Terjadi kesalahan', 'Ada masalah di server. Coba lagi nanti.'],
            503: ['Sedang pemeliharaan', 'Aplikasi akan kembali sebentar lagi.'],
        })[props.status] ?? ['Terjadi kesalahan', 'Coba lagi nanti.'],
);
</script>

<template>
    <Head :title="copy[0]" />
    <main
        class="flex min-h-svh flex-col items-center justify-center gap-4 bg-background p-6 text-center"
    >
        <BrandLogo :size="64" />
        <p class="text-5xl font-bold text-primary">{{ status }}</p>
        <h1 class="text-xl font-semibold">{{ copy[0] }}</h1>
        <p class="max-w-sm text-sm text-muted-foreground">{{ copy[1] }}</p>
        <Button as-child class="h-11">
            <Link :href="home()">Kembali ke beranda</Link>
        </Button>
    </main>
</template>
```

In `resources/js/app.ts` add a layout case before `default`: `case name === 'Error': return null;`.

In `bootstrap/app.php` replace the `withExceptions` closure with:

```php
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, Request $request) {
            if (! app()->environment(['local', 'testing'])
                && in_array($response->getStatusCode(), [403, 404, 500, 503], true)) {
                return \Inertia\Inertia::render('Error', ['status' => $response->getStatusCode()])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            }

            return $response;
        });
    })->create();
```

- [ ] **Step 9: Verify**

Run: `node scripts/check-contrast.mjs && php artisan wayfinder:generate && npm run types:check && npm run check`
Expected: all pass.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: UINSSC brand tokens, DM Sans, logo, error page and contrast check"
```

---

### Task 6: Role navigation, mobile bottom nav, layout

**Files:**
- Create: `resources/js/composables/useRoleNav.ts`, `resources/js/components/BottomNav.vue`
- Modify: `resources/js/components/AppSidebar.vue`, `resources/js/layouts/app/AppSidebarLayout.vue`

**Interfaces:**
- Produces: `useRoleNav(): { items: ComputedRef<NavItem[]> }`. **Later plans append entries to `userNav`, `adminNav`, `picNav` in this file.** `BottomNav` shows the first 4 items plus "Profil".
- Breakpoint note: the starter sidebar switches to a sheet below `md` (768 px); the bottom nav is shown below `md` too.

- [ ] **Step 1: `useRoleNav.ts`**

```ts
import { usePage } from '@inertiajs/vue3';
import { Home } from '@lucide/vue';
import { computed } from 'vue';
import { dashboard as adminDashboard } from '@/routes/admin';
import { dashboard as picDashboard } from '@/routes/pic';
import { dashboard as userDashboard } from '@/routes/user';
import type { NavItem } from '@/types';

const userNav: NavItem[] = [
    { title: 'Beranda', href: userDashboard(), icon: Home },
];

const adminNav: NavItem[] = [
    { title: 'Beranda', href: adminDashboard(), icon: Home },
];

const picNav: NavItem[] = [
    { title: 'Beranda', href: picDashboard(), icon: Home },
];

export function useRoleNav() {
    const page = usePage();

    const items = computed<NavItem[]>(() => {
        switch (page.props.auth.user?.role) {
            case 'admin':
                return adminNav;
            case 'pic':
                return picNav;
            default:
                return userNav;
        }
    });

    return { items };
}
```

- [ ] **Step 2: `BottomNav.vue`**

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { UserRound } from '@lucide/vue';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useRoleNav } from '@/composables/useRoleNav';
import { edit } from '@/routes/profile';
import type { NavItem } from '@/types';

const { items } = useRoleNav();
const { isCurrentOrParentUrl } = useCurrentUrl();

const tabs = computed<NavItem[]>(() => [
    ...items.value.slice(0, 4),
    { title: 'Profil', href: edit(), icon: UserRound },
]);
</script>

<template>
    <nav
        aria-label="Navigasi utama"
        class="fixed inset-x-0 bottom-0 z-40 flex border-t border-border bg-background pb-[env(safe-area-inset-bottom)] md:hidden"
    >
        <Link
            v-for="item in tabs"
            :key="item.title"
            :href="item.href"
            :aria-current="isCurrentOrParentUrl(item.href) ? 'page' : undefined"
            class="flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 px-1 text-xs font-medium text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none aria-[current=page]:text-primary"
        >
            <component :is="item.icon" class="size-5" aria-hidden="true" />
            <span class="truncate">{{ item.title }}</span>
        </Link>
    </nav>
</template>
```

- [ ] **Step 3: Rewrite `AppSidebar.vue`** — keep the template, replace the script:

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useRoleNav } from '@/composables/useRoleNav';
import { dashboard } from '@/routes';

const { items } = useRoleNav();
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="items" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
```

In `NavMain.vue` change `<SidebarGroupLabel>Platform</SidebarGroupLabel>` to `<SidebarGroupLabel>Menu</SidebarGroupLabel>` and change `:is-active="isCurrentUrl(item.href)"` to `:is-active="isCurrentOrParentUrl(item.href)"` (destructure `isCurrentOrParentUrl` from `useCurrentUrl()`).

- [ ] **Step 4: Add the bottom nav to `AppSidebarLayout.vue`.** Import `BottomNav`, and change the content area to leave room for it:

```vue
<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import BottomNav from '@/components/BottomNav.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent
            variant="sidebar"
            class="min-w-0 overflow-x-clip pb-20 md:pb-0"
        >
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <BottomNav />
        <Toaster />
    </AppShell>
</template>
```

- [ ] **Step 5: Verify**

Run: `php artisan wayfinder:generate && npm run types:check && npm run check && npm run build`
Expected: all succeed.

- [ ] **Step 6: Manual check** (note result in the PR description)

Run: `php artisan migrate:fresh --seed && composer dev`, open the app, log in as `admin` / `password`.
Expected: sidebar visible ≥ 768 px; below 768 px the bottom nav shows "Beranda" and "Profil"; Tab key reaches every nav link with a visible focus ring; no horizontal scroll at 360 px.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: role-based navigation and mobile bottom nav"
```

---

### Task 7: Shared UI components

**Files:**
- Create: `resources/js/components/{StatusBadge,EmptyState,PageHeader,ConfirmDialog,Pagination}.vue`, `resources/js/components/ui/textarea/{Textarea.vue,index.ts}`, `resources/js/components/ui/native-select/{NativeSelect.vue,index.ts}`, `resources/js/lib/format.ts`

**Interfaces (later plans import these exactly):**
- `StatusBadge` props: `{ status: string; label?: string }` — `status` is one of `Pending|Assigned|In_Progress|On_Hold|Closed|Rejected|Cancelled`.
- `EmptyState` props: `{ title: string; description?: string }`, default slot for the action button.
- `PageHeader` props: `{ title: string; description?: string }`, default slot for actions.
- `ConfirmDialog` props: `{ open: boolean; title: string; description?: string; confirmLabel?: string; destructive?: boolean; reasonLabel?: string; reasonMin?: number; processing?: boolean }`; emits `update:open`, `confirm(reason?: string)`.
- `Pagination` props: `{ links: { url: string | null; label: string; active: boolean }[] }`.
- `Textarea` and `NativeSelect` are `v-model` text controls styled like `Input` (`h-11` for select).
- `lib/format.ts`: `formatDate(iso?: string | null): string`, `formatDateTime(iso?: string | null): string`, `formatRupiah(value?: number | string | null): string`.

- [ ] **Step 1: `lib/format.ts`**

```ts
const zone = 'Asia/Jakarta';

export function formatDate(iso?: string | null): string {
    if (!iso) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: zone,
    }).format(new Date(iso));
}

export function formatDateTime(iso?: string | null): string {
    if (!iso) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: zone,
    }).format(new Date(iso));
}

export function formatRupiah(value?: number | string | null): string {
    if (value === null || value === undefined || value === '') return '-';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
}
```

- [ ] **Step 2: `StatusBadge.vue`** (text always shown, so status is never color-only)

```vue
<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ status: string; label?: string }>();

const map: Record<string, { label: string; cls: string }> = {
    Pending: { label: 'Menunggu', cls: 'bg-status-pending-bg text-status-pending' },
    Assigned: { label: 'Ditugaskan', cls: 'bg-status-assigned-bg text-status-assigned' },
    In_Progress: { label: 'Dikerjakan', cls: 'bg-status-in-progress-bg text-status-in-progress' },
    On_Hold: { label: 'Ditunda', cls: 'bg-status-on-hold-bg text-status-on-hold' },
    Closed: { label: 'Selesai', cls: 'bg-status-closed-bg text-status-closed' },
    Rejected: { label: 'Ditolak', cls: 'bg-status-rejected-bg text-status-rejected' },
    Cancelled: { label: 'Dibatalkan', cls: 'bg-status-cancelled-bg text-status-cancelled' },
};

const entry = computed(
    () => map[props.status] ?? { label: props.status, cls: 'bg-muted text-muted-foreground' },
);
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap"
        :class="entry.cls"
        data-test="status-badge"
    >
        {{ label ?? entry.label }}
    </span>
</template>
```

- [ ] **Step 3: `PageHeader.vue` and `EmptyState.vue`**

```vue
<!-- PageHeader.vue -->
<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-xl font-semibold tracking-tight">{{ title }}</h1>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2"><slot /></div>
    </div>
</template>
```

```vue
<!-- EmptyState.vue -->
<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <div
        class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-border p-8 text-center"
    >
        <p class="font-medium">{{ title }}</p>
        <p v-if="description" class="max-w-sm text-sm text-muted-foreground">
            {{ description }}
        </p>
        <div class="mt-2"><slot /></div>
    </div>
</template>
```

- [ ] **Step 4: `ui/textarea/Textarea.vue` + `index.ts`**

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';

const model = defineModel<string>({ default: '' });
defineProps<{ class?: string; rows?: number }>();
</script>

<template>
    <textarea
        v-model="model"
        :rows="rows ?? 4"
        :class="
            cn(
                'flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                $props.class,
            )
        "
    />
</template>
```

`index.ts`: `export { default as Textarea } from './Textarea.vue';`

- [ ] **Step 5: `ui/native-select/NativeSelect.vue` + `index.ts`** (native select: best picker on phones)

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';

const model = defineModel<string | number | null>({ default: '' });
defineProps<{ class?: string }>();
</script>

<template>
    <select
        v-model="model"
        :class="
            cn(
                'h-11 w-full rounded-md border border-input bg-background px-3 text-base shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                $props.class,
            )
        "
    >
        <slot />
    </select>
</template>
```

`index.ts`: `export { default as NativeSelect } from './NativeSelect.vue';`

- [ ] **Step 6: `ConfirmDialog.vue`** (uses the existing `ui/dialog`; optional reason field with minimum length)

```vue
<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description?: string;
        confirmLabel?: string;
        destructive?: boolean;
        reasonLabel?: string;
        reasonMin?: number;
        processing?: boolean;
        error?: string;
    }>(),
    { confirmLabel: 'Ya, lanjutkan', reasonMin: 10 },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    confirm: [reason?: string];
}>();

const reason = ref('');
watch(
    () => props.open,
    (open) => {
        if (open) reason.value = '';
    },
);

const needsReason = computed(() => !!props.reasonLabel);
const reasonValid = computed(
    () => !needsReason.value || reason.value.trim().length >= props.reasonMin,
);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="needsReason" class="grid gap-2">
                <Label for="confirm-reason">{{ reasonLabel }}</Label>
                <Textarea id="confirm-reason" v-model="reason" :rows="4" />
                <p class="text-xs text-muted-foreground">
                    Minimal {{ reasonMin }} karakter.
                </p>
                <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
            </div>

            <DialogFooter class="gap-2">
                <Button
                    variant="outline"
                    class="h-11"
                    :disabled="processing"
                    @click="emit('update:open', false)"
                >
                    Batal
                </Button>
                <Button
                    class="h-11"
                    :variant="destructive ? 'destructive' : 'default'"
                    :disabled="processing || !reasonValid"
                    @click="emit('confirm', needsReason ? reason.trim() : undefined)"
                >
                    {{ confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
```

- [ ] **Step 7: `Pagination.vue`**

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    links: { url: string | null; label: string; active: boolean }[];
}>();

const clean = (label: string) =>
    label
        .replace('&laquo; Previous', 'Sebelumnya')
        .replace('Next &raquo;', 'Berikutnya')
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›');
</script>

<template>
    <nav
        v-if="links.length > 3"
        aria-label="Halaman"
        class="flex flex-wrap items-center justify-center gap-1"
    >
        <template v-for="(link, i) in links" :key="i">
            <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                :aria-current="link.active ? 'page' : undefined"
                class="inline-flex h-11 min-w-11 items-center justify-center rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="link.active ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-accent'"
            >
                {{ clean(link.label) }}
            </Link>
            <span
                v-else
                class="inline-flex h-11 min-w-11 items-center justify-center rounded-md px-3 text-sm text-muted-foreground opacity-60"
            >
                {{ clean(link.label) }}
            </span>
        </template>
    </nav>
</template>
```

- [ ] **Step 8: Verify and commit**

Run: `npm run types:check && npm run check && npm run build && php artisan test`
Expected: all pass.

```bash
git add -A
git commit -m "feat: shared UI components (status badge, dialog, pagination, form controls)"
```

---

## Self-Review (Plan 1)

- **Spec coverage:** F01 AC1–AC7 → Tasks 3–4 (`AuthenticationTest`, `RoleAccessTest`, `ProfileUpdateTest`); `is_active` → Tasks 2–4; F11 AC1–AC2 → Task 6 manual check; AC3 → Task 6; AC4 → Task 5 contrast script; AC5/AC6/AC7 → Tasks 6–7 (44 px `h-11`/`min-h-14`, text in `StatusBadge`, `disabled` on `processing`); AC8 (no raw hex in `.vue`) → components use tokens only. DM Sans, logo, token palette → Task 5.
- **Placeholder scan:** none.
- **Type consistency:** `UserRole::dashboardRoute()`, `hasRole()`, factory `reporter()/admin()/pic()/inactive()`, `StatusBadge` status keys match `ReportStatus` values defined in Plan 3.
- **Review Focus:** all five lines have a test (Task 3, Task 4).
- **Known decision needing your OK:** email verification, password reset and self-delete were removed (see index deviations).
