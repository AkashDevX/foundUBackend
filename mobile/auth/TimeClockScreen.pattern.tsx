/**
 * Example screen — copy into your foundU RN app and wire into navigation.
 * Requires: npx expo install expo-location
 */
import React, { useEffect } from 'react';
import { ActivityIndicator, Pressable, Text, View } from 'react-native';
import { useTimeClock } from './useTimeClock';
import { workLocationTabCard } from './workAssignmentSelectors';
import { useAuth } from './AuthContext';

export function TimeClockScreenPattern(): React.JSX.Element {
  const { currentEmployee } = useAuth();
  const { status, loading, error, refresh, clockIn, clockOut } = useTimeClock();
  const location = workLocationTabCard(currentEmployee);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  const onClockIn = async () => {
    const result = await clockIn();
    if (!result.ok) {
      return;
    }
    await refresh();
  };

  const onClockOut = async () => {
    const result = await clockOut();
    if (!result.ok) {
      return;
    }
    await refresh();
  };

  return (
    <View style={{ flex: 1, padding: 20, gap: 12 }}>
      <Text style={{ fontSize: 22, fontWeight: '700' }}>Time clock</Text>
      <Text>
        Site: {location.title} — {location.subtitle ?? 'No address'}
      </Text>
      {location.latitude != null && location.longitude != null ? (
        <Text>
          Expected: {location.latitude.toFixed(5)}, {location.longitude.toFixed(5)}
        </Text>
      ) : (
        <Text>Your administrator must set work site coordinates before you can clock in.</Text>
      )}

      {loading ? <ActivityIndicator /> : null}
      {error ? <Text style={{ color: '#b00020' }}>{error}</Text> : null}

      {status ? (
        <Text>
          {status.is_clocked_in
            ? `Clocked in since ${status.open_session?.clocked_in_at ?? '—'}`
            : 'Not clocked in'}
        </Text>
      ) : null}

      <Pressable
        disabled={loading || !status?.can_clock_in}
        onPress={() => void onClockIn()}
        style={{ backgroundColor: '#0b6e4f', padding: 14, borderRadius: 10, opacity: status?.can_clock_in ? 1 : 0.5 }}
      >
        <Text style={{ color: '#fff', textAlign: 'center', fontWeight: '600' }}>Clock in</Text>
      </Pressable>

      <Pressable
        disabled={loading || !status?.can_clock_out}
        onPress={() => void onClockOut()}
        style={{ backgroundColor: '#1f3a5f', padding: 14, borderRadius: 10, opacity: status?.can_clock_out ? 1 : 0.5 }}
      >
        <Text style={{ color: '#fff', textAlign: 'center', fontWeight: '600' }}>Clock out</Text>
      </Pressable>
    </View>
  );
}
