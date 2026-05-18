<?php

namespace CornellCustomDev\LaravelLdap\Tests\Feature;

use CornellCustomDev\LaravelLdap\Ldap;
use CornellCustomDev\LaravelLdap\LdapService;
use CornellCustomDev\LaravelLdap\LdapServiceException;
use CornellCustomDev\LaravelLdap\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;

class LdapServiceTest extends TestCase
{
    use WithFaker;

    #[Test]
    public function can_build_service()
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

    #[Test]
    public function makes_singleton_service()
    {
        $service = LdapService::make();
        $this->assertInstanceOf(LdapService::class, $service);

        // Confirm it is a singleton
        $this->assertEquals($service, app(LdapService::class));
    }

    #[Test]
    public function get_requires_netid()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('get requires netid');

        LdapService::get(null);
    }

    #[Test]
    public function find_will_catch_failed_connection()
    {
        $ldap = $this->mockLdap(connection: false);
        $service = new LdapService($ldap);

        $this->expectException(LdapServiceException::class);
        $this->expectExceptionMessage('Could not connect to LDAP server.');

        $service->find('netid');
    }

    #[Test]
    public function find_will_catch_failed_bind()
    {
        $ldap = $this->mockLdap(bind: false);
        $service = new LdapService($ldap);

        $this->expectException(LdapServiceException::class);
        $this->expectExceptionMessage('Could not bind to LDAP server.');

        $service->find('netid');
    }

    #[Test]
    public function find_will_catch_error_result()
    {
        $error_message = 'TEST_ERROR';
        $ldap = $this->mockLdap(parse_result: $error_message);
        $service = new LdapService($ldap);

        $this->expectException(LdapServiceException::class);
        $this->expectExceptionMessage("Error response from ldap_bind: $error_message");

        $service->find('netid');
    }

    #[Test]
    public function find_will_catch_failed_search()
    {
        $error_message = 'TEST_ERROR';
        $ldap = $this->mockLdap();
        $ldap->method('getFirst')->willThrowException(new \Exception($error_message));
        $service = new LdapService($ldap);

        $this->expectException(LdapServiceException::class);
        $this->expectExceptionMessage($error_message);

        $service->find('netid');
    }

    #[Test]
    public function find_will_return_null_if_no_results()
    {
        $ldap = $this->mockLdap();
        $service = new LdapService($ldap);

        $result = $service->find('netid');

        $this->assertNull($result);
    }

    #[Test]
    public function find_will_return_ldap_data()
    {
        $ldapResponse = $this->fixture('ldap_search.json', json: true);

        $ldap = $this->mockLdap(getFirst: $ldapResponse);
        $service = new LdapService($ldap);

        $result = $service->find('netid');

        $this->assertEquals('tt999', $result->netid);
        $this->assertEquals('9999999', $result->emplid);
        $this->assertEquals('Testy', $result->first_name);
        $this->assertEquals('Testerson', $result->last_name);
        $this->assertEquals('Testy Testerson', $result->display_name);
        $this->assertEquals('testerson@cornell.edu', $result->email);
        $this->assertEquals('607/2551111', $result->campus_phone);
        $this->assertEquals('CIO - CIT Enterprise Services', $result->dept_name);
        $this->assertEquals('Web Developer', $result->working_title);
        $this->assertEquals('staff', $result->primary_affiliation);
        $this->assertEquals(['staff'], $result->affiliations);
        $this->assertEquals(null, $result->previous_netids);
        $this->assertEquals(null, $result->previous_emplids);
    }

    private function mockLdap(
        $connection = true,
        $bind = true,
        $parse_result = true,
        $getFirst = null,
    ): Ldap|Stub {
        $ldap = $this->createStub(Ldap::class);
        $ldap->method('connect')->willReturn($connection);
        $ldap->method('bind')->willReturn($bind);
        $ldap->method('parse_result')->willReturn($parse_result);
        $ldap->method('getFirst')->willReturn($getFirst);

        return $ldap;
    }
}
