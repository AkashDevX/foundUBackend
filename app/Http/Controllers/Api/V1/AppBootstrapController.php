<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\RegistrationPicklistItem;
use Illuminate\Http\JsonResponse;

/**
 * Public metadata for the mobile app: organizations + registration picklists (master DB only).
 */
class AppBootstrapController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $companies = Company::query()
            ->where('is_active', true)
            ->whereNotNull('tenant_connection')
            ->whereNotNull('database_name')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'app_key']);

        $items = RegistrationPicklistItem::query()
            ->where('is_active', true)
            ->orderBy('picklist_key')
            ->orderBy('sort_order')
            ->orderBy('value')
            ->get(['picklist_key', 'value', 'label']);

        $picklists = [];
        foreach ($items->groupBy('picklist_key') as $key => $group) {
            $picklists[$key] = $group->values()->map(function (RegistrationPicklistItem $row): array {
                return [
                    'value' => $row->value,
                    'label' => $row->label !== null && $row->label !== '' ? $row->label : $row->value,
                ];
            })->all();
        }

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'companies' => $companies->map(fn (Company $c): array => [
                'id' => $c->id,
                'appKey' => $c->app_key,
                'slug' => $c->slug,
                'name' => $c->name,
            ])->values()->all(),
            'picklists' => $picklists,
        ]);
    }
}
