<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Geofence radius fallback (meters)
    |--------------------------------------------------------------------------
    |
    | Used only when a work location has no radius of its own. Each work
    | location stores geofence_radius_meters (admin: Organization setup →
    | Work locations). Clock-in and automatic clock-out use that site radius.
    |
    */

    'geofence_radius_meters' => (int) env('TIME_CLOCK_GEOFENCE_RADIUS_METERS', 300),

    /*
    |--------------------------------------------------------------------------
    | Clock-in grace period
    |--------------------------------------------------------------------------
    |
    | How many minutes before and after the scheduled shift start an employee
    | may clock in without an exception. A 20-minute grace on a 9:00 AM shift
    | allows punches from 8:40 AM through 9:20 AM.
    |
    | outside_grace_policy:
    | - prevent: block the punch
    | - exception: block the punch until an admin clears the exception
    |
    | Organizations can override both values from the admin portal once the
    | clock-in grace tables exist. These env values are the fallback.
    |
    */

    'clock_in_grace_minutes' => (int) env('TIME_CLOCK_CLOCK_IN_GRACE_MINUTES', 20),

    'clock_in_outside_grace_policy' => env('TIME_CLOCK_OUTSIDE_GRACE_POLICY', 'exception'),

    /*
    |--------------------------------------------------------------------------
    | Meal-break window
    |--------------------------------------------------------------------------
    |
    | Employees take their meal break only inside this part of the shift, not
    | at any time. The defaults follow a common Australian workplace rule: a
    | meal break is required when the shift is longer than 5 hours, and it is
    | taken from the 4th hour through the 6th hour.
    |
    | A 6:00 AM shift then reads: "Please take your break between 10:00 AM and
    | 12:00 PM." Organizations can override these values from Time clock records
    | once the break-window columns exist. These env values are the fallback.
    |
    | - start_minutes: window opens this many minutes after shift start (240 = 4h)
    | - end_minutes: window closes this many minutes after shift start (360 = 6h)
    | - required_after_minutes: shifts this long or shorter have no meal break
    | - reminder_lead_minutes: notify the employee this long before the window opens
    |
    */

    'break_window' => [
        'enabled' => (bool) env('TIME_CLOCK_BREAK_WINDOW_ENABLED', true),
        'start_minutes' => (int) env('TIME_CLOCK_BREAK_WINDOW_START_MINUTES', 240),
        'end_minutes' => (int) env('TIME_CLOCK_BREAK_WINDOW_END_MINUTES', 360),
        'required_after_minutes' => (int) env('TIME_CLOCK_BREAK_REQUIRED_AFTER_MINUTES', 300),
        'reminder_lead_minutes' => (int) env('TIME_CLOCK_BREAK_REMINDER_LEAD_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto clock-out exit hysteresis (meters)
    |--------------------------------------------------------------------------
    |
    | After a successful clock-in, the employee must leave this many meters
    | beyond the normal geofence radius before auto clock-out is accepted.
    | This absorbs normal GPS drift near the boundary.
    |
    */

    'geofence_exit_extra_meters' => (int) env('TIME_CLOCK_GEOFENCE_EXIT_EXTRA_METERS', 0),

    /*
    |--------------------------------------------------------------------------
    | Max GPS accuracy buffer (meters)
    |--------------------------------------------------------------------------
    |
    | Device-reported accuracy expands the effective radius up to this cap
    | when deciding whether a punch is inside/outside the site.
    |
    */

    'geofence_accuracy_buffer_cap_meters' => (int) env('TIME_CLOCK_GEOFENCE_ACCURACY_BUFFER_CAP_METERS', 100),

    /*
    |--------------------------------------------------------------------------
    | Server-side automatic clock-out
    |--------------------------------------------------------------------------
    |
    | The mobile geofence monitor only runs while the app is alive. This
    | scheduled safety net closes open shifts on the server so employees are
    | clocked out even when their phone is off, restarted, or the app was
    | swiped away. Runs from the `time-clock:auto-clock-out` command.
    |
    | - shift_end_grace_minutes: wait this long after the scheduled shift end
    |   before auto clocking out (covers slightly-late finishes).
    | - max_session_hours: hard cap — close any session open longer than this,
    |   even when no scheduled shift end can be resolved.
    |
    */

    'auto_clock_out' => [
        'enabled' => (bool) env('TIME_CLOCK_AUTO_CLOCK_OUT_ENABLED', true),
        'shift_end_grace_minutes' => (int) env('TIME_CLOCK_AUTO_CLOCK_OUT_GRACE_MINUTES', 10),
        'max_session_hours' => (int) env('TIME_CLOCK_MAX_SESSION_HOURS', 16),
    ],

    /*
    |--------------------------------------------------------------------------
    | In-shift location tracking
    |--------------------------------------------------------------------------
    |
    | Mid-shift GPS pings (separate from punch events). Idle detection flags
    | low movement inside the geofence — it does NOT auto clock-out.
    |
    */

    'location_ping_min_interval_seconds' => (int) env('TIME_CLOCK_LOCATION_PING_MIN_INTERVAL_SECONDS', 120),

    'idle_window_minutes' => (int) env('TIME_CLOCK_IDLE_WINDOW_MINUTES', 30),

    'idle_max_displacement_meters' => (int) env('TIME_CLOCK_IDLE_MAX_DISPLACEMENT_METERS', 40),

    /*
    | Samples with worse accuracy than this are ignored for idle detection
    | (still stored for the trail / live map).
    */
    'idle_min_usable_accuracy_meters' => (int) env('TIME_CLOCK_IDLE_MIN_USABLE_ACCURACY_METERS', 80),

    /*
    | After an idle alert is opened/acknowledged, wait this long before opening
    | another for the same clock-in session.
    */
    'idle_alert_cooldown_minutes' => (int) env('TIME_CLOCK_IDLE_ALERT_COOLDOWN_MINUTES', 45),

    /*
    | Minimum usable samples inside the idle window before an alert can fire.
    */
    'idle_min_samples' => (int) env('TIME_CLOCK_IDLE_MIN_SAMPLES', 3),

];
