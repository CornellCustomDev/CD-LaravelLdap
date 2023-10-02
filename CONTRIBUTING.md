# Contributing

**Contributions are welcome!**

This package was built on a fairly lightweight need of retrieving basic LDAP directory data for an application. It is not intended to be a comprehensive LDAP solution. But if there are features that you need, please open an issue or submit a pull request.

This package follows the same approach to contribution as the [Laravel Starter Kit](https://github.com/CU-CommunityApps/CD-LaravelStarterKit/blob/main/CONTRIBUTING.md). Please see that document for more information. 

This package is built with `spatie/laravel-package-tools` and generally follows the approach from [Spatie's Package Skeleton](https://github.com/spatie/package-skeleton-laravel). It was written based on learning from https://laravelpackage.training/. It is also informed by https://laravelpackage.com/.

## Local Development Setup

```shell
composer install
```

## Testing

There is testing coverage for nearly 100% of the `LdapService` and `LdapData` classes. The `Ldap` class exists to allow for mocking the PHP LDAP extension.
Please add tests for any new features or bug fixes.

Tests are run with PHPUnit:

```bash
vendor/bin/phpunit
```

## Development Standards

Please follow the goals and style [outlined in the Laravel Starter Kit](https://github.com/CU-CommunityApps/CD-LaravelStarterKit/blob/main/CONTRIBUTING.md#development-standards). 

Linting is run with Laravel Pint:

```bash
vendor/bin/pint
```
