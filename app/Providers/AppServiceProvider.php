<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\TenantPersonalAccessToken;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
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

        RedirectIfAuthenticated::redirectUsing(fn () => route('admin.dashboard'));

        Request::macro('tenantCompany', function (): ?Company {
            /** @var Request $this */
            return $this->attributes->get('tenant_company');
        });
    }
}
