# CD-LaravelLdap

A Laravel package for connecting to Cornell LDAP resources

## Installation
The package can be installed via composer once an initial release has been added to packagist:

```bash
composer require cornell-custom-dev/laravel-ldap
```

Environment variables that define the LDAP user and password should be set in the environment. See `/resources/stubs/.env.ldap.stub`.

## Usage

The service is registered automatically in the Laravel dependency injection container. This means that Laravel will provide the service simply by referencing it as a typed argument for a method:

```php
public function getLdapName($netid, LdapService $ldap): string
{
    $ldapData = $ldap->get($netid);
    
    if (empty($ldapData)) {
        return null;
    }
    
    return $ldapData->first_name . ' ' $ldapData->last_name
}
```

The method `LdapService::get($netid)` caches a query for 300 seconds by default, so multiple calls to the service for the same `$netid` value are not expensive.
