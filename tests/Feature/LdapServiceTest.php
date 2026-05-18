<?php

namespace CornellCustomDev\LaravelStarterKit\Ldap\Tests\Feature;

use CornellCustomDev\LaravelStarterKit\Ldap\Ldap;
use CornellCustomDev\LaravelStarterKit\Ldap\LdapDataService;
use CornellCustomDev\LaravelStarterKit\Ldap\LdapDataException;
use CornellCustomDev\LaravelStarterKit\Ldap\Tests\TestCase;
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
        $service = new LdapDataService($ldap, intval(null));

        $this->assertInstanceOf(LdapDataService::class, $service);
    }

    #[Test]
    public function makes_singleton_service()
    {
        $service = LdapDataService::make();
        $this->assertInstanceOf(LdapDataService::class, $service);

        // Confirm it is a singleton
        $this->assertEquals($service, app(LdapDataService::class));
    }

    #[Test]
    public function get_requires_netid()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('get requires netid');

        LdapDataService::get(null);
    }

    #[Test]
    public function find_will_catch_failed_connection()
    {
        $ldap = $this->mockLdap(connection: false);
        $service = new LdapDataService($ldap);

        $this->expectException(LdapDataException::class);
        $this->expectExceptionMessage('Could not connect to LDAP server.');

        $service->find('netid');
    }

    #[Test]
    public function find_will_catch_failed_bind()
    {
        $ldap = $this->mockLdap(bind: false);
        $service = new LdapDataService($ldap);

        $this->expectException(LdapDataException::class);
        $this->expectExceptionMessage('Could not bind to LDAP server.');

        $service->find('netid');
    }

    #[Test]
    public function find_will_catch_error_result()
    {
        $error_message = 'TEST_ERROR';
        $ldap = $this->mockLdap(parse_result: $error_message);
        $service = new LdapDataService($ldap);

        $this->expectException(LdapDataException::class);
        $this->expectExceptionMessage("Error response from ldap_bind: $error_message");

        $service->find('netid');
    }

    #[Test]
    public function find_will_catch_failed_search()
    {
        $error_message = 'TEST_ERROR';
        $ldap = $this->mockLdap();
        $ldap->method('getFirst')->willThrowException(new \Exception($error_message));
        $service = new LdapDataService($ldap);

        $this->expectException(LdapDataException::class);
        $this->expectExceptionMessage($error_message);

        $service->find('netid');
    }

    #[Test]
    public function find_will_return_null_if_no_results()
    {
        $ldap = $this->mockLdap();
        $service = new LdapDataService($ldap);

        $result = $service->find('netid');

        $this->assertNull($result);
    }

    #[Test]
    public function find_will_return_ldap_data()
    {
        $ldapResponse = $this->fixture('ldap_search.json', json: true);

        $ldap = $this->mockLdap(getFirst: $ldapResponse);
        $service = new LdapDataService($ldap);

        $result = $service->find('netid');

        $this->assertEquals('tt999', $result->id());
        $this->assertEquals('9999999', $result->emplid);
        $this->assertEquals('Testy', $result->firstName);
        $this->assertEquals('Testerson', $result->lastName);
        $this->assertEquals('Testy Testerson', $result->name());
        $this->assertEquals('testerson@cornell.edu', $result->email());
        $this->assertEquals('607/2551111', $result->campusPhone);
        $this->assertEquals('CIO - CIT Enterprise Services', $result->deptName);
        $this->assertEquals('Web Developer', $result->workingTitle);
        $this->assertEquals('staff', $result->primaryAffiliation);
        $this->assertEquals(['staff'], $result->affiliations);
        $this->assertEquals(null, $result->previousNetids);
        $this->assertEquals(null, $result->previousEmplids);
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
