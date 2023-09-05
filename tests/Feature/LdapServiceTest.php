<?php

namespace CornellCustomDev\LaravelLdap\Tests\Feature;

use CornellCustomDev\LaravelLdap\Ldap;
use CornellCustomDev\LaravelLdap\LdapService;
use CornellCustomDev\LaravelLdap\LdapServiceException;
use CornellCustomDev\LaravelLdap\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use InvalidArgumentException;

class LdapServiceTest extends TestCase
{
    use WithFaker;

    public function testCanBuildService()
    {
        $ldap = new Ldap(
            ldap_user: $this->faker->userName,
            ldap_pass: $this->faker->password,
            ldap_server: 'directory',
            ldap_base_dn: 'dn',
        );
        $service = new LdapService($ldap, intval(null));

        $this->assertInstanceOf(LdapService::class, $service);
    }

    public function testCanResolveServiceFromContainer()
    {
        $service = resolve(LdapService::class);
        $this->assertInstanceOf(LdapService::class, $service);
    }

    public function testRequiresNetid()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('get requires netid');

        LdapService::get(null);
    }

    public function testWillCatchFailedConnection()
    {
        $this->expectException(LdapServiceException::class);

        // Create a mock Ldap object that will return false for connect()
        $ldap = $this->createMock(Ldap::class);
        $ldap->method('connect')->willReturn(false);

        $service = new LdapService($ldap);

        $service->find('netid');
    }
}
