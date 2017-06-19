<?php

namespace Academe\GoogleApi\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Academe\GoogleApi\Models\Authorisation;
use Academe\GoogleApi\Helper;
use Google_Service_Analytics;
use Illuminate\Http\Request;
use Google_Client;
use Session;
use Config;
use Input;
use Auth;
use URL;

class GoogleApiController extends BaseController
{
    // The GET parameter name to pass teh final URL into the authorise route.
    const FINAL_URL_PARAM_NAME = 'final_url';

    // The scopes GET parameter name when requesting authorisation.
    const SCOPES_PARAM_NAME = 'scopes';
    const ADD_SCOPES_PARAM_NAME = 'add_scopes';

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

        // Clear any existing tokens.
        $auth->resetAuth();

        // Set the required scopes.
        // Passed in as a GET parameter, they will override the current scopes
        // for the authorisation, or default to the config setting.
        $scopes = (array)Input::get(static::SCOPES_PARAM_NAME, $auth->scopes);

        if (empty($scopes)) {
            $scopes = config('googleapi.default_scopes', []);
        }

        $auth->scopes = $scopes;

        // Additional scopes can be added during an authentication, adding to
        // what is already authorised.

        $add_scopes = (array)Input::get(static::ADD_SCOPES_PARAM_NAME, []);

        foreach($add_scopes as $add_scope) {
            $auth->addScope($add_scope);
        }

        // These two scopes are needed to get access to the Google
        // user ID. We need that for looking for duplicate OAuth
        // authorisations.

        $auth->addScope('openid');
        $auth->addScope('email');

        $auth->save();

        // Set an optional final redirect URL so we can get back to
        // where we requested the authorisation from.
        // CHECKME: can the final URL be passed to Google to send back at
        // the end, rather than keeping it in the session?

        $final_url = Input::get(static::FINAL_URL_PARAM_NAME, '');
        $request->session()->put($this->session_key_final_url, $final_url);

        // Now redirect to Google for authorisation.

        $client = Helper::getAuthClient($auth);

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
            ->IsAuthorising()
            ->firstOrFail();

        // The temporary token.
        $code = Input::get('code');

        // The temporary token.
        $final_redirect = $request->session()->get($this->session_key_final_url);

        $client = Helper::getAuthClient($auth);

        // TODO: check for errors here and set the final state approriately.
        $client->authenticate($code);

        // The token details will be an array.
        $token_details = $client->getAccessToken();

        // Store the token details back in the model.
        $auth->json_token = $token_details;

        // If the id_token is availabe, then decode it and save pertinent details.
        if (isset($token_details['id_token'])) {
            $auth->idToken = $client->verifyIdToken();
        }

        // Set active or maybe inactive if we hit an error fetching the
        // access token above.
        $auth->state = $auth::STATE_ACTIVE;

        $auth->save();

        // If no final URL was provided with the initial authorisation,
        // then use the default route or path.

        if (empty($final_redirect)) {
            $final_redirect = (
                Config::get('googleapi.default_final_route')
                ? route(Config::get('googleapi.default_final_route'))
                : url(Config::get('googleapi.default_final_path'))
            );
        } else {
            $request->session()->forget($this->session_key_final_url);
        }

        return redirect($final_redirect);
    }

    /**
     * Revoke the authorisation then redirect back.
     */
    public function revoke(Request $request)
    {
        // Users only have one authorisation at this time, so no
        // context needs to be given.
        $auth = Authorisation::currentUser()
            ->isActive()
            ->first();

        if ($auth) {
            $auth->revokeAuth();
            $auth->save();
        }

        return redirect()->back();
    }
}
