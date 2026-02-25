<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserRole;
use App\Enums\UserSubtype;
use App\Models\Aria2Config;
use App\Models\MediaDownloadRef;
use App\Models\User;
use App\Models\XtreamCodesConfig;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Override;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->bind(XtreamCodesConfig::class, static fn () => XtreamCodesConfig::firstOrFromEnv());
        $this->app->bind(Aria2Config::class, static fn () => Aria2Config::firstOrFromEnv());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // SQLite specific settings and optimizations
        if (DB::getDriverName() === 'sqlite') {
            try {
                DB::unprepared(<<<'SQL'
                -- PRAGMA busy_timeout = 5000;
                -- PRAGMA cache_size = -20000;
                -- PRAGMA foreign_keys = ON;
                -- PRAGMA incremental_vacuum;
                -- PRAGMA mmap_size = 2147483648;
                -- PRAGMA temp_store = MEMORY;
                -- PRAGMA synchronous = NORMAL;
                SQL,
                );
            } catch (QueryException $e) {
                throw_unless(str_contains($e->getMessage(), 'does not exist.'), $e);
            }
        }

        // Indicate that models should prevent lazy loading, silently discarding attributes, and accessing missing attributes.
        Model::shouldBeStrict(! app()->isProduction());

        // By default, Laravel uses Carbon for dates. If we modify the date
        // it will modify the original date. To avoid this, we can use
        // CarbonImmutable instead of Carbon.
        Date::use(CarbonImmutable::class);

        // Better to disable destructive commands in production
        DB::prohibitDestructiveCommands(app()->isProduction());

        Gate::define('admin', static fn (User $user): Response => $user->role === UserRole::Admin
            ? Response::allow()
            : Response::deny('Admin-only'));

        Gate::define('super-admin', static function (User $user): Response {
            if ($user->role !== UserRole::Admin) {
                return Response::deny('Admin-only');
            }

            return $user->is_super_admin
                ? Response::allow()
                : Response::deny('Super-admin-only');
        });

        Gate::define('member-internal', static function (User $user): Response {
            if ($user->role !== UserRole::Member) {
                return Response::deny('Internal-only');
            }

            return $user->subtype === UserSubtype::Internal
                ? Response::allow()
                : Response::deny('Internal-only');
        });

        Gate::define('member-external', static function (User $user): Response {
            if ($user->role !== UserRole::Member) {
                return Response::deny('External-only');
            }

            return $user->subtype === UserSubtype::External
                ? Response::allow()
                : Response::deny('External-only');
        });

        Gate::define('server-download', static function (User $user): Response {
            if ($user->role === UserRole::Admin) {
                return Response::allow();
            }

            if ($user->role === UserRole::Member && $user->subtype === UserSubtype::Internal) {
                return Response::allow();
            }

            if ($user->role === UserRole::Member && $user->subtype === UserSubtype::External) {
                return Response::deny('External accounts cannot use server downloads. Use Direct Download instead.');
            }

            return Response::deny('Server download access is restricted to admins and internal members.');
        });

        Gate::define('download-operations', static function (User $user, MediaDownloadRef $download): Response {
            if ($user->role === UserRole::Admin) {
                return Response::allow();
            }

            if ($user->role !== UserRole::Member) {
                return Response::deny('Download operations are restricted to admins and members.');
            }

            if ($user->subtype === UserSubtype::External) {
                return Response::deny('External accounts cannot perform download operations. Use Direct Download instead.');
            }

            if ($user->subtype !== UserSubtype::Internal) {
                return Response::deny('Download operations are restricted to internal members.');
            }

            return $download->user_id === $user->id
                ? Response::allow()
                : Response::denyAsNotFound();
        });

        Gate::define('manage-users', static fn (User $user): Response => $user->role === UserRole::Admin
            ? Response::allow()
            : Response::deny('Admin-only'));

        Gate::define('auto-download-schedules', static function (User $user): Response {
            if ($user->role === UserRole::Admin) {
                return Response::allow();
            }

            if ($user->role === UserRole::Member && $user->subtype === UserSubtype::Internal) {
                return Response::allow();
            }

            return $user->role === UserRole::Member && $user->subtype === UserSubtype::External
                ? Response::deny('External accounts cannot perform this action')
                : Response::deny('Internal-only');
        });
    }
}
