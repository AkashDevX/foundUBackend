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

    'geofence_exit_extra_meters' => (int) env('TIME_CLOCK_GEOFENCE_EXIT_EXTRA_METERS', 50),

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

];
