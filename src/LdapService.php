<?php

namespace CornellCustomDev\LaravelLdap;

use Exception;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use LDAP\Connection;

class LdapService
{
    public const LDAP_CACHE_SECONDS = 300;

    public function __construct(
        private readonly Ldap $ldap,
        private readonly int $cache_seconds = self::LDAP_CACHE_SECONDS,
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
            throw new InvalidArgumentException(LdapService::class.'::get requires netid');
        }

        $cache_key = LdapService::class.'::get_'.$netid;
        if ($bust_cache) {
            Cache::forget($cache_key);
        }

        $ldap_service = LdapService::make();

        return Cache::remember($cache_key, now()->addSeconds($ldap_service->cache_seconds), fn () => $ldap_service->find($netid));
    }

    /**
     * Find a user in the LDAP directory, returning an LdapData object for the first entry found.
     *
     * @throws LdapServiceException
     */
    public function find(string $netid, ?bool $debug = false): ?LdapData
    {
        $connection = $this->makeBindConnection();

        try {
            $response = $this->ldap->getFirst($connection, "uid=$netid");
            if ($debug) {
                dump(json_encode($response));
            }
            if (! $response) {
                return null;
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
     * Parse a response from ldap_search into a simple array.
     */
    public static function parseResponse(?array $response = []): array
    {
        unset($response['dn']);
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
            if (! empty($parsed_value)) {
                $data[$key] = $parsed_value;
            }
        }

        return $data;
    }

    /**
     * @throws LdapServiceException
     */
    private function makeBindConnection(): bool|Connection
    {
        $connection = $this->ldap->connect();
        if (! $connection) {
            throw new LdapServiceException('Could not connect to LDAP server.');
        }

        $result = $this->ldap->bind($connection);
        if (! $result) {
            throw new LdapServiceException('Could not bind to LDAP server.');
        }

        $parsed_result = $this->ldap->parse_result($connection, $result);
        if ($parsed_result !== true) {
            throw new LdapServiceException("Error response from ldap_bind: $parsed_result");
        }

        return $connection;
    }
}
