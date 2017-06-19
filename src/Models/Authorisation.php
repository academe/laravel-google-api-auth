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
use Auth;

class Authorisation extends Model
{
    // The status values.
    const STATE_AUTH       = 'auth';
    const STATE_ACTIVE     = 'active';
    const STATE_INACTIVE   = 'inactive';

    // The default table name.
    // Overridable by config 'googleapi.authorisation_table'
    protected $table = 'gapi_authorisations';

    public function __construct() {
        parent::__construct();

        // Set the table name from configuration, so we can play nice
        // with the developer who may need a different name.
        $this->table = config('googleapi.authorisation_table');
    }

    /**
     * Only the record owned by a specified user ID.
     * Set the order too, in case additional records have got in.
     * Only use this for online access when the user is logged in.
     */
    public function scopeUser($query, $userId)
    {
        return $query
            ->where('user_id', '=', $userId)
            ->orderBy('id');
    }

    /**
     * Only the record owned by the current user.
     */
    public function scopeCurrentUser($query)
    {
        return $this->user(Auth::user()->id);
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
    }

    /**
     * Add a single scope to the list we already have.
     */
    public function addScope($scope)
    {
        if (! $this->hasScope($scope)) {
            $this->scopes = array_merge($this->scopes, [$scope]);
        }
    }

    public function hasScope($scope)
    {
        return in_array($scope, $this->scopes);
    }

    /**
     * Reset the record for a new authorisation.
     */
    public function resetAuth()
    {
        $this->state = static::STATE_AUTH;
        $this->access_token = null;
        $this->refresh_token = null;
        $this->created_time = null;
        $this->expires_in = null;
    }

    /**
     * Revoke the record for a new authorisation.
     * See SO example to revoke the token with Google:
     * https://stackoverflow.com/questions/31515231/revoke-google-access-token-in-php
     */
    public function revokeAuth()
    {
        if ($this->isActive()) {
            $this->state = static::STATE_INACTIVE;
            $this->access_token = null;
            $this->refresh_token = null;
            $this->created_time = null;
            $this->expires_in = null;

            return true;
        }

        return false;
    }
}
