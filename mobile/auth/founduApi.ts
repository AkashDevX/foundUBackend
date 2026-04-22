/**
 * Thin API layer — enforces: register never returns a token; only login may save one (see AuthContext).
 */

import type {
  CurrentEmployeeBody,
  LoginErrorBody,
  LoginSuccessBody,
  RegisterSuccessBody,
  TenantHeaders,
} from './types';

export type FoundUApiConfig = {
  baseUrl: string;
  /** Company slug — same as X-Company-Slug and registration_company_slug. */
  companySlug: string;
  /**
   * From GET /api/v1/bootstrap → companies[].appKey for the selected org.
   * If set, must be sent as registration_company_app_key in the register body (server matches master DB).
   */
  companyAppKey?: string | null;
};

function headers(cfg: FoundUApiConfig): TenantHeaders {
  return {
    'X-Company-Slug': cfg.companySlug,
    'Content-Type': 'application/json',
    Accept: 'application/json',
  };
}

export async function postRegister(
  cfg: FoundUApiConfig,
  body: Record<string, unknown>,
): Promise<{ ok: true; data: RegisterSuccessBody } | { ok: false; status: number; body: unknown }> {
  const res = await fetch(`${cfg.baseUrl.replace(/\/$/, '')}/api/v1/register`, {
    method: 'POST',
    headers: headers(cfg),
    body: JSON.stringify(body),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) {
    return { ok: false, status: res.status, body: json };
  }
  if (res.status !== 201) {
    return {
      ok: false,
      status: res.status,
      body: { message: 'Registration must return HTTP 201 Created.', received_status: res.status },
    };
  }
  const data = json as RegisterSuccessBody;
  return { ok: true, data };
}

export async function postLogin(
  cfg: FoundUApiConfig,
  email: string,
  password: string,
): Promise<
  | { ok: true; data: LoginSuccessBody }
  | { ok: false; status: number; data: LoginErrorBody | Record<string, unknown> }
> {
  const res = await fetch(`${cfg.baseUrl.replace(/\/$/, '')}/api/v1/login`, {
    method: 'POST',
    headers: headers(cfg),
    body: JSON.stringify({ email, password }),
  });
  const json = (await res.json().catch(() => ({}))) as LoginSuccessBody | LoginErrorBody;
  if (!res.ok) {
    return { ok: false, status: res.status, data: json as LoginErrorBody };
  }
  return { ok: true, data: json as LoginSuccessBody };
}

/** Authenticated snapshot — includes admin-assigned shift / location / department (`work_assignment`). */
export async function getCurrentEmployee(
  cfg: FoundUApiConfig,
  bearerToken: string,
): Promise<{ ok: true; data: CurrentEmployeeBody } | { ok: false; status: number; body: unknown }> {
  const res = await fetch(`${cfg.baseUrl.replace(/\/$/, '')}/api/v1/me`, {
    method: 'GET',
    headers: {
      ...headers(cfg),
      Authorization: `Bearer ${bearerToken}`,
    },
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) {
    return { ok: false, status: res.status, body: json };
  }
  return { ok: true, data: json as CurrentEmployeeBody };
}
