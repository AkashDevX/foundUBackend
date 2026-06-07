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

    'geofence_radius_meters' => (int) env('TIME_CLOCK_GEOFENCE_RADIUS_METERS', 100),

];
