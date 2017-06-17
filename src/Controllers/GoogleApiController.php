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
     * Start the Google API OAuth 2.0 authorisation process.
     */
    public function authorise(Request $request)
    {
        // Create a new model instance to follow the authoriation through.

        $auth = new Authorisation();

        // The current user is the creator/owner.
        $auth->user_id = Auth::user()->id;

        $auth->save();

        // Save the ID in the session to get us back to this model instance.

        $request->session()->put($this->session_key_auth_id, $auth->id);

        // Set an optional final redirect URL so we can get back to
        // where we requested the authorisation from.
        // CHECKME: can the final URL be passed to Google to send back at
        // the end, rather than keeping it in the session?

        $final_url = Input::get('final_url', '');

        $request->session()->put($this->session_key_final_url, $final_url);

        // Now redirect to Google for authorisation.
        // TODO: move this into some kind of service for reusing.

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

        $authUrl = $client->createAuthUrl();

        return redirect($authUrl);
    }

    /**
     * This is where Google returns the user with authorisation.
     * The keys are exchanged for tokens here.
     */
    public function callback(Request $request)
    {
    }
}
