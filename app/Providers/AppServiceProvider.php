<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\TenantPersonalAccessToken;
use App\Support\DisplayTimezone;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

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
        Sanctum::usePersonalAccessTokenModel(TenantPersonalAccessToken::class);

        Authenticate::redirectUsing(fn () => route('portal.login'));

        RedirectIfAuthenticated::redirectUsing(function () {
            /** @var \App\Models\OrganizationPortalUser|null $user */
            $user = auth('portal')->user();
            $company = $user?->company;

            if ($company !== null && $company->isPlatformController()) {
                return route('platform.dashboard');
            }

            return route('admin.dashboard');
        });

        Request::macro('tenantCompany', function (): ?Company {
            /** @var Request $this */
            return $this->attributes->get('tenant_company');
        });

        View::composer(['layouts.admin', 'admin.*', 'layouts.platform', 'platform.*'], function ($view): void {
            $view->with([
                'displayTimezone' => DisplayTimezone::name(),
                'displayTimezoneLabel' => DisplayTimezone::label(),
            ]);
        });
    }
}
