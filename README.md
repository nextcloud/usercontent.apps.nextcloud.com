# Proxy for the Nextcloud appstore

The Nextcloud appstore is serving some content from external domains.
This can be considered a privacy violation in some cases.

This software downloads legit resources from the appstore
and acts as a proxy for the images.

## Development

After cloning the repository,
you can run the proxy locally with `php -S localhost:8001 index.php`.

You can also download the app screenshots to the cache directory
by running the `sync.php` script.
