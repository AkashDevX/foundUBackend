/**
 * PATTERN: Sign Up must not navigate to the main app on press alone.
 * Use submitSignup() → onPendingApproval only after HTTP 201 from Laravel.
 *
 * Wrong: onPress={() => navigation.replace('Main')}
 * Right: await submitSignup(auth, payload, { onPendingApproval: () => navigation.replace('PendingApproval'), ... })
 */

import React from 'react';
import { Alert, Pressable, Text } from 'react-native';
import { useAuth } from './AuthContext';
import { submitSignup } from './submitSignup';

type Props = {
  buildPayload: () => Record<string, unknown>;
  onGoToPending: () => void;
};

export function ExampleSignupButton(props: Props): React.JSX.Element {
  const auth = useAuth();

  return (
    <Pressable
      onPress={() => {
        void (async () => {
          await submitSignup(auth, props.buildPayload(), {
            onPendingApproval: props.onGoToPending,
            onFailure: (message: string) => Alert.alert('Sign up failed', message),
          });
        })();
      }}
    >
      <Text>Sign up</Text>
    </Pressable>
  );
}
