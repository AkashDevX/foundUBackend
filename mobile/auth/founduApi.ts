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

/**
 * Request a password-reset OTP email for an active employee.
 * Server always returns a generic success body when validation passes.
 */
export async function postForgotPassword(
  cfg: FoundUApiConfig,
  email: string,
): Promise<
  | { ok: true; message: string }
  | { ok: false; status: number; message: string; body: unknown }
> {
  const res = await fetch(`${cfg.baseUrl.replace(/\/$/, '')}/api/v1/forgot-password`, {
    method: 'POST',
    headers: headers(cfg),
    body: JSON.stringify({ email }),
  });
  const json = (await res.json().catch(() => ({}))) as { message?: string };
  const message =
    typeof json.message === 'string' && json.message.trim() !== ''
      ? json.message.trim()
      : res.ok
        ? 'If an account exists for that email, we have sent a verification code.'
        : 'Could not send verification code.';
  if (!res.ok) {
    return { ok: false, status: res.status, message, body: json };
  }
  return { ok: true, message };
}

export async function postVerifyPasswordResetOtp(
  cfg: FoundUApiConfig,
  email: string,
  otp: string,
): Promise<
  | { ok: true; resetToken: string; message: string }
  | { ok: false; status: number; message: string; body: unknown }
> {
  const res = await fetch(`${cfg.baseUrl.replace(/\/$/, '')}/api/v1/forgot-password/verify-otp`, {
    method: 'POST',
    headers: headers(cfg),
    body: JSON.stringify({ email, otp }),
  });
  const json = (await res.json().catch(() => ({}))) as { message?: string; reset_token?: string };
  const message =
    typeof json.message === 'string' && json.message.trim() !== ''
      ? json.message.trim()
      : 'Verification failed.';
  if (!res.ok) {
    return { ok: false, status: res.status, message, body: json };
  }
  const resetToken = typeof json.reset_token === 'string' ? json.reset_token : '';
  if (resetToken === '') {
    return { ok: false, status: res.status, message: 'No reset token returned.', body: json };
  }
  return { ok: true, resetToken, message };
}

export async function postResetPassword(
  cfg: FoundUApiConfig,
  resetToken: string,
  password: string,
  passwordConfirmation: string,
): Promise<
  | { ok: true; message: string }
  | { ok: false; status: number; message: string; body: unknown }
> {
  const res = await fetch(`${cfg.baseUrl.replace(/\/$/, '')}/api/v1/forgot-password/reset`, {
    method: 'POST',
    headers: headers(cfg),
    body: JSON.stringify({
      reset_token: resetToken,
      password,
      password_confirmation: passwordConfirmation,
    }),
  });
  const json = (await res.json().catch(() => ({}))) as { message?: string };
  const message =
    typeof json.message === 'string' && json.message.trim() !== ''
      ? json.message.trim()
      : res.ok
        ? 'Your password has been updated.'
        : 'Could not update password.';
  if (!res.ok) {
    return { ok: false, status: res.status, message, body: json };
  }
  return { ok: true, message };
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

/** Employee task allocations for the signed-in employee. */
export async function getEmployeeTasks(
  cfg: FoundUApiConfig,
  bearerToken: string,
  date?: string,
): Promise<{ ok: true; data: import('./taskTypes').EmployeeTasksBody } | { ok: false; status: number; body: unknown }> {
  const query = date ? `?date=${encodeURIComponent(date)}` : '';
  const res = await fetch(`${cfg.baseUrl.replace(/\/$/, '')}/api/v1/tasks${query}`, {
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
  return { ok: true, data: json as import('./taskTypes').EmployeeTasksBody };
}
