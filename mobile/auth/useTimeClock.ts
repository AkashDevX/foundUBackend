import { useCallback, useState } from 'react';
import * as Location from 'expo-location';
import { useAuth } from './AuthContext';
import { getBearerToken } from './sessionStorage';
import { getTimeClockStatus, postClockIn, postClockOut } from './timeClockApi';
import type { DeviceCoordinates, TimeClockStatus } from './timeClockTypes';

export type TimeClockHook = {
  status: TimeClockStatus | null;
  loading: boolean;
  error: string | null;
  refresh: () => Promise<void>;
  clockIn: () => Promise<{ ok: true } | { ok: false; message: string; code?: string }>;
  clockOut: () => Promise<{ ok: true } | { ok: false; message: string; code?: string }>;
};

async function readDeviceCoordinates(): Promise<DeviceCoordinates> {
  const permission = await Location.requestForegroundPermissionsAsync();
  if (permission.status !== 'granted') {
    throw new Error('Location permission is required to clock in or out at your work site.');
  }

  const position = await Location.getCurrentPositionAsync({
    accuracy: Location.Accuracy.High,
  });

  return {
    latitude: position.coords.latitude,
    longitude: position.coords.longitude,
    accuracy_meters: position.coords.accuracy ?? null,
  };
}

function formatPunchError(body: unknown): { message: string; code?: string } {
  if (body && typeof body === 'object' && 'message' in body) {
    const record = body as { message?: string; code?: string };
    return {
      message: record.message ?? 'Clock action failed.',
      code: record.code,
    };
  }
  return { message: 'Clock action failed.' };
}

export function useTimeClock(): TimeClockHook {
  const { apiConfig } = useAuth();
  const [status, setStatus] = useState<TimeClockStatus | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const refresh = useCallback(async () => {
    const token = await getBearerToken();
    if (!token) {
      setStatus(null);
      setError('Sign in to use the time clock.');
      return;
    }

    setLoading(true);
    setError(null);
    const result = await getTimeClockStatus(apiConfig, token);
    setLoading(false);

    if (!result.ok) {
      setError('Could not load time clock status.');
      return;
    }

    setStatus(result.data.time_clock);
  }, [apiConfig]);

  const punch = useCallback(
    async (action: 'in' | 'out'): Promise<{ ok: true } | { ok: false; message: string; code?: string }> => {
      const token = await getBearerToken();
      if (!token) {
        return { ok: false, message: 'Sign in to use the time clock.' };
      }

      setLoading(true);
      setError(null);

      try {
        const coords = await readDeviceCoordinates();
        const result =
          action === 'in'
            ? await postClockIn(apiConfig, token, coords)
            : await postClockOut(apiConfig, token, coords);

        setLoading(false);

        if (!result.ok) {
          const err = formatPunchError(result.body);
          setError(err.message);
          return { ok: false, message: err.message, code: err.code };
        }

        setStatus(result.data.time_clock);
        return { ok: true };
      } catch (e) {
        setLoading(false);
        const message = e instanceof Error ? e.message : 'Could not read GPS location.';
        setError(message);
        return { ok: false, message };
      }
    },
    [apiConfig],
  );

  const clockIn = useCallback(() => punch('in'), [punch]);
  const clockOut = useCallback(() => punch('out'), [punch]);

  return {
    status,
    loading,
    error,
    refresh,
    clockIn,
    clockOut,
  };
}
