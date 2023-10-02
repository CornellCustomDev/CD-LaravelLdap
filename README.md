# CD-LaravelLdap

A Laravel package for retrieving Cornell University LDAP data.

This package exists in order to provide a robust, well-tested LDAP connection service with a standard data structure that is well-defined and can be extended over time.

## Installation
The package can be installed via composer once an initial release has been added to packagist:

```bash
composer require cornell-custom-dev/laravel-ldap
```

After running composer require, the config file should be published:

```bash
php artisan vendor:publish --tag=laravel-ldap-config
```

Environment variables that define the LDAP user and password should be set in the environment. See `/resources/stubs/.env.ldap.stub`.

## Usage

The service is registered automatically in the Laravel dependency injection container. It can be called statically and the service will be resolved from the container:

```php
try {
  $ldapData = LdapService::get($netid);
  $display_name = $ldapData->display_name;
} catch (LdapServiceException $e) {
  ...
}
```

The `LdapService::get()` method caches the query for 300 seconds by default, so multiple calls to the service for the same `$netid` value are not expensive.

Documentation of all currently parsed fields can be found in `src/LdapData.php`.

## Contributing

Anyone on the Custom Development team should be welcome and able to contribute. See [CONTRIBUTING](CONTRIBUTING.md) for details on how be involved and provide quality contributions.
