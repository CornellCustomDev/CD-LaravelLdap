<?php

namespace CornellCustomDev\LaravelStarterKit\Ldap;

use LDAP\Connection;
use LDAP\Result;
use LDAP\ResultEntry;

/**
 * Wrappers for standard LDAP PHP extension functions used from the LdapDataService class.
 *
 * This class allows us to provide some simplifications and testability for the LdapDataService class.
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
        if (! $result) {
            return null;
        }

        $result_entry = $this->first_entry($connection, $result);
        if (! $result_entry) {
            return null;
        }

        return $this->get_attributes($connection, $result_entry);
    }

    public function connect(): bool|Connection
    {
        return ldap_connect($this->ldap_server);
    }

    public function bind($ldap): bool|Result
    {
        return ldap_bind_ext($ldap, "uid=$this->ldap_user", $this->ldap_pass);
    }

    public function search($ldap, string $base_dn, string $filter): array|bool|Result
    {
        return ldap_search($ldap, $base_dn, $filter);
    }

    public function first_entry($ldap, $result): bool|ResultEntry
    {
        return ldap_first_entry($ldap, $result);
    }

    public function get_attributes($ldap, $entry): array
    {
        return ldap_get_attributes($ldap, $entry);
    }

    public function parse_result($ldap, $result): bool|string
    {
        return ldap_parse_result($ldap, $result, $error_code, $matched_dn, $error_message) ?: $error_message;
    }
}
