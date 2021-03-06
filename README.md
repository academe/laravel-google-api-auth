# Google API Access for Laravel 5.4

## Quick Summary

This package is still in development, but I am using it in production.
Here is a brief description of what the intended use-case is.

This is the Laravel glue and a wrapper for the
[Google API Client](https://github.com/google/google-api-php-client) version 3.
It provides routes for *non-service account* authorising and revodking, a model
and storage for multiple authorisations that users may have on your application,
and helper classes to load and configure the Google API Client.

It's purpose is to allow a Laravel user to authenticate their own
Google account with your application, so that your application can
access the Google APIs on behalf of that user.

Originally it was just for accessing Google Analytics for a remote site,
but it can be used for any of the Google APIs through scopes.

It will support renewal keys, so will be able to access the API offline,
i.e. when the user is not present.

The aim is that access token renewals will be invisible to your application.
The way the Google API Client is written, this may not be entirely possible,
but I hope with the right wrappers it will be.

Your application will need to be registered with Google, and will need to
state up front what contexts it wants to access, i.e. which APIs. The end
user providing authorisation for their APIs (with OAuth 2) will not need to
set up anything special in their account.

Using this package, it is possoble for a single user to authorise access to a
Google account, and then share that authorisation with multiple users.
All required authorisation details are stored in a model storage rather than a
user's session, so access to the model instance is all that is needed for an
authorised access to and API.

I hope that sets the context of what this package is trying to achieve.

## Requirements

This package requires PHP >=5.6 and Laravel >= 5.4

## Installation

Install via composer - edit your `composer.json` to require the package.

```js
"require": {
    "academe/googleapi": "1.*"
}
```

Then run `composer update` in your terminal to pull it in.

Or use `composer require academe/googleapi`

## Laravel

To use in laravel add the following to the `providers` array in your `config/app.php`

```php
Academe\GoogleApi\GoogleApiServiceProvider::class,
```

There is no facade in this package at this time.

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

You should use POST for this route. If you use GET, then a simple form will be provided for the user to confirm.

All authorisation instances have a name, which is a unique list for each laravel user.
The default name is "default".
Specify a name when authorisating by adding the `'name' => 'name meanngful to the user'`
parameter to the authorise route.

Get access to the API and list Analytics accounts that can be accessed:

```php
try {
    $client = \Academe\GoogleApi\Helper::getApiClient(\Academe\GoogleApi\Helper::getCurrentUserAuth('default'));
    $service = new Google_Service_Analytics($client);
    $accounts = $service->management_accountSummaries->listManagementAccountSummaries();
} catch (Exception $e) {
    // Invalid credentials, or any other error in the API request.
    $client = null;
}

if ($client) {
    foreach ($accounts->getItems() as $item) {
        // You wouldn't really echo anything in Laravel...
        echo "Account: " . $item['name'] . " (" . $item['id'] . ") <br />";
    }
}
```

Once authorised, the access token will be refreshed automatically, so long as
the refresh token is not revoked.

The `academe_gapi_authorise` route will revoke am authorisation for the current user.
Parameters include `name` to identify which authorisaition to revoke.
This route should be called using the `DELETE` HTTP method.
A simple confirmation form is provided if the `GET` method is used.

## Other Notes

* When a laravel user provide authorisation, we grab the Google user ID and their
  email address. This ID is unique to that Google account. With this we can find
  any Google user that is authorised more than once. While this is permitted,
  it does use up a limited number of tokens that Google will provide, so this
  allows any duplicates to be found and merged.
* Each user can have about 25 active tokens, so a user
  can authorise the same application multiple times. We won't prevent this
  from happenening usng this package, *but* we provide a "name" to be able to
  distinguish between each authorisation instance.
* The scopes are stored against each authorisation, so it can be extended if
  needed, with a reauthorisation to provide incremental authorisation.

## TODO

* Some way to handle an expired access token that we did not renew in time.
  This will result in an exception when it is used. The action will be to
  catch the exception, attempt to renew the access token, then try the same
  action again. How we would wrap that is not clear.
* Think of a way to handle race conditions on access token renewals. It probably
  won't be a problem, as the renewal token can be reused, so an old access
  token will always be caught by exception and renewed.
* The 'offline' and 'force' parameters force a new renewal token to be generated on
  every authorisartion. There may be times when the authorisation is not needed
  for offlne purposes. There may also be times when a "force" is not needed, and
  the current refresh token can continue to be used. If the scope changes, e.g. is
  added to, then a force is needed. Look into this.
* A hook into the client creation (a factory?) will allow a client to be
  customised on creation.
