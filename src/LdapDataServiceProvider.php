<?php

namespace CornellCustomDev\LaravelStarterKit\Ldap;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LdapDataServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-ldap')
            ->publishesServiceProvider('LdapDataServiceProvider')
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile();
            });
    }

    public function boot(): void
    {
        $this->app->singleton(
            abstract: LdapDataService::class,
            concrete: fn () => new LdapDataService(
                ldap: new Ldap(
                    ldap_user: strval(config('ldap.user') ?: ''),
                    ldap_pass: strval(config('ldap.pass') ?: ''),
                    ldap_server: strval(config('ldap.server') ?: ''),
                    ldap_base_dn: strval(config('ldap.base_dn') ?: ''),
                ),
                cache_seconds: intval(config('ldap.cache_seconds')),
            ),
        );
    }
}
