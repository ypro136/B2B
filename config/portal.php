<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Portal
    |--------------------------------------------------------------------------
    |
    | The portal used when the request host does not match a configured
    | portal domain (for example while running on localhost or in tests).
    |
    */
    'default' => env('PORTAL_TYPE', 'admin'),

    /*
    |--------------------------------------------------------------------------
    | Active Portal
    |--------------------------------------------------------------------------
    |
    | Overwritten by the ResolvePortal middleware on every request.
    |
    */
    'type' => env('PORTAL_TYPE', 'admin'),

    /*
    |--------------------------------------------------------------------------
    | Portal Domains
    |--------------------------------------------------------------------------
    */
    'admin_domain' => env('ADMIN_DOMAIN', 'admin.localhost'),
    'company_domain' => env('COMPANY_DOMAIN', 'company.localhost'),

    'domains' => [
        'admin' => env('ADMIN_DOMAIN', 'admin.localhost'),
        'company' => env('COMPANY_DOMAIN', 'company.localhost'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Portals
    |--------------------------------------------------------------------------
    */
    'supported' => ['admin', 'company'],
];
