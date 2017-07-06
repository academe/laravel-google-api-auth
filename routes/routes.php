<?php

/**
 * A web route, since the sessions need starting up.
 * FIXME: although convenience, these should probably not be GET routes.
 * authorise and revoke should be POST, or even POST and DELETE respecively,
 * and have CSRF protection. 'auth' middleware here would also good.
 * Strictly, authorise and revoke routes change the state of the system, so
 * they must not be GET routes.
 * The documentation needs to show how these can be overridden or disabled.
 */

Route::group(['middleware' => 'web'], function() {

    // Start the Google API authorisation process.
    // Save a few details then send the user to Google.

    Route::post('gapi/oauth2authorise', 'Academe\GoogleApi\Controllers\GoogleApiController@authorise')
        ->name('academe_gapi_authorise');

    // Simple GET catcher for initialising an authorisation.
    // Provides a simple form with a single button.

    Route::get('gapi/oauth2authorise', 'Academe\GoogleApi\Controllers\GoogleApiController@authoriseForm');

    // The "redirect" route, i.e. the return from Google after authorising.

    Route::get('gapi/oauth2callback', 'Academe\GoogleApi\Controllers\GoogleApiController@callback')
        ->name('academe_gapi_callback');

    // Log out of Google.
    // Revoke the tokens with Google then discard the local access tokens.

    Route::delete('gapi/oauth2revoke', 'Academe\GoogleApi\Controllers\GoogleApiController@revoke')
        ->name('academe_gapi_revoke');

    // Simple GET catcher for revoking.
    // Provides a simple form with a single button.

    Route::get('gapi/oauth2revoke', 'Academe\GoogleApi\Controllers\GoogleApiController@revokeForm');

});
