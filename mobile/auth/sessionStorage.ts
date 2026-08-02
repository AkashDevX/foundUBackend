/**
 * Token storage — ONLY written after POST /login succeeds (never after /register).
 * Prefer expo-secure-store for the token in production; AsyncStorage shown for compatibility.
 *
 * npm i @react-native-async-storage/async-storage
 * # or: npx expo install expo-secure-store
 */

import AsyncStorage from '@react-native-async-storage/async-storage';

const KEY_TOKEN = 'foundu_bearer_token';
/** Remembers user submitted registration on this device (optional UX: show “waiting for approval”). */
const KEY_AWAITING_APPROVAL = 'foundu_awaiting_approval_email';

export async function saveBearerToken(token: string): Promise<void> {
  await AsyncStorage.setItem(KEY_TOKEN, token);
}

export async function clearBearerToken(): Promise<void> {
  await AsyncStorage.removeItem(KEY_TOKEN);
}

export async function getBearerToken(): Promise<string | null> {
  return AsyncStorage.getItem(KEY_TOKEN);
}

export async function setAwaitingApprovalEmail(email: string | null): Promise<void> {
  if (email === null || email === '') {
    await AsyncStorage.removeItem(KEY_AWAITING_APPROVAL);
    return;
  }
  await AsyncStorage.setItem(KEY_AWAITING_APPROVAL, email);
}

export async function getAwaitingApprovalEmail(): Promise<string | null> {
  return AsyncStorage.getItem(KEY_AWAITING_APPROVAL);
}
