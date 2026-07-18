# Mobile auth reference (React Native)

This folder is **not** a standalone app. Copy `mobile/auth/` into your React Native project (for example `src/auth/`).

## Install (in your RN app)

```bash
npm install @react-native-async-storage/async-storage
npx expo install expo-location
```

For production tokens, prefer `expo-secure-store` (Expo) or Keychain and swap `sessionStorage.ts`.

## Configure

1. Call **`GET /api/v1/bootstrap`** (no tenant header) → pick a company → use **`slug`** and **`appKey`**.
2. Set auth config:

```ts
setApiConfig({
  baseUrl: 'https://your-api-host',
  companySlug: '<slug from bootstrap>',
  companyAppKey: '<appKey from bootstrap>',
});
```

3. **Register** body must include:
   - `registration_company_slug` = same as `companySlug`
   - `registration_company_app_key` = same as `companyAppKey` (when the company has an `app_key` in the master DB)
   - `X-Company-Slug` request header = same `companySlug` (see `founduApi.ts`)

4. **Do not** navigate to the main app after sign-up. Use `submitSignup()` or `register()` and only go to a “pending approval” screen on **HTTP 201**. Use `RootGate` or `phase === 'authenticated'` (token from **`POST /api/v1/login` only) for the main app.

## Key files

| File | Purpose |
|------|---------|
| `AuthContext.tsx` | Phases: unauthenticated → pending (after register) → authenticated (only after login). |
| `submitSignup.ts` | Safe sign-up: no main app on failure. |
| `validateSignupPayload.ts` | Slug / app key / password checks before the network call. |
| `RootGate.tsx` | Renders login vs pending vs app from `phase`. |
| `SignupButton.pattern.tsx` | Example press handler. |

## Backend API (same host)

- `GET /api/v1/bootstrap` — companies + picklists (master DB)
- `POST /api/v1/request-organization` — header `X-Platform-Slug: crulynk`; new organisation access request (master DB, **CruLynk platform portal only**)
- `POST /api/v1/register` — header `X-Company-Slug`; creates **pending** employee (tenant DB)
- `POST /api/v1/login` — email + password; **Bearer token** only if status is **active**
- `GET /api/v1/me`, `POST /api/v1/logout` — header `Authorization: Bearer …`
- `GET /api/v1/time-clock/status` — current clock state + assignment geofence info
- `POST /api/v1/time-clock/clock-in` — body `{ latitude, longitude, accuracy_meters? }` (must be within radius of assigned work site)
- `POST /api/v1/time-clock/clock-out` — body `{ latitude, longitude, accuracy_meters?, comment? }`; must be clocked in first (`comment` is optional, max 2000 chars)

Organization portal (admin approve/decline): web UI at `/` → `/admin` after signing in.

## Show assignment in RN tabs

After login (or app restart with token), `AuthContext` now hydrates `currentEmployee` from `GET /api/v1/me`.
Use the selector helpers to render each tab card:

```ts
import { useAuth, departmentTabCard, workLocationTabCard, shiftTabCard } from './auth';

const { currentEmployee, refreshCurrentEmployee } = useAuth();

// Optional pull-to-refresh
await refreshCurrentEmployee();

const department = departmentTabCard(currentEmployee);
const location = workLocationTabCard(currentEmployee);
const shift = shiftTabCard(currentEmployee);
```

`currentEmployee.work_assignment` includes admin-assigned `department`, `work_location` (with `latitude/longitude`), and `shift`.

## Clock in / clock out (GPS geofence)

Uses **`time_clock_entries`** only (one row per punch: `clock_in` / `clock_out`). Legacy `time_clock_sessions` was removed.

Copy `timeClockApi.ts`, `timeClockTypes.ts`, `useTimeClock.ts`, and optionally `TimeClockScreen.pattern.tsx`.

```ts
import { useTimeClock } from './auth';

const { status, clockIn, clockOut, refresh } = useTimeClock();
// status.is_clocked_in, status.can_clock_in, status.geofence_radius_meters
```

The API compares device GPS to the **assigned work location** coordinates (set in the admin workforce UI). Default allowed radius: **100 m** (`TIME_CLOCK_GEOFENCE_RADIUS_METERS` on the server).
