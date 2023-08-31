<?php

namespace CornellCustomDev\LaravelLdap;

/**
 * An immutable data object representing the LDAP data for a user.
 */
class LdapData
{
    public function __construct(
        public readonly string $netid,
        public readonly string $emplid,
        public readonly ?string $first_name,
        public readonly ?string $last_name,
        public readonly ?string $email,
        public readonly ?string $campus_phone,
        public readonly ?string $dept_name,
        public readonly ?string $working_title,
        public readonly ?string $primary_affiliation,
        public readonly ?array $affiliations,
        public readonly ?array $previous_netids,
        public readonly ?array $previous_emplids,
    ) {}

    /**
     * Create a new LdapData object from an array of LDAP data.
     */
    public static function make(array $data): ?LdapData
    {
        $affiliations = is_array($data['cornelleduaffiliation'] ?? null)
            ? $data['cornelleduaffiliation']
            : [$data['cornelleduaffiliation'] ?? []];
        return new LdapData(
            netid: $data['uid'],
            emplid: $data['cornelleduemplid'],
            // Use preferred first name if it is not null, otherwise use givenName.
            first_name: ($data['cornelleduprefgivenname'] ?? null) ?: $data['givenName'] ?? null,
            // Use preferred last name if it is not null, otherwise use sn.
            last_name: ($data['cornelleduprefsn'] ?? null) ?: $data['sn'] ?? null,
            // Only set 'email' if it is not empty.
            email: ($data['mail'] ?? null) ?: null,
            campus_phone: $data['cornelleducampusphone'] ?? null,
            dept_name: $data['cornelledudeptname1'] ?? null,
            working_title: $data['cornelleduwrkngtitle1'] ?? null,
            primary_affiliation: $data['cornelleduprimaryaffiliation'] ?? null,
            affiliations: $affiliations,
            previous_netids: $data['cornelledupreviousnetids'] ?? null,
            previous_emplids: $data['cornelledupreviousemplids'] ?? null,
        );
    }
}
