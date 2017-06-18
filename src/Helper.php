<?php

namespace Academe\GoogleApi;

//use Illuminate\Routing\Controller as BaseController;
use Academe\GoogleApi\Models\Authorisation;
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
     * TODO: Move this to a helper class.
     */
    public static function getAuthClient()
    {
        $client = new Google_Client();

        $client->setApplicationName('Application TBC');
        $client->setClientId(config('googleapi.client_id'));
        $client->setClientSecret(config('googleapi.client_secret'));

        // Or just analytics.readonly?
        // TODO: make configurable. Maybe even support incremental authorisation too.
        // The scopes should allow the site to request any APIs it likes.
        $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);

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

        $client = static::getAuthClient();

        $client->setAccessToken($auth->json_token);

        // Set a callback for storing the new token on renewal.
        // This may be called offline, so it needs to know the auth ID.
        $client->setTokenCallback(function($cacheKey, $accessToken) use ($auth) {
            // Refresh from the database. Not sure if this is necessary or desirable.
            $auth = Authorisation::find($auth->id);

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
     */
    public static function getUserAuth($userId)
    {
        return Authorisation::User($userId)->first();
    }

    public static function getCurrentUserAuth()
    {
        return Authorisation::CurrentUser()->first();
    }

    /**
     * Cancel an authorisation by ID.
     */
    public static function cancelAuth($auth_id)
    {
        $auth = Authorisation::where('id', '=', $auth_id)
            ->where('state', '=', Authorisation::STATE_ACTIVE)
            ->first();

        if ($auth) {
            $auth->cancelAuth();
            $auth->save();

            return true;
        }

        return false;
    }

    /**
     * Explicitly refresh the access token for an authorisation.
     * TODO: think about this one. We need a client and an auth ID.
     * Will only work if there is a refresh token.
     */
    public static function refreshToken($auth_id)
    {
        $auth = Authorisation::where('id', '=', $auth_id)
            ->whereIn('state', [Authorisation::STATE_ACTIVE, Authorisation::STATE_INACTIVE])
            ->first();

        if ($auth) {
            $auth->save();

            return true;
        }

        return false;
    }
}
