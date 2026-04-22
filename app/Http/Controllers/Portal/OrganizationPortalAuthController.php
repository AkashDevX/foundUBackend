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
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return view('portal.login', compact('companies'));
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
                'company_id' => __('The selected organization is invalid.'),
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
                'email' => __('These credentials do not match our records for this organization.'),
            ]);
        }

        Auth::guard('portal')->login($user, (bool) ($validated['remember'] ?? false));

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('portal')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
