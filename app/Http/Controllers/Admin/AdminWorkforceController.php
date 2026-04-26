<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\OrganizationPortalUser;
use App\Models\Shift;
use App\Models\WorkLocation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AdminWorkforceController extends Controller
{
    /**
     * @return array<int, string>
     */
    private function allowedShiftDays(): array
    {
        return ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    }

    /**
     * @param mixed $input
     * @return array<int, string>|null
     */
    private function normalizeShiftDays(mixed $input): ?array
    {
        if (! is_array($input)) {
            return null;
        }

        $allowed = $this->allowedShiftDays();
        $days = collect($input)
            ->map(fn ($d) => is_string($d) ? strtolower(trim($d)) : null)
            ->filter(fn ($d) => is_string($d) && in_array($d, $allowed, true))
            ->unique()
            ->values()
            ->all();

        return $days === [] ? null : $days;
    }

    private function nominatimUserAgent(): string
    {
        return sprintf(
            '%s Workforce Admin (%s)',
            config('app.name', 'Laravel'),
            parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'
        );
    }

    public function index(Request $request): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('admin.workforce.departments');
    }

    public function departments(Request $request): View
    {
        return $this->renderSection($request, 'departments');
    }

    public function workLocations(Request $request): View
    {
        return $this->renderSection($request, 'work-locations');
    }

    public function shifts(Request $request): View
    {
        return $this->renderSection($request, 'shifts');
    }

    private function renderSection(Request $request, string $section): View
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $departments = Department::on($conn)->orderBy('name')->get();
        $locations = WorkLocation::on($conn)->orderBy('name')->get();
        $shifts = Shift::on($conn)->orderBy('name')->get();

        return view('admin.workforce', [
            'company' => $company,
            'departments' => $departments,
            'workLocations' => $locations,
            'shifts' => $shifts,
            'mapDefaultLat' => config('workforce.default_map_lat'),
            'mapDefaultLng' => config('workforce.default_map_lng'),
            'mapDefaultZoom' => config('workforce.default_map_zoom'),
            'section' => $section,
        ]);
    }

    /**
     * Forward geocode a free-text address via Nominatim search.
     *
     * @return array{lat: float, lng: float, display_name: string}|null
     */
    private function geocodeAddress(string $address): ?array
    {
        $query = trim($address);
        if ($query === '') {
            return null;
        }

        try {
            $response = Http::timeout(12)
                ->withHeaders([
                    'User-Agent' => $this->nominatimUserAgent(),
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'limit' => 1,
                ]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();
        if (! is_array($json) || ! isset($json[0]) || ! is_array($json[0])) {
            return null;
        }

        $first = $json[0];
        $display = $first['display_name'] ?? null;
        $lat = isset($first['lat']) ? (float) $first['lat'] : null;
        $lng = isset($first['lon']) ? (float) $first['lon'] : null;

        if (! is_string($display) || $display === '' || $lat === null || $lng === null) {
            return null;
        }

        return [
            'display_name' => $display,
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    /**
     * Reverse geocode via OpenStreetMap Nominatim (server-side; polite User-Agent).
     *
     * @see https://operations.osmfoundation.org/policies/nominatim/
     */
    public function reverseGeocode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        try {
            $response = Http::timeout(12)
                ->withHeaders([
                    'User-Agent' => $this->nominatimUserAgent(),
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en',
                ])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $data['lat'],
                    'lon' => $data['lng'],
                    'format' => 'jsonv2',
                ]);
        } catch (\Throwable) {
            return response()->json([
                'ok' => false,
                'message' => 'Could not reach the geocoding service. Try again in a moment.',
            ], 502);
        }

        if (! $response->successful()) {
            return response()->json([
                'ok' => false,
                'message' => 'Geocoding service returned an error.',
            ], 502);
        }

        $json = $response->json();
        $display = is_array($json) ? ($json['display_name'] ?? null) : null;

        if (! is_string($display) || $display === '') {
            return response()->json([
                'ok' => false,
                'message' => 'No address found for this pin.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'display_name' => $display,
            'lat' => (float) $data['lat'],
            'lng' => (float) $data['lng'],
        ]);
    }

    public function searchGeocode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
        ]);

        try {
            $response = Http::timeout(12)
                ->withHeaders([
                    'User-Agent' => $this->nominatimUserAgent(),
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $data['q'],
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'limit' => 5,
                ]);
        } catch (\Throwable) {
            return response()->json([
                'ok' => false,
                'message' => 'Could not reach the geocoding service. Try again in a moment.',
                'suggestions' => [],
            ], 502);
        }

        if (! $response->successful()) {
            return response()->json([
                'ok' => false,
                'message' => 'Geocoding service returned an error.',
                'suggestions' => [],
            ], 502);
        }

        $json = $response->json();
        if (! is_array($json)) {
            return response()->json([
                'ok' => true,
                'suggestions' => [],
            ]);
        }

        $suggestions = collect($json)
            ->filter(fn ($row) => is_array($row) && is_string($row['display_name'] ?? null))
            ->take(5)
            ->map(fn (array $row) => [
                'display_name' => (string) $row['display_name'],
                'lat' => isset($row['lat']) ? (float) $row['lat'] : null,
                'lng' => isset($row['lon']) ? (float) $row['lon'] : null,
            ])
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'suggestions' => $suggestions,
        ]);
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $data = $request->validate([
            'department_name' => ['required', 'string', 'max:160'],
            'department_code' => ['nullable', 'string', 'max:32'],
        ]);

        Department::on($conn)->create([
            'name' => $data['department_name'],
            'code' => $data['department_code'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->back()->with('status', 'Department created.');
    }

    public function updateDepartment(Request $request, int $department): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $data = $request->validate([
            'department_name' => ['required', 'string', 'max:160'],
            'department_code' => ['nullable', 'string', 'max:32'],
        ]);

        $target = Department::on($conn)->whereKey($department)->firstOrFail();
        $target->forceFill([
            'name' => $data['department_name'],
            'code' => $data['department_code'] ?? null,
        ])->save();

        return redirect()->back()->with('status', 'Department updated.');
    }

    public function storeWorkLocation(Request $request): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $request->merge([
            'latitude' => $request->filled('latitude') ? $request->input('latitude') : null,
            'longitude' => $request->filled('longitude') ? $request->input('longitude') : null,
        ]);

        $data = $request->validate([
            'location_name' => ['required', 'string', 'max:200'],
            'address' => ['nullable', 'string', 'max:2000'],
            'location_notes' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $lat = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $lng = isset($data['longitude']) ? (float) $data['longitude'] : null;

        // If user typed a manual address but did not choose a suggestion,
        // resolve one best-match coordinate pair server-side before saving.
        if ($lat === null && $lng === null && is_string($data['address'] ?? null) && trim((string) $data['address']) !== '') {
            $resolved = $this->geocodeAddress((string) $data['address']);
            if ($resolved !== null) {
                $lat = $resolved['lat'];
                $lng = $resolved['lng'];
                $data['address'] = $resolved['display_name'];
            }
        }

        if ($lat === null xor $lng === null) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['latitude' => 'Provide both latitude and longitude from the map, or leave both empty.']);
        }

        WorkLocation::on($conn)->create([
            'name' => $data['location_name'],
            'address' => $data['address'] ?? null,
            'latitude' => $lat,
            'longitude' => $lng,
            'notes' => $data['location_notes'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->back()->with('status', 'Work location created.');
    }

    public function updateWorkLocation(Request $request, int $location): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $request->merge([
            'latitude' => $request->filled('latitude') ? $request->input('latitude') : null,
            'longitude' => $request->filled('longitude') ? $request->input('longitude') : null,
        ]);

        $data = $request->validate([
            'location_name' => ['required', 'string', 'max:200'],
            'address' => ['nullable', 'string', 'max:2000'],
            'location_notes' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $lat = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $lng = isset($data['longitude']) ? (float) $data['longitude'] : null;

        if ($lat === null && $lng === null && is_string($data['address'] ?? null) && trim((string) $data['address']) !== '') {
            $resolved = $this->geocodeAddress((string) $data['address']);
            if ($resolved !== null) {
                $lat = $resolved['lat'];
                $lng = $resolved['lng'];
                $data['address'] = $resolved['display_name'];
            }
        }

        if ($lat === null xor $lng === null) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['latitude' => 'Provide both latitude and longitude, or leave both empty.']);
        }

        $target = WorkLocation::on($conn)->whereKey($location)->firstOrFail();
        $target->forceFill([
            'name' => $data['location_name'],
            'address' => $data['address'] ?? null,
            'latitude' => $lat,
            'longitude' => $lng,
            'notes' => $data['location_notes'] ?? null,
        ])->save();

        return redirect()->back()->with('status', 'Work location updated.');
    }

    public function storeShift(Request $request): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $data = $request->validate([
            'shift_name' => ['required', 'string', 'max:160'],
            'shift_start_time' => ['required', 'date_format:H:i'],
            'shift_end_time' => ['required', 'date_format:H:i'],
            'shift_days' => ['nullable', 'array'],
            'shift_days.*' => ['string', 'in:mon,tue,wed,thu,fri,sat,sun'],
            'shift_breaks_summary' => ['nullable', 'string', 'max:255'],
            'shift_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        Shift::on($conn)->create([
            'name' => $data['shift_name'],
            'start_time' => $data['shift_start_time'],
            'end_time' => $data['shift_end_time'],
            'shift_days' => $this->normalizeShiftDays($data['shift_days'] ?? null),
            'breaks_summary' => $data['shift_breaks_summary'] ?? null,
            'notes' => $data['shift_notes'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->back()->with('status', 'Shift created.');
    }

    public function updateShift(Request $request, int $shift): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $data = $request->validate([
            'shift_name' => ['required', 'string', 'max:160'],
            'shift_start_time' => ['required', 'date_format:H:i'],
            'shift_end_time' => ['required', 'date_format:H:i'],
            'shift_days' => ['nullable', 'array'],
            'shift_days.*' => ['string', 'in:mon,tue,wed,thu,fri,sat,sun'],
            'shift_breaks_summary' => ['nullable', 'string', 'max:255'],
            'shift_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $target = Shift::on($conn)->whereKey($shift)->firstOrFail();
        $target->forceFill([
            'name' => $data['shift_name'],
            'start_time' => $data['shift_start_time'],
            'end_time' => $data['shift_end_time'],
            'shift_days' => $this->normalizeShiftDays($data['shift_days'] ?? null),
            'breaks_summary' => $data['shift_breaks_summary'] ?? null,
            'notes' => $data['shift_notes'] ?? null,
        ])->save();

        return redirect()->back()->with('status', 'Shift updated.');
    }
}
