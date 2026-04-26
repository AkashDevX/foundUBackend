<?php

return [

    /*
    |--------------------------------------------------------------------------
    | How the API identifies which tenant registry row (master DB) applies
    |--------------------------------------------------------------------------
    |
    | Every request that should run against a company database must specify
    | which company. Typically the mobile app sends this HTTP header so the
    | backend loads the row from the master DB, then routes CRUD to that
    | company's database connection.
    |
    */

    'identifier_header' => env('TENANT_IDENTIFIER_HEADER', 'X-Company-Slug'),

    /*
    |--------------------------------------------------------------------------
    | Connections that receive per-tenant schema (migrations)
    |--------------------------------------------------------------------------
    |
    | Each value must match a key in config/database.php connections.
    |
    */

    'tenant_migration_connections' => [
        'tenant_bluegreen',
        'tenant_constructconcepts',
        'tenant_aidandable',
    ],

];
