<?php

namespace Academe\GoogleApi\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Academe\GoogleApi\Models\Authorisation;
use Academe\GoogleApi\Helper;
use Illuminate\Http\Request;
use Google_Client;
use Session;
use Config;
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

        $auth->save();

        // Save the ID in the session so the callback knows where to look.

        $request->session()->put($this->session_key_auth_id, $auth->id);

        // Set an optional final redirect URL so we can get back to
        // where we requested the authorisation from.
        // CHECKME: can the final URL be passed to Google to send back at
        // the end, rather than keeping it in the session?

        $final_url = Input::get($auth::FINAL_URL_PARAM_NAME, '');

        $request->session()->put($this->session_key_final_url, $final_url);

        // Now redirect to Google for authorisation.

        $client = Helper::getAuthClient();

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

        // The temporary token.
        $final_redirect = $request->session()->get($this->session_key_final_url);

        $client = Helper::getAuthClient();

        // TODO: check for errors here and set the final state approriately.
        $client->authenticate($code);

        // The token details will be an array.
        $token_details = $client->getAccessToken();

        // Store the token details back in the model.
        $auth->json_token = $token_details;

        //
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
     * Cancel the authorisation then redirect back.
     */
    public function cancel(Request $request)
    {
        $auth = Authorisation::currentUser()
            ->where('state', '=', Authorisation::STATE_ACTIVE)
            ->first();

        if ($auth) {
            $auth->cancelAuth();
            $auth->save();
        }

        return redirect()->back();
    }
}
