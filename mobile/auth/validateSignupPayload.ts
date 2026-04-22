import type { FoundUApiConfig } from './founduApi';

export type SignupValidationResult = { ok: true } | { ok: false; message: string };

/**
 * Client-side checks before calling the API — stops obvious mistakes.
 * Server still validates against master registry + tenant employees DB.
 */
export function validateSignupPayload(
  cfg: FoundUApiConfig,
  payload: Record<string, unknown>,
): SignupValidationResult {
  const slug = payload.registration_company_slug;
  if (typeof slug !== 'string' || slug.trim() === '') {
    return { ok: false, message: 'Choose an organization.' };
  }
  if (slug !== cfg.companySlug) {
    return {
      ok: false,
      message: 'Organization mismatch: X-Company-Slug must match registration_company_slug.',
    };
  }

  const registryKey = cfg.companyAppKey;
  if (registryKey !== undefined && registryKey !== null && registryKey !== '') {
    const submitted = payload.registration_company_app_key;
    if (submitted !== registryKey) {
      return {
        ok: false,
        message:
          'Organization key must match the selected company from bootstrap (registration_company_app_key / appKey).',
      };
    }
  }

  const pw = payload.password;
  const pwConf = payload.password_confirmation;
  if (typeof pw !== 'string' || pw === '') {
    return { ok: false, message: 'Enter a password.' };
  }
  if (pw !== pwConf) {
    return { ok: false, message: 'Password and confirmation do not match.' };
  }

  const email = payload.email;
  if (typeof email !== 'string' || !email.includes('@')) {
    return { ok: false, message: 'Enter a valid email.' };
  }

  const name = payload.full_legal_name;
  if (typeof name !== 'string' || name.trim() === '') {
    return { ok: false, message: 'Enter your full legal name.' };
  }

  return { ok: true };
}
