import type { WorkAssignment } from './types';

export type TimeClockEntryPayload = {
  id: number;
  event_type: 'clock_in' | 'break_start' | 'break_end' | 'clock_out';
  clocked_at: string | null;
  device_latitude: number | null;
  device_longitude: number | null;
  device_accuracy_meters: number | null;
  work_location_id: number | null;
  expected_latitude: number | null;
  expected_longitude: number | null;
  distance_from_site_meters: number | null;
  allowed_radius_meters: number;
  within_geofence: boolean;
  punch_source?: 'manual' | 'auto_geofence_exit';
  department_id: number | null;
  shift_id: number | null;
  comment?: string | null;
};

export type TimeClockBreakPayload = {
  started_at: string | null;
  ended_at: string | null;
  duration_seconds: number | null;
  is_open: boolean;
};

export type TimeClockStatus = {
  is_clocked_in: boolean;
  is_on_break: boolean;
  can_clock_in: boolean;
  can_clock_out: boolean;
  can_break_in: boolean;
  can_break_out: boolean;
  geofence_radius_meters: number;
  open_session: {
    entry_id: number;
    clocked_in_at: string | null;
    work_location_id: number | null;
    within_geofence: boolean;
    geofence_latitude?: number | null;
    geofence_longitude?: number | null;
    allowed_radius_meters?: number | null;
    break_started_at?: string | null;
    breaks?: TimeClockBreakPayload[];
    total_break_seconds?: number;
  } | null;
  last_event: TimeClockEntryPayload | null;
  assignment_ready: boolean;
  assignment_issue:
    | 'no_work_location_assigned'
    | 'work_location_missing_coordinates'
    | null;
  shift_issue?: 'no_scheduled_shift_today' | null;
  scheduled_shift: {
    start_time: string;
    end_time: string;
    start_label: string;
    end_label: string;
  } | null;
  work_assignment: WorkAssignment | null;
};

export type TimeClockStatusBody = {
  time_clock: TimeClockStatus;
};

export type TimeClockPunchBody = {
  message: string;
  entry: TimeClockEntryPayload;
  time_clock: TimeClockStatus;
};

export type TimeClockErrorBody = {
  message: string;
  code:
    | 'no_work_location_assigned'
    | 'work_location_missing_coordinates'
    | 'already_clocked_in'
    | 'not_clocked_in'
    | 'already_on_break'
    | 'not_on_break'
    | 'outside_geofence'
    | 'still_within_geofence'
    | 'no_scheduled_shift_today'
    | 'work_location_not_found';
  details: Record<string, unknown>;
};

export type DeviceCoordinates = {
  latitude: number;
  longitude: number;
  accuracy_meters?: number | null;
};
