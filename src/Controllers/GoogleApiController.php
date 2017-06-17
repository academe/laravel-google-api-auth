<?php

namespace Academe\GoogleApi\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Academe\GoogleApi\Models\Authorisation;
use Illuminate\Http\Request;
use Google_Client;
use Session;
use Input;
use Auth;
use URL;

class GoogleApiController extends BaseController
{
    /**
     * The session key for the ID of the authorisation being processed.
     */
    protected $session_key_auth_id = 'gapi_auth_session_id';

    /**
     * The session key for the final redirect URL after authorisation.
     */
    protected $session_key_final_url = 'gapi_auth_final_url';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // The Google API auth must be owned by a user.
        // We don't want them unattached and ending up with the
        // wrong user.

        $this->middleware('auth');
    }

    /**
     * Get an instance of teh Google API client.
     * This is just the base credentials. To use the API we also need to
     * set $client->setAccessToken($auth->json_token);
     * Also we need to register a callback to catch token renewals so they
     * can be saved.
     */
    private function getClient()
    {
        $client = new Google_Client();
        $client->setApplicationName('Application TBC');
        $client->setClientId(config('googleapi.client_id'));
        $client->setClientSecret(config('googleapi.client_secret'));

        // Or just analytics.readonly?
        // TODO: make configurable. Maybe even support incremental authorisation too.
        // The scopes should allow the site to request any APIs it likes.
        $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);

        $client->setRedirectUri(URL::route('academe_gapi_callback'));

        // These are necessary if offline access or auto-renewing of access tokens is needed.
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');

        return $client;
    }

    /**
     * Start the Google API OAuth 2.0 authorisation process.
     */
    public function authorise(Request $request)
    {
        // Get or create a new model instance to follow the authoriation
        // through.
        // With just one set of credentials, each user can have only one
        // active authorisation.

        $auth = Authorisation::currentUser()->first() ?: new Authorisation();

        // The current user is the creator/owner.
        $auth->user_id = Auth::user()->id;

        // Clear any existing tokens. TODO: move this to the model.
        $auth->state = $auth::STATE_AUTH;
        $auth->access_token = null;
        $auth->refresh_token = null;

        $auth->save();

        // Save the ID in the session so the callback knows where to look.

        $request->session()->put($this->session_key_auth_id, $auth->id);

        // Set an optional final redirect URL so we can get back to
        // where we requested the authorisation from.
        // CHECKME: can the final URL be passed to Google to send back at
        // the end, rather than keeping it in the session?

        $final_url = Input::get('final_url', '');

        $request->session()->put($this->session_key_final_url, $final_url);

        // Now redirect to Google for authorisation.

        $client = $this->getClient();

        $authUrl = $client->createAuthUrl();

        return redirect($authUrl);
    }

    /**
     * This is where Google returns the user with authorisation.
     * The keys are exchanged for tokens here.
     */
    public function callback(Request $request)
    {
        // Get the authorisation record waitng for the callback.
        $auth = Authorisation::currentUser()
            ->where('id', '=', $request->session()->get($this->session_key_auth_id))
            ->where('state', '=', Authorisation::STATE_AUTH)
            ->firstOrFail();

        // The temporary token.
        $code = Input::get('code');

        $client = $this->getClient();

        // TODO: check for errors here and set the final state approriately.
        $client->authenticate($code);

        // The token details will be an array.
        $token_details = $client->getAccessToken();

        // Store the token details back in the model.
        $auth->json_token = $token_details;

        //
        $auth->state = $auth::STATE_ACTIVE;

        $auth->save();

        return redirect(url('home'));
    }
}
