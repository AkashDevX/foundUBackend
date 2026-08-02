<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrganizationPortalUser;
use App\Models\TermsAndConditions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminTermsController extends Controller
{
    public function edit(Request $request): View
    {
        $ctx = $this->pageContext($request);
        $terms = $this->currentTerms($ctx['connection']);

        return view('admin.terms', array_merge($ctx, [
            'terms' => $terms,
        ]));
    }

    public function update(Request $request): RedirectResponse
    {
        $ctx = $this->pageContext($request);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:100000'],
            'last_updated_on' => ['required', 'date'],
        ]);

        $terms = $this->currentTerms($ctx['connection']);
        $terms->content = $data['content'];
        $terms->last_updated_on = $data['last_updated_on'];
        $terms->save();

        return redirect()
            ->route('admin.terms.edit')
            ->with('status', 'Terms and conditions saved. Mobile create-account will show this text.');
    }

    private function currentTerms(string $connection): TermsAndConditions
    {
        $terms = TermsAndConditions::on($connection)->orderBy('id')->first();

        if ($terms) {
            return $terms;
        }

        $terms = new TermsAndConditions;
        $terms->setConnection($connection);
        $terms->content = '';
        $terms->last_updated_on = now()->toDateString();
        $terms->save();

        return $terms;
    }

    /**
     * @return array{company: \App\Models\Company, connection: string}
     */
    private function pageContext(Request $request): array
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();

        return [
            'company' => $company,
            'connection' => $company->tenant_connection,
        ];
    }
}
