<?php

// Start the Google API authorisation process.
// Save a few details then send the user to Google.

Route::post('oauth2authorise', 'GoogleApiController@authorise')
    ->name('academe_gapi_authorise');

// Simple GET catcher for initialising an authorisation.
// Provides a simple form with a single button.

Route::get('oauth2authorise', 'GoogleApiController@authoriseForm');

// The "redirect" route, i.e. the return from Google after authorising.

Route::get('oauth2callback', 'GoogleApiController@callback')
    ->name('academe_gapi_callback');

// Log out of Google.
// Revoke the tokens with Google then discard the local access tokens.

Route::delete('oauth2revoke', 'GoogleApiController@revoke')
    ->name('academe_gapi_revoke');

// Simple GET catcher for revoking.
// Provides a simple form with a single button.

Route::get('oauth2revoke', 'GoogleApiController@revokeForm');

