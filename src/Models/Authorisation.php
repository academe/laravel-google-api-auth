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
 * + created_time - the (local) time the token was created
 * + expires_in - the time after the created_time that the token will expire
 * + state - TBC
 * + TBC some context details for the authorisation give, user details etc.
 */

use Illuminate\Database\Eloquent\Model;
use Auth;

class Authorisation extends Model
{
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
     * Only records owned by the current user.
     * Set the order too, in case additional records have got in.
     */
    public function scopeCurrentUser($query, $user_id = null)
    {
        return $query
            ->where('user_id', '=', $user_id ?: Auth::user()->id)
            ->orderBy('id');
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
        // minutes, of course.

        $this->attributes['expires_in'] = array_get($value, 'expires_in');
    }
}
