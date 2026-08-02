import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import { getCurrentEmployee, postLogin, postRegister, type FoundUApiConfig } from './founduApi';
import {
  clearBearerToken,
  getAwaitingApprovalEmail,
  getBearerToken,
  saveBearerToken,
  setAwaitingApprovalEmail,
} from './sessionStorage';
import { formatApiErrorBody } from './apiErrors';
import type { CurrentEmployeeBody, LoginSuccessBody, RegisterSuccessBody } from './types';

type Phase =
  /** No token; user has not registered on this session or cleared. */
  | 'unauthenticated'
  /** Register API succeeded; deliberately no token — must not open “main app”. */
  | 'pending_org_approval'
  /** Bearer token present (only from login). */
  | 'authenticated';

type AuthContextValue = {
  phase: Phase;
  /** False until AsyncStorage has been read (avoid flashing the login screen when a token exists). */
  sessionReady: boolean;
  apiConfig: FoundUApiConfig;
  setApiConfig: (c: FoundUApiConfig) => void;
  /** Registration: never sets token; on API failure phase stays unchanged — never opens main app. */
  register: (
    payload: Record<string, unknown>,
  ) => Promise<
    | { ok: true; data: RegisterSuccessBody }
    | { ok: false; message: string; status: number }
  >;
  /** Login only — persists token here on success. */
  login: (email: string, password: string) => Promise<{ ok: true; data: LoginSuccessBody } | { ok: false; code?: string; message: string }>;
  logout: () => Promise<void>;
  /** Restore token from storage on cold start — still “authenticated” only if token exists. */
  hydrateFromStorage: () => Promise<void>;
  /** Current `/api/v1/me` snapshot (includes `work_assignment` for active signed-in employees). */
  currentEmployee: CurrentEmployeeBody['employee'] | null;
  refreshCurrentEmployee: () => Promise<{ ok: true } | { ok: false; message: string }>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider(props: {
  children: ReactNode;
  initialConfig: FoundUApiConfig;
}): React.JSX.Element {
  const [apiConfig, setApiConfigState] = useState<FoundUApiConfig>(props.initialConfig);
  const [phase, setPhase] = useState<Phase>('unauthenticated');
  const [sessionReady, setSessionReady] = useState(false);
  const [currentEmployee, setCurrentEmployee] = useState<CurrentEmployeeBody['employee'] | null>(null);

  const refreshCurrentEmployee = useCallback(async (): Promise<{ ok: true } | { ok: false; message: string }> => {
    const token = await getBearerToken();
    if (!token) {
        setCurrentEmployee(null);
        return { ok: false, message: 'No bearer token available.' };
    }
    const me = await getCurrentEmployee(apiConfig, token);
    if (!me.ok) {
        if (me.status === 401 || me.status === 403) {
            await clearBearerToken();
            setCurrentEmployee(null);
            setPhase('unauthenticated');
        }
        return { ok: false, message: formatApiErrorBody(me.body) };
    }
    setCurrentEmployee(me.data.employee);
    return { ok: true };
  }, [apiConfig]);

  const hydrateFromStorage = useCallback(async () => {
    const token = await getBearerToken();
    const pendingEmail = await getAwaitingApprovalEmail();
    if (token) {
      setPhase('authenticated');
      void refreshCurrentEmployee();
      setSessionReady(true);
      return;
    }
    if (pendingEmail) {
      setPhase('pending_org_approval');
      setSessionReady(true);
      return;
    }
    setPhase('unauthenticated');
    setCurrentEmployee(null);
    setSessionReady(true);
  }, [refreshCurrentEmployee]);

  useEffect(() => {
    void hydrateFromStorage();
  }, [hydrateFromStorage]);

  const setApiConfig = useCallback((c: FoundUApiConfig) => {
    setApiConfigState(c);
  }, []);

  const register = useCallback(
    async (payload: Record<string, unknown>) => {
      const result = await postRegister(apiConfig, payload);
      if (!result.ok) {
        return {
          ok: false as const,
          message: formatApiErrorBody(result.body),
          status: result.status,
        };
      }
      const data = result.data;

      // HARD RULE: never treat registration as sign-in
      if (data.auth?.token_issued === true || data.auth?.authenticated === true) {
        await clearBearerToken();
        return {
          ok: false as const,
          message: 'Unexpected API: registration must not issue a token.',
          status: 500,
        };
      }

      await clearBearerToken();
      await setAwaitingApprovalEmail(data.employee?.email ?? null);
      setCurrentEmployee(null);
      setPhase('pending_org_approval');

      return { ok: true as const, data };
    },
    [apiConfig],
  );

  const login = useCallback(
    async (email: string, password: string) => {
      const result = await postLogin(apiConfig, email, password);
      if (!result.ok) {
        const err = result.data as { message?: string; code?: string };
        return {
          ok: false as const,
          code: err?.code,
          message: err?.message ?? 'Login failed.',
        };
      }
      const data = result.data;

      if (!data.token || data.auth?.token_issued !== true) {
        await clearBearerToken();
        return { ok: false as const, message: 'Invalid login response.' };
      }

      await saveBearerToken(data.token);
      await setAwaitingApprovalEmail(null);
      setPhase('authenticated');
      setCurrentEmployee({
        ...data.employee,
        phone: null,
      });
      void refreshCurrentEmployee();

      return { ok: true as const, data };
    },
    [apiConfig, refreshCurrentEmployee],
  );

  const logout = useCallback(async () => {
    const token = await getBearerToken();
    if (token) {
      try {
        await fetch(`${apiConfig.baseUrl.replace(/\/$/, '')}/api/v1/logout`, {
          method: 'POST',
          headers: {
            'X-Company-Slug': apiConfig.companySlug,
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
          },
        });
      } catch {
        // still clear local session
      }
    }
    await clearBearerToken();
    setCurrentEmployee(null);
    const pending = await getAwaitingApprovalEmail();
    setPhase(pending ? 'pending_org_approval' : 'unauthenticated');
  }, [apiConfig]);

  const value = useMemo<AuthContextValue>(
    () => ({
      phase,
      sessionReady,
      apiConfig,
      setApiConfig,
      register,
      login,
      logout,
      hydrateFromStorage,
      currentEmployee,
      refreshCurrentEmployee,
    }),
    [phase, sessionReady, apiConfig, setApiConfig, register, login, logout, hydrateFromStorage, currentEmployee, refreshCurrentEmployee],
  );

  return <AuthContext.Provider value={value}>{props.children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return ctx;
}
