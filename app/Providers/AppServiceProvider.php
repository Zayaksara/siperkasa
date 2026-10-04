<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureLimiters();

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
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Configure rate limiters.
     */
    protected function configureLimiters(): void
    {
        RateLimiter::for('reports', function (Request $request) {
            return Limit::perHour(10)->by($request->user()->id ?? $request->ip());
        });
    }
}
