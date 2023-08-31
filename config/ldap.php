<?php

return [
    'user' => env('LDAP_USER'),
    'pass' => env('LDAP_PASS'),
    'server' => env('LDAP_SERVER', 'ldaps://query.directory.cornell.edu'),
    'base_dn' => env('LDAP_BASE_DN', 'ou=People,o=Cornell University,c=US'),
    'cache_seconds' => env('LDAP_CACHE_SECONDS', 300),
];
