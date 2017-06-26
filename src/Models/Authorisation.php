<?php

namespace Academe\GoogleApi\Models;

/**
 * Holds Google API authorisations.
 * 
 * Data columns:
 *
 * + user_id - the laravel user who created/owns the authorisation.
 * + access_token - the current active token
 * + refresh_token - the long-term token for refreshing the auth token
 * + created_time - the (local) time the token was created (unix timestamp, integer)
 * + expires_in - the time after the created_time that the token will expire, in seconds
 * + state - auth, active, inactive
 * + scope - the current scope that has been authorised
 */

use Illuminate\Database\Eloquent\Model;
use Academe\GoogleApi\Helper;
use Google_Service_Exception;
use Google_Client;
use Auth;

class Authorisation extends Model
{
    // The status values.
    const STATE_AUTH       = 'auth';
    const STATE_ACTIVE     = 'active';
    const STATE_INACTIVE   = 'inactive';

    // The name if name of the authorisation provided.
    const DEFAULT_NAME = 'default';

    // The default table name.
    // Overridable by config 'googleapi.authorisation_table'
    protected $table = 'gapi_authorisations';

    // Default values.
    //protected $fillable = ['name'];
    protected $guarded = [];

    // These two scopes are needed to get access to the Google user ID.
    // We need that for looking for duplicate OAuth authorisations.

    protected $base_scopes = ['openid', 'email'];

    /**
     * Google_Client
     */
    protected $google_client;

    public function __construct(array $attributes = []) {
        parent::__construct($attributes);

        // Set the table name from configuration, so we can play nice
        // with the developer who may need a different name.
        $this->table = config('googleapi.authorisation_table');
    }

    /**
     * Set the Google API client.
     */
    public function setApiClient(Google_Client $client)
    {
        $this->google_client = $client;

        return $this;
    }

    /**
     * Get the Google API client.
     * @param bool $use_default Use the defaukt client from the Helper.
     */
    public function getApiClient($use_default = false)
    {
        $client = $this->google_client;

        if (empty($client) && $use_default) {
            $client = Helper::getApiClient($this);

            $this->setApiClient($client);
        }

        return $client;
    }

    /**
     * Only the record owned by a specified user ID.
     * Set the order too, in case additional records have got in.
     * Only use this for online access when the user is logged in.
     */
    public function scopeOwner($query, $userId)
    {
        return $query
            ->where('user_id', '=', $userId)
            ->orderBy('id');
    }

    /**
     * Fetch by name, defaulting to the default name if none supplied.
     */
    public function scopeName($query, $name)
    {
        return $query
            ->where('name', '=', (!empty($name) ? $name : static::DEFAULT_NAME));
    }

    /**
     * Only the record owned by the current user.
     */
    public function scopeCurrentUser($query)
    {
        return $this->owner(Auth::user()->id);
    }

    public function scopeIsAuthorising($query)
    {
        return $query->where('state', '=', Authorisation::STATE_AUTH);
    }

    public function scopeIsActive($query)
    {
        return $query->where('state', '=', static::STATE_ACTIVE);
    }

    public function scopeIsInactive($query)
    {
        return $query->where('state', '=', static::STATE_INACTIVE);
    }

    /**
     * Return a JSON token details for the API.
     * It will include the 
     */
    public function getJsonTokenAttribute()
    {
        $token = [
            'access_token' => $this->access_token,
            'token_type' => 'Bearer',
            'expires_in' => $this->expires_in,
        ];

        if ($this->refresh_token) {
            $token['refresh_token'] = $this->refresh_token;
        }

        $token['created'] = $this->created_time;

        return json_encode($token);
    }

    /**
     * Accepts an array or JSON string.
     */
    public function setJsonTokenAttribute($value)
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        // TODO: error if not an array.

        $this->attributes['access_token'] = array_get($value, 'access_token');
        $this->attributes['refresh_token'] = array_get($value, 'refresh_token');
        $this->attributes['created_time'] = array_get($value, 'created');

        // Here I am very very tempted to knock five minutes off the expires_in
        // period to make sure we don't operate close to the line. Having a token
        // expire in the middle of a bunch of processing is a pain to deal with,
        // so renew early to try to help there. That's a *configurable* five
        // minutes, of course. [DONE]

        $this->attributes['expires_in'] = array_get($value, 'expires_in', 3600)
            - Config('googleapi.expires_in_safety_margin', 0);
    }

    /**
     * If an idToken array is available then distribute relavent data from that
     * to appropriate columns. We are mainly interested in the Google user ID, so we
     * can tell if a user has [erroneously] authorised twice in this application.
     */
    public function setIdTokenAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['google_user_id'] = array_get($value, 'sub', $this->google_user_id);
            $this->attributes['google_email'] = array_get($value, 'email', $this->google_email);
        }
    }

    /**
     * Get the scopes, an array of string values.
     */
    public function getScopesAttribute()
    {
        if ($array = json_decode($this->scope, true)) {
            return $array;
        } else {
            return [];
        }
    }

    /**
     * Set the scopes, an array of string values.
     */
    public function setScopesAttribute(array $value)
    {
        $this->attributes['scope'] = json_encode($value);

        // Add the base scopes.
        $this->addScope($this->base_scopes);
    }

    /**
     * Add a single scope to the list we already have.
     * @param string|array $scopes
     */
    public function addScope($scopes)
    {
        foreach((array)$scopes as $scope) {
            if (! $this->hasScope($scope)) {
                $this->scopes = array_merge($this->scopes, [$scope]);
            }
        }
    }

    public function hasScope($scope)
    {
        return in_array($scope, $this->scopes);
    }

    /**
     * Initialise the record for a new authorisation.
     */
    public function initAuth()
    {
        $this->state = static::STATE_AUTH;
        $this->access_token = null;
        $this->refresh_token = null;
        $this->created_time = null;
        $this->expires_in = null;
    }

    /**
     * Get the user that owns this authorisation.
     */
    public function user()
    {
        return $this->belongsTo(config('googleapi.user_model'));
    }

    /**
     * Checks if the authorisation is labelled as active.
     */
    public function isActive()
    {
        return $this->state === static::STATE_ACTIVE;
    }

    /**
     * Revoke the record for a new authorisation.
     * TODO: Once revoked, we should also go through other authorisations
     * for this Laravel user and Google account to revoke those too.
     */
    public function revokeAuth()
    {
        if ($this->isActive() && $this->access_token) {
            // Start by revoking the access token with Google.
            // See SO example:
            // https://stackoverflow.com/questions/31515231/revoke-google-access-token-in-php
            // Q: Does this remove the refresh token too?

            $client = $this->getApiClient(true);

            // Revoke the token with Google.
            $client->revokeToken($this->access_token);

            // Now remove details of the token we have stored.
            // CHECKME: should we keep the refresh token? Can that possibly still be of use?
            $this->state = static::STATE_INACTIVE;
            $this->access_token = null;
            $this->refresh_token = null;
            $this->created_time = null;
            $this->expires_in = null;

            // Save the rusultant state.
            $this->save();

            return true;
        }

        return false;
    }

    /**
     * Refresh a token.
     */
    public function refreshToken()
    {
        // A refresh token is needed to renew.
        if (empty($this->refresh_token)) {
            return false;
        }

        $client = $this->getApiClient(true);

        // Renew the token with Google.
        $client->refreshToken($this->refresh_token);

        // Capture the renewed token details: access token, expirey etc. are
        // all set at once.
        $this->json_token = $client->getAccessToken();

        // Save the rusultant state.
        $this->save();

        return true;
    }

    /**
     * Tests a token to see that it still works.
     */
    public function testToken()
    {
        // If not marked as active, then don't even attempt to access the
        // remote service.

        if (! $this->isActive()) {
            throw new Exception('Authorisation is marked as inactive.');
        }

        $client = $this->getApiClient(true);

        $oauth2 = new \Google_Service_Oauth2($client);

        try {
            $userinfo = $oauth2->userinfo->get();
        } catch (Google_Service_Exception $e) {
            // If the error is a 401, we will try renewing the token manually,
            // just once, to see if that fixes the problem.

            try {
                if ($e->getCode() == 401 && $this->refreshToken()) {
                    // Try accessing the API again.

                    $userinfo = $oauth2->userinfo->get();

                    return true;
                }
            } catch (Google_Service_Exception $e) {
                // Still in error. Mark this authorisation as inactive.

                $this->state = static::STATE_INACTIVE;
                $this->save();
            }

            // Renewal did not work, so throw the reason.
            throw $e;
        }

        return true;
    }
}
