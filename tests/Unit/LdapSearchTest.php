<?php

namespace CornellCustomDev\LaravelStarterKit\Ldap\Tests\Unit;

use CornellCustomDev\LaravelStarterKit\Ldap\LdapDataException;
use CornellCustomDev\LaravelStarterKit\Ldap\LdapSearch;
use CornellCustomDev\LaravelStarterKit\Ldap\LdapService;
use CornellCustomDev\LaravelStarterKit\Ldap\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
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
        $this->expectExceptionMessage('getById requires a search term');

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
        $this->getFunctionMock('CornellCustomDev\LaravelStarterKit\Ldap', 'ldap_error')
            ->expects($this->once())->willReturn("Can't contact LDAP server");

        $this->expectException(LdapDataException::class);
        $this->expectExceptionMessage("Could not bind to LDAP server: Can't contact LDAP server");

        LdapSearch::search('netid', bustCache: true);
    }

    #[Test]
    public function find_will_catch_unparseable_bind_result()
    {
        $this->mockLdap(parse_result: false);
        $this->getFunctionMock('CornellCustomDev\LaravelStarterKit\Ldap', 'ldap_error')
            ->expects($this->once())->willReturn('Decoding error');

        $this->expectException(LdapDataException::class);
        $this->expectExceptionMessage('Could not parse the response from ldap_bind: Decoding error');

        LdapSearch::search('netid', bustCache: true);
    }

    #[Test]
    public function find_will_catch_rejected_bind()
    {
        $diagnostic = '80090308: LdapErr: DSID-0C09041C, comment: AcceptSecurityContext error, data 52e, v4563';
        $this->mockLdap(bind_error_code: 49, bind_error_message: $diagnostic);
        Log::shouldReceive('debug')->never();

        try {
            LdapSearch::search('netid', bustCache: true);
            $this->fail('Expected a rejected bind to throw LdapDataException.');
        } catch (LdapDataException $e) {
            $this->assertSame('Error response from ldap_bind: Invalid credentials', $e->getMessage());
            $this->assertStringNotContainsString(config('ldap.pass'), $e->getMessage());
        }
    }

    #[Test]
    public function find_will_log_rejected_bind_diagnostic_when_debugging()
    {
        config(['ldap.debug' => true]);
        $diagnostic = '8009030C: LdapErr: DSID-0C090534, comment: AcceptSecurityContext error, data 2030, v4f7c';
        $this->mockLdap(bind_error_code: 49, bind_error_message: $diagnostic);
        Log::shouldReceive('debug')->once()->with('LDAP bind rejected', [
            'server' => app(LdapService::class)->server,
            'diagnostic' => $diagnostic,
        ]);

        $this->expectException(LdapDataException::class);
        $this->expectExceptionMessage('Error response from ldap_bind: Invalid credentials');

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
        $this->assertEquals('tt999@cornell.edu', $result->primaryEmail());
        $this->assertEquals('testerson@cornell.edu', $result->emailAlias());
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
        $bind_error_code = 0,
        $bind_error_message = '',
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
        $ldap_connect->expects($this->once())->willReturnCallback(
            function ($connection, $result, &$error_code, &$matched_dn, &$error_message) use ($parse_result, $bind_error_code, $bind_error_message) {
                $error_code = $bind_error_code;
                $error_message = $bind_error_message;

                return $parse_result;
            });
        if ($parse_result !== true || $bind_error_code !== 0) {
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
