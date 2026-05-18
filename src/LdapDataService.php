<?php

namespace CornellCustomDev\LaravelStarterKit\Ldap;

use Exception;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use LDAP\Connection;

class LdapDataService
{
    public const LDAP_CACHE_SECONDS = 300;

    public function __construct(
        private readonly Ldap $ldap,
        private readonly int $cache_seconds = self::LDAP_CACHE_SECONDS,
    ) {}

    /**
     * Retrieve the instance of LdapDataService from the service container.
     */
    public static function make(): LdapDataService
    {
        return app(LdapDataService::class);
    }

    /**
     * Get a cached result for LdapDataService::make()->find($netid).
     *
     * @throws InvalidArgumentException
     * @throws LdapDataException
     */
    public static function get(?string $netid, bool $bust_cache = false): ?LdapData
    {
        if (empty($netid)) {
            throw new InvalidArgumentException(LdapDataService::class.'::get requires netid');
        }

        $cache_key = LdapDataService::class.'::get_'.$netid;
        if ($bust_cache) {
            Cache::forget($cache_key);
        }

        $ldap_service = LdapDataService::make();

        return Cache::remember($cache_key, now()->addSeconds($ldap_service->cache_seconds), fn () => $ldap_service->find($netid));
    }

    /**
     * Find a user in the LDAP directory, returning an LdapData object for the first entry found.
     *
     * @throws LdapDataException
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
            throw new LdapDataException($e->getMessage());
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
     * @throws LdapDataException
     */
    private function makeBindConnection(): bool|Connection
    {
        $connection = $this->ldap->connect();
        if (! $connection) {
            throw new LdapDataException('Could not connect to LDAP server.');
        }

        $result = $this->ldap->bind($connection);
        if (! $result) {
            throw new LdapDataException('Could not bind to LDAP server.');
        }

        $parsed_result = $this->ldap->parse_result($connection, $result);
        if ($parsed_result !== true) {
            throw new LdapDataException("Error response from ldap_bind: $parsed_result");
        }

        return $connection;
    }
}
