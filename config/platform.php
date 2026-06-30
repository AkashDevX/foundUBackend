<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CruLynk platform controller (master registry)
    |--------------------------------------------------------------------------
    |
    | Mobile routes that create platform-scoped records (e.g. new organisation
    | access requests) must send this header. Requests are stored on the master
    | DB and shown only in the CruLynk platform portal — never in tenant org UI.
    |
    */

    'identifier_header' => env('PLATFORM_IDENTIFIER_HEADER', 'X-Platform-Slug'),

    'slug' => env('PLATFORM_SLUG', 'crulynk'),

];
