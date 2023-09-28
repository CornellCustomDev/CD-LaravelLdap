<?php

namespace CornellCustomDev\LaravelLdap\Tests\Feature;

use CornellCustomDev\LaravelLdap\Ldap;
use CornellCustomDev\LaravelLdap\LdapService;
use CornellCustomDev\LaravelLdap\LdapServiceException;
use CornellCustomDev\LaravelLdap\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;

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

    public function testMakesSingletonService()
    {
        $service = LdapService::make();
        $this->assertInstanceOf(LdapService::class, $service);

        // Confirm it is a singleton
        $this->assertEquals($service, app(LdapService::class));
    }

    public function testGetRequiresNetid()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('get requires netid');

        LdapService::get(null);
    }

    public function testFindWillCatchFailedConnection()
    {
        $ldap = $this->mockLdap(connection: false);
        $service = new LdapService($ldap);

        $this->expectException(LdapServiceException::class);
        $this->expectExceptionMessage('Could not connect to LDAP server.');

        $service->find('netid');
    }

    public function testFindWillCatchFailedBind()
    {
        $ldap = $this->mockLdap(bind: false);
        $service = new LdapService($ldap);

        $this->expectException(LdapServiceException::class);
        $this->expectExceptionMessage('Could not bind to LDAP server.');

        $service->find('netid');
    }

    public function testFindWillCatchErrorResult()
    {
        $error_message = 'TEST_ERROR';
        $ldap = $this->mockLdap(parse_result: $error_message);
        $service = new LdapService($ldap);

        $this->expectException(LdapServiceException::class);
        $this->expectExceptionMessage("Error response from ldap_bind: $error_message");

        $service->find('netid');
    }

    public function testFindWillCatchFailedSearch()
    {
        $error_message = 'TEST_ERROR';
        $ldap = $this->mockLdap();
        $ldap->method('getFirst')->willThrowException(new \Exception($error_message));
        $service = new LdapService($ldap);

        $this->expectException(LdapServiceException::class);
        $this->expectExceptionMessage($error_message);

        $service->find('netid');
    }

    public function testFindWillReturnNullIfNoResults()
    {
        $ldap = $this->mockLdap();
        $service = new LdapService($ldap);

        $result = $service->find('netid');

        $this->assertNull($result);
    }

    public function testFindWillReturnLdapData()
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
    ): Ldap|MockObject
    {
        $ldap = $this->createMock(Ldap::class);
        $ldap->method('connect')->willReturn($connection);
        $ldap->method('bind')->willReturn($bind);
        $ldap->method('parse_result')->willReturn($parse_result);
        $ldap->method('getFirst')->willReturn($getFirst);

        return $ldap;
    }

}
