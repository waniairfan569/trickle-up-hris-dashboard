<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Current-tenant holder (SaaS multi-tenancy). No-op until a tenant is set.
        $this->app->singleton(\App\Tenancy\TenantManager::class, function ($app) {
            return new \App\Tenancy\TenantManager();
        });

        $this->app->singleton(\App\Services\HRPermissionService::class, function ($app) {
            return new \App\Services\HRPermissionService();
        });

        $this->app->singleton(\App\Services\EmployeeAccessService::class, function ($app) {
            return new \App\Services\EmployeeAccessService();
        });
        // Register Performance Review Events
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\ReviewSubmitted::class,
            [\App\Listeners\SendReviewNotification::class, 'handleSubmitted']
        );
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\ReviewShared::class,
            [\App\Listeners\SendReviewNotification::class, 'handleShared']
        );
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\ReviewSigned::class,
            [\App\Listeners\SendReviewNotification::class, 'handleSigned']
        );
        
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Login::class,
            \App\Listeners\LogSuccessfulLogin::class
        );

        // Central notification gate: respect each user's per-category channel
        // preferences (Settings → Notifications). Returning false cancels that
        // channel for that send. Categories not in the map are never gated.
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Notifications\Events\NotificationSending::class,
            function (\Illuminate\Notifications\Events\NotificationSending $event) {
                $category = \App\Support\NotificationCategories::for($event->notification);
                if (!$category) {
                    return true; // not an employee-facing, gated category
                }
                $notifiable = $event->notifiable;
                if (!($notifiable instanceof \App\Models\User)) {
                    return true;
                }
                return $notifiable->wantsNotification($category, $event->channel);
            }
        );

        // Cap the "remember me" cookie at exactly 360 days (Laravel defaults to
        // 5 years). We re-queue Laravel's own recaller cookie value with a
        // 360-day lifetime so the cookie expiry matches the 360-day session.
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Login::class,
            function (\Illuminate\Auth\Events\Login $event) {
                if (!($event->remember ?? false)) {
                    return;
                }
                try {
                    $guard = \Illuminate\Support\Facades\Auth::guard();
                    if (!method_exists($guard, 'getRecallerName')) {
                        return;
                    }
                    $name = $guard->getRecallerName();
                    $jar = app(\Illuminate\Cookie\CookieJar::class);
                    foreach ($jar->getQueuedCookies() as $cookie) {
                        if ($cookie->getName() === $name) {
                            $jar->queue($jar->make(
                                $name,
                                $cookie->getValue(),
                                518400, // 360 days in minutes
                                $cookie->getPath(),
                                $cookie->getDomain(),
                                $cookie->isSecure(),
                                $cookie->isHttpOnly(),
                                $cookie->isRaw(),
                                $cookie->getSameSite()
                            ));
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    // Never block a login over a cookie-lifetime tweak.
                }
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Define system gates for HRIS RBAC
        Gate::define('manage-employees', function (User $user) {
            $user->loadMissing('roles');
            return $user->isAdmin();
        });

        Gate::define('approve-time-off', function (User $user) {
            $user->loadMissing('roles');
            return $user->isAdmin() || $user->isManager();
        });

        Gate::define('view-reports', function (User $user) {
            $user->loadMissing('roles');
            return $user->isAdmin() || $user->isManager();
        });

        Gate::define('manage-settings', function (User $user) {
            $user->loadMissing('roles');
            return $user->hasRole(Role::SUPER_ADMIN);
        });

        // Register custom Blade directives for role checks
        \Illuminate\Support\Facades\Blade::directive('role', function ($role) {
            return "<?php if(auth()->check() && auth()->user()->hasRole(array_map('trim', explode(',', $role)))): ?>";
        });

        \Illuminate\Support\Facades\Blade::directive('endrole', function () {
            return "<?php endif; ?>";
        });

        // @usertime($timestamp) — render a canonical-stored timestamp in the
        // authenticated user's effective timezone, e.g. "09:28 AM".
        \Illuminate\Support\Facades\Blade::directive('usertime', function ($expression) {
            return "<?php echo app(\\App\\Services\\TimezoneService::class)->formatForUser($expression, auth()->user()); ?>";
        });

        // @userdate($timestamp[, $withTime]) — render as a localized date.
        \Illuminate\Support\Facades\Blade::directive('userdate', function ($expression) {
            return "<?php echo app(\\App\\Services\\TimezoneService::class)->formatDateForUser($expression, auth()->user()); ?>";
        });
    }
}
