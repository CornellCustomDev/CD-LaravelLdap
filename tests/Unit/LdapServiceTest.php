<?php

namespace CornellCustomDev\LaravelStarterKit\Ldap\Tests\Unit;

use CornellCustomDev\LaravelStarterKit\Ldap\LdapData;
use CornellCustomDev\LaravelStarterKit\Ldap\LdapDataException;
use CornellCustomDev\LaravelStarterKit\Ldap\LdapSearch;
use CornellCustomDev\LaravelStarterKit\Ldap\LdapService;
use CornellCustomDev\LaravelStarterKit\Ldap\Tests\TestCase;
use InvalidArgumentException;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\Test;

class LdapServiceTest extends TestCase
{
    use PHPMock;

    /**
     * Arguments passed to the mocked ldap_* functions, keyed by function name.
     */
    private array $calls = [];

    private function weill(string $user = 'cn=test,ou=binds,dc=weill,dc=cornell,dc=edu', string $pass = 'secret'): LdapService
    {
        return new LdapService(
            server: 'ldaps://weill.example.edu',
            user: $user,
            pass: $pass,
            baseDn: config('ldap.weill.base_dn'),
            domain: config('ldap.weill.domain'),
            idAttribute: config('ldap.weill.id_attribute'),
            attributes: config('ldap.weill.attributes'),
        );
    }

    #[Test]
    public function package_binds_the_cornell_service()
    {
        $service = app(LdapService::class);

        $this->assertSame($service, app(LdapService::class));
        $this->assertSame($service, app('ldap.cornell'));
        $this->assertEquals('ldaps://query.directory.cornell.edu', $service->server);
        $this->assertEquals('ou=People,o=Cornell University,c=US', $service->baseDn);
        $this->assertEquals('cornell.edu', $service->domain);
        $this->assertEquals('uid', $service->idAttribute);
        $this->assertEquals(LdapService::DEFAULT_ATTRIBUTES, $service->attributes);
    }

    #[Test]
    public function weill_service_returns_ldap_data_by_cwid()
    {
        $this->mockLdap(entries: ['count' => 1, $this->fixture('ldap_weill.json', json: true)]);

        $result = $this->weill()->getById('abc2001', bustCache: true);

        $this->assertEquals('ldaps://weill.example.edu', $this->calls['ldap_connect'][0]);
        $this->assertEquals('cn=test,ou=binds,dc=weill,dc=cornell,dc=edu', $this->calls['ldap_bind_ext'][1]);
        $this->assertEquals('secret', $this->calls['ldap_bind_ext'][2]);
        $this->assertEquals('ou=people,dc=weill,dc=cornell,dc=edu', $this->calls['ldap_search'][1]);
        $this->assertEquals('(weillCornellEduCWID=abc2001)', $this->calls['ldap_search'][2]);
        $this->assertEquals(LdapService::WEILL_ATTRIBUTES, $this->calls['ldap_search'][3]);

        $this->assertInstanceOf(LdapData::class, $result);
        $this->assertEquals('med.cornell.edu', $result->domain);
        $this->assertEquals('wew2001', $result->id());
        $this->assertEquals('wew2001@med.cornell.edu', $result->principalName());
        $this->assertEquals('8888888', $result->emplid);
        $this->assertEquals('Wendy', $result->firstName);
        $this->assertEquals('Weillerson', $result->lastName);
        $this->assertEquals('Wendy Weillerson', $result->name());
        $this->assertEquals('wew2001@med.cornell.edu', $result->email());
        $this->assertEquals('abc2001', $result->returnedData['weillcornelleducwid']);
        $this->assertEquals('wew2001', $result->ldapData['netid']);
    }

    #[Test]
    public function get_by_id_requires_id()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('getById requires a search term');

        $this->weill()->getById(' ', bustCache: true);
    }

    #[Test]
    public function get_by_id_returns_null_if_no_results()
    {
        $this->mockLdap(entries: ['count' => 0]);

        $this->assertNull($this->weill()->getById('abc2001', bustCache: true));
    }

    #[Test]
    public function service_without_credentials_throws_ldap_data_exception()
    {
        $this->expectException(LdapDataException::class);
        $this->expectExceptionMessage('LDAP bind credentials are not configured.');

        $this->weill(user: '')->getById('abc2001', bustCache: true);
    }

    #[Test]
    public function get_by_netid_matches_exactly_and_escapes_the_netid()
    {
        $this->mockLdap(entries: ['count' => 0]);

        LdapSearch::getByNetid('tt9*', bustCache: true);

        $this->assertEquals('ldaps://query.directory.cornell.edu', $this->calls['ldap_connect'][0]);
        $this->assertEquals('uid=test,ou=Directory Administrators,o=Cornell University,c=US', $this->calls['ldap_bind_ext'][1]);
        $this->assertEquals('(uid=tt9\2a)', $this->calls['ldap_search'][2]);
    }

    #[Test]
    public function searches_with_different_attributes_are_cached_separately()
    {
        // A null result is never cached, so the first search must return an entry for the cache to matter
        $this->mockLdap(entries: ['count' => 1, $this->fixture('ldap_search.json', json: true)], times: 2);

        LdapSearch::search('(uid=tt999)', bustCache: true);
        LdapSearch::search('(uid=tt999)', attributes: ['uid', 'cornelleduacadcollege']);

        $this->assertEquals(['uid', 'cornelleduacadcollege'], $this->calls['ldap_search'][3]);
    }

    #[Test]
    public function cornell_service_without_ldap_user_throws_ldap_data_exception()
    {
        config(['ldap.user' => '']);
        $this->app->forgetInstance(LdapService::class);

        $this->expectException(LdapDataException::class);
        $this->expectExceptionMessage('LDAP bind credentials are not configured.');

        LdapSearch::getByNetid('tt999', bustCache: true);
    }

    #[Test]
    public function service_without_credentials_throws_even_when_the_cache_is_warm()
    {
        $this->mockLdap(entries: ['count' => 1, $this->fixture('ldap_weill.json', json: true)]);
        $this->weill()->getById('abc2001', bustCache: true);

        $this->expectException(LdapDataException::class);
        $this->expectExceptionMessage('LDAP bind credentials are not configured.');

        $this->weill(pass: '')->getById('abc2001');
    }

    private function mockLdap(array $entries, int $times = 1): void
    {
        $namespace = 'CornellCustomDev\LaravelStarterKit\Ldap';
        $record = fn (string $function, $return) => function (...$args) use ($function, $return) {
            $this->calls[$function] = $args;

            return $return;
        };

        $this->getFunctionMock($namespace, 'ldap_connect')
            ->expects($this->exactly($times))->willReturnCallback($record('ldap_connect', true));
        $this->getFunctionMock($namespace, 'ldap_set_option')
            ->expects($this->atLeastOnce());
        $this->getFunctionMock($namespace, 'ldap_close')
            ->expects($this->exactly($times));
        $this->getFunctionMock($namespace, 'ldap_bind_ext')
            ->expects($this->exactly($times))->willReturnCallback($record('ldap_bind_ext', true));
        $this->getFunctionMock($namespace, 'ldap_parse_result')
            ->expects($this->exactly($times))->willReturn(true);
        $this->getFunctionMock($namespace, 'ldap_search')
            ->expects($this->exactly($times))->willReturnCallback($record('ldap_search', []));
        $this->getFunctionMock($namespace, 'ldap_get_entries')
            ->expects($this->exactly($times))->willReturn($entries);
    }
}
