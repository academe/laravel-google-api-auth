<?php

return [
    // ********************************************************  //
    // Get these values from https://console.developers.google.com
    // Be sure to enable the Analytics API
    // ********************************************************  //

    'client_id'     => env('GA_API_CLIENT_ID'),
    'client_secret' => env('GA_API_CLIENT_SECRET'),

    'authorisation_table' => 'gapi_authorisations',

    // The route will be checked first.
    // This defines teh default final URL after athorisation if
    // no specific URL is given.

    'default_final_route' => null,
    'default_final_path' => '/',
];
