<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default map view (OpenStreetMap via Leaflet)
    |--------------------------------------------------------------------------
    |
    | Used when opening the “Drop a pin” tab on Workforce setup. Adjust for
    | your primary operating region, or set WORKFORCE_DEFAULT_MAP_* in .env.
    |
    */

    'default_map_lat' => (float) env('WORKFORCE_DEFAULT_MAP_LAT', -27.4698),

    'default_map_lng' => (float) env('WORKFORCE_DEFAULT_MAP_LNG', 153.0251),

    'default_map_zoom' => (int) env('WORKFORCE_DEFAULT_MAP_ZOOM', 12),

];
