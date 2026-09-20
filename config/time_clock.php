<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Geofence radius (meters)
    |--------------------------------------------------------------------------
    |
    | Mobile clock-in/out must be within this distance of the assigned work
    | location coordinates (Haversine great-circle distance).
    |
    */

    'geofence_radius_meters' => (int) env('TIME_CLOCK_GEOFENCE_RADIUS_METERS', 300),

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
