<?php

namespace Academe\GoogleApi\Traits;

/**
 * Trait for the Users model, to fetch all Google Authorisations a user has.
 * Not required for the operation of this package, but here for convenience.
 */

trait HasGoogleAuthorisationTrait
{
    /**
     * Get the Google authorisations this user owns.
     */
    public function googleAuthorisations()
    {
        return $this->hasMany('Academe\GoogleApi\Models\Authorisation');
    }

}
