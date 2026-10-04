<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use RuntimeException;

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
        $this->configureHealthCheck();

        // Read from config (not env) so it keeps working after `php artisan optimize`.
        if (filled(config('siperkasa.trusted_proxies'))) {
            $proxies = (string) config('siperkasa.trusted_proxies');
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

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
     * `/up` fails (500) when the database is unreachable or the disk is nearly full.
     */
    protected function configureHealthCheck(): void
    {
        Event::listen(DiagnosingHealth::class, function (): void {
            DB::select('select 1');

            $free = disk_free_space(storage_path());
            $minBytes = (int) config('siperkasa.health_min_free_mb') * 1024 * 1024;

            if ($free === false || $free < $minBytes) {
                throw new RuntimeException('Free disk space below threshold.');
            }
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
}
