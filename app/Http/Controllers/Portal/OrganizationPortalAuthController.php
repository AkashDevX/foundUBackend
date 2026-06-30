<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\OrganizationPortalUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class OrganizationPortalAuthController extends Controller
{
    public function create(): View
    {
        $companies = Company::query()
            ->where('is_active', true)
            ->tenantOrganizations()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $platformCompany = Company::query()
            ->where('is_active', true)
            ->platformController()
            ->orderBy('name')
            ->first(['id', 'name', 'slug']);

        return view('portal.login', compact('companies', 'platformCompany'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $companyOk = Company::query()
            ->where('id', $validated['company_id'])
            ->where('is_active', true)
            ->exists();

        if (! $companyOk) {
            throw ValidationException::withMessages([
                'login' => __('Please select a valid organization and try again.'),
            ]);
        }

        $email = mb_strtolower(trim($validated['email']));

        /** @var OrganizationPortalUser|null $user */
        $user = OrganizationPortalUser::query()
            ->where('company_id', $validated['company_id'])
            ->where('email', $email)
            ->first();

        if ($user === null || ! Hash::check($validated['password'], $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'login' => __('The email or password is incorrect for the selected organization.'),
            ]);
        }

        Auth::guard('portal')->login($user, (bool) ($validated['remember'] ?? false));

        $request->session()->regenerate();

        $company = $user->company()->firstOrFail();
        $homeRoute = $company->isPlatformController()
            ? 'platform.dashboard'
            : 'admin.dashboard';

        return redirect()->intended(route($homeRoute));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('portal')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
