import type { AuthContextValue } from './AuthContext';
import type { FoundUApiConfig } from './founduApi';
import { validateSignupPayload } from './validateSignupPayload';

export type SignupCallbacks = {
  /** Only call when HTTP 201 + backend auth flags correct — never navigate to main app here. */
  onPendingApproval: () => void;
  /** Validation or API failure — stay on signup / show error; do NOT open main app. */
  onFailure: (message: string) => void;
};

/**
 * Safe signup handler for your Sign Up button:
 * - Validates locally
 * - Calls register() — main app is never opened from this path
 * - Navigates to “pending approval” only on real success (201)
 */
export async function submitSignup(
  auth: Pick<AuthContextValue, 'register' | 'apiConfig'>,
  payload: Record<string, unknown>,
  callbacks: SignupCallbacks,
): Promise<void> {
  const cfg = auth.apiConfig as FoundUApiConfig;

  const local = validateSignupPayload(cfg, payload);
  if (!local.ok) {
    callbacks.onFailure(local.message);
    return;
  }

  const result = await auth.register(payload);
  if (!result.ok) {
    callbacks.onFailure(result.message);
    return;
  }

  callbacks.onPendingApproval();
}
