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

    // Parameter name for the name of the authotisation.
    const AUTH_NAME_PARAM_NAME = 'name';

    // The scopes GET parameter name when requesting authorisation.
    const SCOPES_PARAM_NAME = 'scopes';
    const ADD_SCOPES_PARAM_NAME = 'add_scopes';

    /**
     * The session key for the final redirect URL after authorisation.
     */
    protected $session_key_final_url = 'gapi_auth_final_url';

    /**
     * The session key for the final redirect URL after authorisation.
     */
    protected $session_key_name = 'gapi_auth_name';

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
     * GET parameters:
     * + name       - The name of the authorisation.
     * + final_url  - Where to take the user after authorisation.
     * + scopes     - Array of scopes to use.
     * + add_scopes - Array of additional scopes to add to what is there.
     */
    public function authorise(Request $request)
    {
        // Get or create a new model instance to follow the authoriation
        // through.
        // With just one set of credentials, each user can have only one
        // active authorisation.

        $name = Input::get(static::AUTH_NAME_PARAM_NAME, Authorisation::DEFAULT_NAME);

        // The current user is the creator/owner.

        $auth = Authorisation::firstOrNew([
            'user_id' => Auth::user()->id,
            'name' => $name,
        ]);

        $name = $auth->name;

        // Clear any existing tokens in this record amd prepare
        // it for the callback.
        $auth->initAuth();

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

        if ($add_scopes = (array)Input::get(static::ADD_SCOPES_PARAM_NAME, [])) {
            $auth->addScope($add_scopes);
        }

        $auth->save();

        // Set an optional final redirect URL so we can get back to
        // where we requested the authorisation from.

        $final_url = Input::get(static::FINAL_URL_PARAM_NAME, '');
        $request->session()->put($this->session_key_final_url, $final_url);

        // Send the name through to the callback, so we can find this instance.

        $request->session()->put($this->session_key_name, $name);

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
        $name = $request->session()->get($this->session_key_name);

        // Get the authorisation record waitng for the callback.
        $auth = Authorisation::currentUser()
            ->name($name)
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
        }

        $request->session()->forget($this->session_key_name);
        $request->session()->forget($this->session_key_final_url);

        return redirect($final_redirect);
    }

    /**
     * Revoke the authorisation then redirect back.
     * We really should try to revoke the token remotely with
     * Google first, so we havemore control over which tokens are
     * active. If we leae too many tokens just hanging there, we
     * could end up with the older ones (possibly the wrong ones)
     * being automatically revoked by Google.
     */
    public function revoke(Request $request)
    {
        // Check if the name of the instance to revoke has been supplied.
        $name = Input::get(
            static::AUTH_NAME_PARAM_NAME,
            Authorisation::DEFAULT_NAME
        );

        // Get the authorisation of the givem name for the current user.
        $auth = Authorisation::currentUser()
            ->name($name)
            ->isActive()
            ->first();

        if ($auth) {
            $auth->revokeAuth();
            $auth->save();
        }

        return redirect()->back();
    }
}
