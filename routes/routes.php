<?php

// A web route, since the sessions need starting up.

Route::group(['middleware' => 'web'], function() {

    // Start the Google API authorisation process.
    // Save a few details then send the user to Google.

    Route::get('gapi/authorise', 'Academe\GoogleApi\Controllers\GoogleApiController@authorise')
        ->name('academe_gapi_authorise');

    // The "redirect" route, i.e. the rturn from Google.
    Route::get('gapi/callback', 'Academe\GoogleApi\Controllers\GoogleApiController@authorise')
        ->name('academe_gapi_callback');

});
