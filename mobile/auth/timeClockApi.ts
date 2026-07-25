import type { FoundUApiConfig } from './founduApi';
import type {
  DeviceCoordinates,
  TimeClockErrorBody,
  TimeClockPunchBody,
  TimeClockStatusBody,
} from './timeClockTypes';

function authHeaders(cfg: FoundUApiConfig, bearerToken: string): Record<string, string> {
  return {
    'X-Company-Slug': cfg.companySlug,
    'Content-Type': 'application/json',
    Accept: 'application/json',
    Authorization: `Bearer ${bearerToken}`,
  };
}

export async function getTimeClockStatus(
  cfg: FoundUApiConfig,
  bearerToken: string,
): Promise<{ ok: true; data: TimeClockStatusBody } | { ok: false; status: number; body: unknown }> {
  const res = await fetch(`${cfg.baseUrl.replace(/\/$/, '')}/api/v1/time-clock/status`, {
    method: 'GET',
    headers: authHeaders(cfg, bearerToken),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) {
    return { ok: false, status: res.status, body: json };
  }
  return { ok: true, data: json as TimeClockStatusBody };
}

export async function postClockIn(
  cfg: FoundUApiConfig,
  bearerToken: string,
  coords: DeviceCoordinates,
): Promise<
  | { ok: true; data: TimeClockPunchBody }
  | { ok: false; status: number; body: TimeClockErrorBody | Record<string, unknown> }
> {
  const res = await fetch(`${cfg.baseUrl.replace(/\/$/, '')}/api/v1/time-clock/clock-in`, {
    method: 'POST',
    headers: authHeaders(cfg, bearerToken),
    body: JSON.stringify(coords),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) {
    return { ok: false, status: res.status, body: json as TimeClockErrorBody };
  }
  return { ok: true, data: json as TimeClockPunchBody };
}

export async function postClockOut(
  cfg: FoundUApiConfig,
  bearerToken: string,
  coords: DeviceCoordinates,
  comment?: string | null,
): Promise<
  | { ok: true; data: TimeClockPunchBody }
  | { ok: false; status: number; body: TimeClockErrorBody | Record<string, unknown> }
> {
  const payload: Record<string, unknown> = { ...coords };
  const trimmed = comment?.trim();
  if (trimmed) {
    payload.comment = trimmed;
  }

  const res = await fetch(`${cfg.baseUrl.replace(/\/$/, '')}/api/v1/time-clock/clock-out`, {
    method: 'POST',
    headers: authHeaders(cfg, bearerToken),
    body: JSON.stringify(payload),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) {
    return { ok: false, status: res.status, body: json as TimeClockErrorBody };
  }
  return { ok: true, data: json as TimeClockPunchBody };
}

export async function postAutoClockOut(
  cfg: FoundUApiConfig,
  bearerToken: string,
  coords: DeviceCoordinates,
): Promise<
  | { ok: true; data: TimeClockPunchBody }
  | { ok: false; status: number; body: TimeClockErrorBody | Record<string, unknown> }
> {
  const res = await fetch(`${cfg.baseUrl.replace(/\/$/, '')}/api/v1/time-clock/auto-clock-out`, {
    method: 'POST',
    headers: authHeaders(cfg, bearerToken),
    body: JSON.stringify({ ...coords, trigger: 'left_geofence' }),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) {
    return { ok: false, status: res.status, body: json as TimeClockErrorBody };
  }
  return { ok: true, data: json as TimeClockPunchBody };
}

export async function postBreakStart(
  cfg: FoundUApiConfig,
  bearerToken: string,
  coords: DeviceCoordinates,
): Promise<
  | { ok: true; data: TimeClockPunchBody }
  | { ok: false; status: number; body: TimeClockErrorBody | Record<string, unknown> }
> {
  const res = await fetch(`${cfg.baseUrl.replace(/\/$/, '')}/api/v1/time-clock/break-start`, {
    method: 'POST',
    headers: authHeaders(cfg, bearerToken),
    body: JSON.stringify(coords),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) {
    return { ok: false, status: res.status, body: json as TimeClockErrorBody };
  }
  return { ok: true, data: json as TimeClockPunchBody };
}

export async function postBreakEnd(
  cfg: FoundUApiConfig,
  bearerToken: string,
  coords: DeviceCoordinates,
): Promise<
  | { ok: true; data: TimeClockPunchBody }
  | { ok: false; status: number; body: TimeClockErrorBody | Record<string, unknown> }
> {
  const res = await fetch(`${cfg.baseUrl.replace(/\/$/, '')}/api/v1/time-clock/break-end`, {
    method: 'POST',
    headers: authHeaders(cfg, bearerToken),
    body: JSON.stringify(coords),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) {
    return { ok: false, status: res.status, body: json as TimeClockErrorBody };
  }
  return { ok: true, data: json as TimeClockPunchBody };
}
