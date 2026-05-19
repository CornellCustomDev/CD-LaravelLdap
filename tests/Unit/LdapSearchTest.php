<?php

namespace CornellCustomDev\LaravelStarterKit\Ldap\Tests\Unit;

use CornellCustomDev\LaravelStarterKit\Ldap\LdapDataException;
use CornellCustomDev\LaravelStarterKit\Ldap\LdapSearch;
use CornellCustomDev\LaravelStarterKit\Ldap\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use InvalidArgumentException;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\Test;

class LdapSearchTest extends TestCase
{
    use PHPMock;
    use WithFaker;

    #[Test]
    public function get_by_netid_requires_netid()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('getByNetid requires a search term');

        LdapSearch::getByNetid(' ', bustCache: true);
    }

    #[Test]
    public function get_by_email_requires_netid()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('getByEmail requires a search term');

        LdapSearch::getByEmail(' ', bustCache: true);
    }

    #[Test]
    public function search_requires_netid()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('search requires a search term');

        LdapSearch::search(' ', bustCache: true);
    }

    #[Test]
    public function find_will_catch_failed_connection()
    {
        $this->mockLdap(connection: false);

        $this->expectException(LdapDataException::class);
        $this->expectExceptionMessage('Could not connect to LDAP server.');

        LdapSearch::search('netid', bustCache: true);
    }

    #[Test]
    public function find_will_catch_failed_bind()
    {
        $this->mockLdap(bind: false);

        $this->expectException(LdapDataException::class);
        $this->expectExceptionMessage('Could not bind to LDAP server.');

        LdapSearch::search('netid', bustCache: true);
    }

    #[Test]
    public function find_will_catch_error_result()
    {
        $error_message = 'TEST_ERROR';
        $this->mockLdap(parse_result: $error_message);

        $this->expectException(LdapDataException::class);
        $this->expectExceptionMessage("Error response from ldap_bind: $error_message");

        LdapSearch::search('netid', bustCache: true);
    }

    #[Test]
    public function find_will_catch_failed_search()
    {
        $this->mockLdap(search_result: false);

        $result = LdapSearch::search('netid', bustCache: true);

        $this->assertNull($result);
    }

    #[Test]
    public function find_will_return_null_if_no_results()
    {
        $this->mockLdap(entries: ['count' => 0]);

        $result = LdapSearch::search('netid', bustCache: true);

        $this->assertNull($result);
    }

    #[Test]
    public function find_will_return_ldap_data()
    {
        $ldapResponse = $this->fixture('ldap_search.json', json: true);

        $this->mockLdap(entries: ['count' => 1, $ldapResponse]);

        $result = LdapSearch::getByNetId('netid', bustCache: true);

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
        $search_result = [],
        $entries = [],
    ): void {
        $ldap_connect = $this->getFunctionMock('CornellCustomDev\LaravelStarterKit\Ldap', 'ldap_connect');
        $ldap_connect->expects($this->once())->willReturn($connection);
        if ($connection === false) {
            return;
        }

        // If we have a connection, we will be setting options and will need to close it.
        $ldap_connect = $this->getFunctionMock('CornellCustomDev\LaravelStarterKit\Ldap', 'ldap_set_option');
        $ldap_connect->expects($this->atLeastOnce());
        $ldap_connect = $this->getFunctionMock('CornellCustomDev\LaravelStarterKit\Ldap', 'ldap_close');
        $ldap_connect->expects($this->once());

        $ldap_connect = $this->getFunctionMock('CornellCustomDev\LaravelStarterKit\Ldap', 'ldap_bind_ext');
        $ldap_connect->expects($this->once())->willReturn($bind);
        if ($bind === false) {
            return;
        }

        $ldap_connect = $this->getFunctionMock('CornellCustomDev\LaravelStarterKit\Ldap', 'ldap_parse_result');
        $ldap_connect->expects($this->once())->willReturn($parse_result);
        if ($parse_result !== true) {
            return;
        }

        $ldap_connect = $this->getFunctionMock('CornellCustomDev\LaravelStarterKit\Ldap', 'ldap_search');
        $ldap_connect->expects($this->once())->willReturn($search_result ?? $entries ?? null);
        if ($search_result === false) {
            return;
        }

        $ldap_connect = $this->getFunctionMock('CornellCustomDev\LaravelStarterKit\Ldap', 'ldap_get_entries');
        $ldap_connect->expects($this->once())->willReturn($entries);
    }
}
