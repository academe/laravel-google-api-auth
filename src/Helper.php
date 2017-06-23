<?php

namespace Academe\GoogleApi;

//use Illuminate\Routing\Controller as BaseController;
use Academe\GoogleApi\Models\Authorisation;
use Google_Service_Analytics;
use Google_Client;
//use Session;
//use Input;
//use Auth;
//use URL;

class Helper
{
    /**
     * Get an instance of teh Google API client.
     * This is just the base credentials. To use the API we also need to
     * set $client->setAccessToken($auth->json_token);
     * Also we need to register a callback to catch token renewals so they
     * can be saved.
     * TODO: provide a hook to allow any additional parameters to be set on
     * the API client, as there are plenty of useful things that can be added,
     * such as authorisation prompts and login hints.
     */
    public static function getAuthClient(Authorisation $auth)
    {
        $client = new Google_Client();

        $client->setApplicationName(config('googleapi.application_name'));
        $client->setClientId(config('googleapi.client_id'));
        $client->setClientSecret(config('googleapi.client_secret'));

        // Or just analytics.readonly?
        // TODO: make configurable. Maybe even support incremental authorisation too.
        // The scopes should allow the site to request any APIs it likes.
        // Can also use addScope() to add them one at a time.
        // If the scope of the auth changes (and it could if this is dynamic) then it
        // may need re-authorising. So perhaps we need to keep a list of scopes
        // in the authorisation record so we know when the scope is being extended.
        // In fact, scopes could be different for each suer authurisation, so we definitely
        // need to store them and reuse them each time we access the API or (perhaps)
        // just refresh the token.

        $client->setScopes($auth->scopes);

        // With multiple authorisations per user, this URL will need to contain
        // some additional context to identify which authorisation is being processed.
        $client->setRedirectUri(route('academe_gapi_callback'));

        // These are necessary if offline access or auto-renewing of access tokens is needed.
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');

        return $client;
    }

    /**
     * Get an instance of a client ready to use for accessing the Google services.
     */
    public static function getApiClient(Authorisation $auth)
    {
        // TODO: the authorisation record must be active before we can use it.

        $client = static::getAuthClient($auth);

        $client->setAccessToken($auth->json_token);

        // Set a callback for storing the new token on renewal.
        // This may be called offline, so it needs to know the auth ID.
        $client->setTokenCallback(function($cacheKey, $accessToken) use ($auth) {
            // Refresh from the database. Not sure if this is necessary or desirable.
            //$auth = Authorisation::find($auth->id);

            // Set the new access token.
            $auth->access_token = $accessToken;

            // Set the new token created time.
            // We really need to get this from the Google API Client, but it is
            // not obviously available. It may be in the cache, which is why the
            // cacheKey is provided.
            // We also assume the expires_in has not changed since the initial
            // authorisation.
            $auth->created_time = time();

            $auth->save();
        });

        return $client;
    }

    /**
     * Get the authorisation for a user, defaulting to the current user.
     * TODO: make it selectable by name or ID.
     */
    public static function getUserAuth($userId, $name = null)
    {
        return Authorisation::User($userId)->name($name)->first();
    }

    public static function getCurrentUserAuth($name = null)
    {
        return Authorisation::CurrentUser()->name($name)->first();
    }

    /**
     * Explicitly refresh the access token for an authorisation.
     * Will only work if there is a refresh token.
     */
    public static function refreshToken(Authorisation $auth)
    {
        //$auth = Authorisation::where('id', '=', $auth_id)
        //    ->whereIn('state', [Authorisation::STATE_ACTIVE, Authorisation::STATE_INACTIVE])
        //    ->first();

        if ($auth) {
            $auth->save();

            return true;
        }

        return false;
    }
}
