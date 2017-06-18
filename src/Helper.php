<?php

namespace Academe\GoogleApi;

//use Illuminate\Routing\Controller as BaseController;
use Academe\GoogleApi\Models\Authorisation;
//use Illuminate\Http\Request;
use Google_Client;
//use Session;
//use Config;
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
    public static function getApiClient()
    {
        $client = static::getAuthClient();

        $auth = Authorisation::currentUser()
            ->where('state', '=', Authorisation::STATE_ACTIVE)
            ->firstOrFail();

        $client->setAccessToken($auth->json_token);

        // Set a callback for storing the new token on renewal.
        // This may be called offline, so it needs to know the auth ID.
        //$client->setTokenCallback(...);

        return $client;
    }
}
