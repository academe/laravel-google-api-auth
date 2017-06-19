# Google API Access for Laravel 5.4

## Quick Summary

This package is still in development, but here is a brief description of
what the intended use-case is.

This is the glue and a wrapper for the Google API Client version 3.

It's purpose is to allow a Laravel user to authenticate their own
Google account with your application, so that your application can
access the Google APIs on behalf of that user.

Originally it was just for accessing Google Analytics for a remote site,
but it can be used for any of the Google APIs.

It will support renewal keys, so will be able to access the API offline,
i.e. when the user is not present.

The aim is that access token renewals will be invisible to your application.
The way the Google API Client is written, this may not be entirely possible,
but I hope with the right wrappers it will be.

Your application will need to be registered with Google, and will need to
state up front what contexts it wants to access, i.e. which APIs. The end
user providing authorisation for their APIs (with OAuth 2) will not need to
set up anything special in their account.

I hope that sets the context of what this package is trying to achieve.

## Using This Package

These are just rough developer usage notes.

* Install the package and run the database migrations.
* Set the Google API credentials (check out the config file), making sure
  the Analytics scope is enabled.
* Start an authorisation by going to `route('academe_gapi_authorise')`
* The first time you try it, Google will complain about the redirect URL.
  Just follow the instructions Google gives to add the redirect URL to the
  Google web aplication.

Sampele code:

Link that allows the current user to authorise the API to access Analytics,
then return to the `/home` URL:

```php
<a href="{{ route('academe_gapi_authorise', ['final_url' => url('home')]) }}">GAPI Auth</a>
```

You can set and add scopes too. For example:

```php
// Or use "add_scopes" to add to the scopes already authenticated.
<a href="{{ route('academe_gapi_authorise', ['scopes' => [Google_Service_Analytics::ANALYTICS_READONLY]]) }}">GAPI Auth</a>
```

Get access to the API and list Analytics accounts that can be accessed:

```php
try {
    $client = \Academe\GoogleApi\Helper::getApiClient(\Academe\GoogleApi\Helper::getCurrentUserAuth());
    $service = new Google_Service_Analytics($client);
    $accounts = $service->management_accountSummaries->listManagementAccountSummaries();
} catch (Exception $e) {
    $client = null;
}

if ($client) {
    foreach ($accounts->getItems() as $item) {
        // You wouldn't really echo anything in Laravel...
        echo "Account: " . $item['name'] . " (" . $item['id'] . ") <br />";
    }
}
```

Once authorised, this will refresh the access token automatically, so long as
the refresh token is not withdrawn.

## TODO

* Multiple authorisations for a single user, I *think* are not permitted.
  So think about invalidating any authorisations already in effect for that
  user. This assumes there is only one set of credentials (a good assumption
  for now, but may change later) so it means each user should only have one
  record in the authorisations table.
* Method to revoke an authorisation. Try revoking it through the API (which
  may or may not succeed) then just remove the local access and renewal tokens.
* Some way to handle an expired access token that we did not renew in time.
  This will result in an exception when it is used. The action will be to
  catch the exception, attempt to renew the access token, then try the same
  action again. How we would wrap that is not clear.
* Think of a way to handle race conditions on access token renewals. It probably
  won't be a problem, as the renewal token can be reused, so an old access
  token will always be caught by exception and renewed.
* Handle the scope properly. It is hard-coded ATM to Analytics.
* Store the context in the authorisation model. Two reasons:
  * The context may be different for each user authorisatin, so we need to know
    what it is for each client we create.
  * If the scope of an authorisation is changed, i.e. extended, then it needs to
    be authorised again. We will only know it has extended by keeping a record of
    the previous scopes. Google calls this incremental authorisation.
* If a user can have multiple authorisations with different Google accounts,
  then how do we ensure this? For example, a user authorises two different areas
  of the app inadvertently using the same Google account. How do we detect this
  and raise it as an error? We need a unique ID of the Google user who
  authenticated. We are not saving the Google user ID and can work on looking
  for duplicates later. The application is best placed for deciding what to do
  with duplicates - perhaps deleting the older one and linking the newer one
  in its place.  
  
  Bear in mind also that two different application users could be
  the same physical user and could authorise the same Google account using
  the separate users. Note that we cannot detect the user doing this in advance;
  by the time we know a user has authorised the same account twice (thus
  invalidating the first access token and renewal token) it is done and so
  only remedial action is possible (e.g informing the user of the invalidated
  authorisation).

