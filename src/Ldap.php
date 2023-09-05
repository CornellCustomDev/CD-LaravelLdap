<?php

namespace CornellCustomDev\LaravelLdap;

use LDAP\Connection;
use LDAP\Result;
use LDAP\ResultEntry;

/**
 * Provides wrappers for standard LDAP PHP extension functions that we use in the LdapService class.
 */
class Ldap
{
    public function __construct(
        private readonly string $ldap_user,
        private readonly string $ldap_pass,
        private readonly string $ldap_server,
        private readonly string $ldap_base_dn,
    ) {}

    /**
     * Search for a user in the LDAP directory, returning the attributes for the first entry.
     */
    public function getFirst($connection, $filter): ?array
    {
        $result = $this->search($connection, $this->ldap_base_dn, $filter);
        if (!$result) {
            return null;
        }

        $result_entry = $this->first_entry($connection, $result);
        if (!$result_entry) {
            return null;
        }

        return $this->get_attributes($connection, $result_entry);
    }

    public function connect(): false|Connection
    {
        return ldap_connect($this->ldap_server);
    }

    /**
     * @throws LdapServiceException
     */
    public function bind($ldap): false|Result
    {
        $result = ldap_bind_ext($ldap, "uid=$this->ldap_user", $this->ldap_pass);
        if (!$result) {
            throw new LdapServiceException('Could not bind to LDAP server.');
        }

        $parsed_result = ldap_parse_result($ldap, $result, $errcode, $matcheddn, $errmsg, $referrals, $controls);
        if ($parsed_result !== true) {
            throw new LdapServiceException('Error response from ldap_bind: ' . $parsed_result);
        }

        return $result;
    }

    public function search($ldap, string $base_dn, string $filter): array|false|Result
    {
        return ldap_search($ldap, $base_dn, $filter);
    }

    public function first_entry($ldap, $result): false|ResultEntry
    {
        return ldap_first_entry($ldap, $result);
    }

    public function get_attributes($ldap, $entry): array
    {
        return ldap_get_attributes($ldap, $entry);
    }
}
