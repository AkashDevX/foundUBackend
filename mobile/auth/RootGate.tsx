import React, { type ReactNode } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { useAuth } from './AuthContext';

/**
 * Navigation gate — wire your actual navigators as children per phase.
 * Replace placeholder screens with your design system.
 *
 * Important: On `pending_org_approval`, still show email + password sign-in (or a button to it).
 * After the org approves them, they are NOT logged in automatically — they must submit `login()`.
 */

export type RootGateSlots = {
  /** Login / sign-in screen (email + password). */
  LoginScreen: ReactNode;
  /** After successful registration only — not authenticated. */
  PendingApprovalScreen: ReactNode;
  /** Token present — your main app tabs/stacks. */
  AppNavigator: ReactNode;
};

export function RootGate(props: RootGateSlots): React.JSX.Element {
  const { phase, sessionReady } = useAuth();

  if (!sessionReady) {
    return (
      <View style={styles.center}>
        <ActivityIndicator />
        <Text style={styles.hint}>Loading session…</Text>
      </View>
    );
  }

  if (phase === 'authenticated') {
    return <>{props.AppNavigator}</>;
  }

  if (phase === 'pending_org_approval') {
    return <>{props.PendingApprovalScreen}</>;
  }

  return <>{props.LoginScreen}</>;
}

const styles = StyleSheet.create({
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  hint: { marginTop: 8, color: '#64748b' },
});
