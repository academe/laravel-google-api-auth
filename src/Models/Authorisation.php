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
}
