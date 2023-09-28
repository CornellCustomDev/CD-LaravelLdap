<?php

namespace CornellCustomDev\LaravelLdap;

/**
 * An immutable data object representing the LDAP data returned for a user.
 */
readonly class LdapData
{
    public function __construct(
        public string  $netid,
        public string  $emplid,
        public ?string $first_name,
        public ?string $last_name,
        public ?string $display_name,
        public ?string $email,
        public ?string $campus_phone,
        public ?string $dept_name,
        public ?string $working_title,
        public ?string $primary_affiliation,
        public ?array  $affiliations,
        public ?array  $previous_netids,
        public ?array  $previous_emplids,
    ) {}

    /**
     * Create a new LdapData object from an array of LDAP data.
     */
    public static function make(array $data): ?LdapData
    {
        $first_name = ($data['cornelleduprefgivenname'] ?? null) ?: $data['givenName'] ?? null;
        $last_name = ($data['cornelleduprefsn'] ?? null) ?: $data['sn'] ?? null;
        $affiliations = is_array($data['cornelleduaffiliation'] ?? null)
            ? $data['cornelleduaffiliation']
            : [$data['cornelleduaffiliation'] ?? []];

        return new LdapData(
            netid: $data['uid'],
            emplid: $data['cornelleduemplid'],
            // Use preferred first name if it is not null, otherwise use givenName.
            first_name: $first_name,
            // Use preferred last name if it is not null, otherwise use sn.
            last_name: $last_name,
            // Use preferred display name if it is not null, otherwise fall back on first_name + last_name.
            display_name: $data['displayname'] ?? trim($first_name . ' ' . $last_name),
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
