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

The aim is that access token renewals wil be invisable to your application.
The way the Google API Client is written, this may not be entirely possible,
but I hope with the right wrappers it will be.

Your applicartion will need to be registered with Google, and will need to
state up front what contexts it wants to access, i.e. which APIs. The end
user providing authorisation for their APIs (with OAuth 2) will not need to
set up anything special in their account.

I hope that sets the context of what this package is trying to achieve.
