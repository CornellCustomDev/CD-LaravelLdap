<?php

namespace CornellCustomDev\LaravelLdap;

use Exception;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use LDAP\Connection;

class LdapService
{
    public function __construct(
        private readonly string $ldap_user,
        private readonly string $ldap_pass,
        private readonly string $ldap_server,
        private readonly string $ldap_base_dn,
        private readonly int $cache_seconds,
    ) {}

    /**
     * Retrieve the instance of LdapService from the service container.
     */
    public static function make(): LdapService
    {
        return app(LdapService::class);
    }

    /**
     * Get a cached result for LdapService::make()->find($netid).
     *
     * @throws InvalidArgumentException
     * @throws LdapServiceException
     */
    public static function get(?string $netid, bool $bust_cache = false): ?LdapData
    {
        if (empty($netid)) {
            throw new InvalidArgumentException(LdapService::class . '::get requires netid');
        }

        $cache_key = LdapService::class . '::get_' . $netid;
        if ($bust_cache) {
            Cache::forget($cache_key);
        }

        $ldap_service = LdapService::make();

        return Cache::remember($cache_key, now()->addSeconds($ldap_service->cache_seconds), fn() => $ldap_service->find($netid));
    }

    /**
     * Find a user in the LDAP directory, returning an LdapData object for the first entry found.
     *
     * @throws LdapServiceException
     */
    public function find(string $netid, ?bool $debug = false): ?LdapData
    {
        $connection = $this->makeConnection();
        if (!$connection) {
            return null;
        }

        try {
            $response = $this->search($connection, $netid);
            if ($debug) {
                dump(json_encode($response));
            }
            $data = self::parseResponse($response);
        } catch (Exception $e) {
            // $this->logError("Error in ldap_search for $netid: " . $e->getMessage());
            throw new LdapServiceException($e->getMessage());
        }

        // Load the data into an immutable object.
        return LdapData::make($data);
    }

    /**
     * Make a connection to the LDAP server.
     *
     * @throws LdapServiceException
     */
    private function makeConnection(): ?Connection
    {
        $ldap = ldap_connect($this->ldap_server);
        if (!$ldap) {
            // @TODO: Throw a configuration exception
            return null;
        }

//        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
//        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);

        $result = ldap_bind_ext($ldap, "uid=$this->ldap_user", $this->ldap_pass);
        if (!$result) {
            // @TODO: Throw a specific error
            return null;
        }

        if (!ldap_parse_result($ldap, $result, $errcode, $matcheddn, $errmsg, $referrals, $controls)) {
            // $this->logError("Error response from ldap_bind: " . $e->getMessage());
            throw new LdapServiceException($errmsg);
        }

        return $ldap;
    }

    /**
     * Search for a user in the LDAP directory, returning the attributes for the first entry.
     */
    private function search(Connection $ldap, string $netid): ?array
    {
        $result = ldap_search($ldap, $this->ldap_base_dn, "uid=$netid");
        if (!$result) {
            return null;
        }

        $result_entry = ldap_first_entry($ldap, $result);
        if (!$result_entry) {
            return null;
        }

        return ldap_get_attributes($ldap, $result_entry);
    }

    /**
     * Parse a response from ldap_search into a simple array.
     */
    public static function parseResponse(array $response): array
    {
        $data = [];

        foreach ($response as $key => $value) {
            if (is_numeric($key) || $key == 'count') {
                continue;
            }
            if ($value['count'] == 1) {
                $parsed_value = $value[0];
            } else {
                unset($value['count']);
                $parsed_value = $value;
            }
            // Only populate the field if we have data.
            if (!empty($parsed_value)) {
                $data[$key] = $parsed_value;
            }
        }

        return $data;
    }
}
