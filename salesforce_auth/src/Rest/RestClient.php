<?php

namespace Drupal\salesforce_auth\Rest;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\salesforce_auth\SelectQuery;
use Drupal\salesforce_auth\SelectQueryResult;
use Drupal\salesforce_auth\SFID;
use Drupal\salesforce_auth\SObject;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;

/**
 * Objects, properties, and methods to communicate with the salesforce_auth REST API.
 */
class RestClient implements RestClientInterface {

    /**
     * Reponse object.
     *
     * @var \GuzzleHttp\Psr7\Response
     */
    public $response;

    /**
     * GuzzleHttp client.
     *
     * @var \GuzzleHttp\ClientInterface
     */
    protected $httpClient;

    /**
     * Config factory service.
     *
     * @var \Drupal\Core\Config\ConfigFactoryInterface
     */
    protected $configFactory;

    /**
     * salesforce_auth API URL.
     *
     * @var Drupal\Core\Url
     */
    protected $url;

    /**
     * salesforce_auth config entity.
     *
     * @var \Drupal\Core\Config\ImmutableConfig
     */
    protected $config;

    /**
     * The state service.
     *
     * @var \Drupal\Core\State\StateInterface
     */
    protected $state;

    /**
     * The cache service.
     *
     * @var Drupal\Core\Cache\CacheBackendInterface
     */
    protected $cache;

    /**
     * The JSON serializer service.
     *
     * @var \Drupal\Component\Serialization\Json
     */
    protected $json;

    const CACHE_LIFETIME = 300;
    const LONGTERM_CACHE_LIFETIME = 86400;

    /**
     * Constructor which initializes the consumer.
     *
     * @param \GuzzleHttp\ClientInterface $http_client
     *   The GuzzleHttp Client.
     * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
     *   The config factory service.
     * @param \Drupal\Core\State\StateInterface $state
     *   The state service.
     * @param \Drupal\Core\Cache\CacheBackendInterface $cache
     *   The cache service.
     * @param \Drupal\Component\Serialization\Json $json
     *   The JSON serializer service.
     */
    public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory, StateInterface $state, CacheBackendInterface $cache, Json $json) {
        $this->configFactory = $config_factory;
        $this->httpClient = $http_client;
        $this->config = $this->configFactory->get('salesforce_auth.settings');
        $this->state = $state;
        $this->cache = $cache;
        $this->json = $json;
        return $this;
    }

    /**
     * Determine if this SF instance is fully configured.
     */
    public function isAuthorized() {
        return $this->getConsumerKey() && $this->getConsumerSecret() && $this->getRefreshToken();
    }

    /**
     * {@inheritdoc}
     */
    public function apiCall($path, array $params = [], $method = 'GET', $returnObject = FALSE) {
        if (!$this->getAccessToken()) {
            $this->refreshToken();
        }

        if (strpos($path, '/') === 0) {
            $url = $this->getInstanceUrl() . $path;
        } else {
            $url = $this->getApiEndPoint() . $path;
        }

        try {
            $this->response = new RestResponse($this->apiHttpRequest($url, $params, $method));
        }
        catch (RequestException $e) {
            // RequestException gets thrown for any response status but 2XX.
            $this->response = $e->getResponse();

            // Any exceptions besides 401 get bubbled up.
            if (!$this->response || $this->response->getStatusCode() != 401) {
                throw new RestException($this->response, $e->getMessage(), $e->getCode(), $e);
            }
        }

        if ($this->response->getStatusCode() == 401) {
            // The session ID or OAuth token used has expired or is invalid: refresh
            // token. If refreshToken() throws an exception, or if apiHttpRequest()
            // throws anything but a RequestException, let it bubble up.
            $this->refreshToken();
            try {
                $this->response = new RestResponse($this->apiHttpRequest($url, $params, $method));
            }
            catch (RequestException $e) {
                $this->response = $e->getResponse();
                throw new RestException($this->response, $e->getMessage(), $e->getCode(), $e);
            }
        }

        if (empty($this->response) || ((int) floor($this->response->getStatusCode() / 100)) != 2) {
            throw new RestException($this->response, 'Unknown error occurred during API call');
        }

        if ($returnObject) {
            return $this->response;
        } else {
            return $this->response->data;
        }
    }

    /**
     * Private helper to issue an SF API request.
     *
     * @param string $url
     *   Fully-qualified URL to resource.
     *
     * @param array $params
     *   Parameters to provide.
     * @param string $method
     *   Method to initiate the call, such as GET or POST.  Defaults to GET.
     *
     * @return GuzzleHttp\Psr7\Response
     *   Response object.
     */
    protected function apiHttpRequest($url, array $params, $method) {
        if (!$this->getAccessToken()) {
            throw new \Exception('Missing OAuth Token');
        }

        $headers = [
            'Authorization' => 'OAuth ' . $this->getAccessToken(),
            'Content-type' => 'application/json',
        ];
        $data = NULL;
        if (!empty($params)) {
            $data = $this->json->encode($params);
        }
        return $this->httpRequest($url, $data, $headers, $method);
    }

    /**
     * Make the HTTP request. Wrapper around drupal_http_request().
     *
     * @param string $url
     *   Path to make request from.
     * @param string $data
     *   The request body.
     * @param array $headers
     *   Request headers to send as name => value.
     * @param string $method
     *   Method to initiate the call, such as GET or POST.  Defaults to GET.
     *
     * @throws RequestException
     *   Request exxception.
     *
     * @return GuzzleHttp\Psr7\Response
     *   Response object.
     */
    protected function httpRequest($url, $data = NULL, array $headers = [], $method = 'GET') {
        // Build the request, including path and headers. Internal use.
        return $this->httpClient->$method($url, ['headers' => $headers, 'body' => $data]);
    }

    /**
     * Extract normalized error information from a RequestException.
     *
     * @param RequestException $e
     *   Exception object.
     *
     * @return array
     *   Error array with keys:
     *   * message
     *   * errorCode
     *   * fields
     */
    protected function getErrorData(RequestException $e) {
        $response = $e->getResponse();
        $response_body = $response->getBody()->getContents();
        $data = $this->json->decode($response_body);
        if (!empty($data[0])) {
            $data = $data[0];
        }
        return $data;
    }

    /**
     * Get the API end point for a given type of the API.
     *
     * @param string $api_type
     *   E.g., rest, partner, enterprise.
     *
     * @return string
     *   Complete URL endpoint for API access.
     */
    public function getApiEndPoint($api_type = 'rest') {
        $url = &drupal_static(__FUNCTION__ . $api_type);
        if (!isset($url)) {
            $identity = $this->getIdentity();
            if (is_string($identity)) {
                $url = $identity;
            } elseif (isset($identity['urls'][$api_type])) {
                $url = $identity['urls'][$api_type];
            }
            $url = str_replace('{version}', $this->getApiVersion(), $url);
        }
        return $url;
    }

    /**
     * Wrapper for config rest_api_version.version
     */
    public function getApiVersion() {
        if ($this->config->get('use_latest')) {
            $versions = $this->getVersions();
            $version = end($versions);
            return $version['version'];
        }
        return $this->config->get('rest_api_version.version');
    }

    /**
     * Getter for consumer_key
     */
    public function getConsumerKey() {
        $this->state->get('salesforce_auth.salesforce_consumer_key');
    }

    /**
     * Setter for consumer_key
     */
    public function setConsumerKey($value) {
        return $this->state->set('salesforce_auth.salesforce_consumer_key', $value);
    }

    public function getConsumerSecret() {
        return $this->state->get('salesforce_auth.salesforce_consumer_secret');
    }

    public function setConsumerSecret($value) {
        return $this->state->set('salesforce_auth.salesforce_consumer_secret', $value);
    }

    public function getLoginUrl() {
        $login_url = $this->state->get('salesforce_auth.salesforce_login_uri');
        return empty($login_url) ? 'https://login.salesforce_auth.com' : $login_url;
    }

    public function setLoginUrl($value) {
        return $this->state->set('salesforce_auth.salesforce_login_uri', $value);
    }

    /**
     * Get the SF instance URL. Useful for linking to objects.
     */
    public function getInstanceUrl() {
        return $this->state->get('salesforce_auth.instance_url');
    }

    /**
     * Set the SF instance URL.
     *
     * @param string $url
     *   URL to set.
     */
    protected function setInstanceUrl($url) {
        $this->state->set('salesforce_auth.instance_url', $url);
        return $this;
    }

    /**
     * Get the access token.
     */
    public function getAccessToken() {
        $access_token = $this->state->get('salesforce_auth.access_token');
        return isset($access_token) && Unicode::strlen($access_token) !== 0 ? $access_token : FALSE;
    }

    /**
     * Set the access token.
     *
     * @param string $token
     *   Access token from salesforce_auth.
     */
    public function setAccessToken($token) {
        $this->state->set('salesforce_auth.access_token', $token);
        return $this;
    }

    /**
     * Get refresh token.
     */
    protected function getRefreshToken() {
        return $this->state->get('salesforce_auth.refresh_token');
    }

    /**
     * Set refresh token.
     *
     * @param string $token
     *   Refresh token from salesforce_auth.
     */
    protected function setRefreshToken($token) {
        $this->state->set('salesforce_auth.refresh_token', $token);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function refreshToken() {
        $refresh_token = $this->getRefreshToken();
        if (empty($refresh_token)) {
            throw new \Exception(t('There is no refresh token.'));
        }

        $data = UrlHelper::buildQuery([
            'grant_type' => 'refresh_token',
            'refresh_token' => urldecode($refresh_token),
            'client_id' => $this->getConsumerKey(),
            'client_secret' => $this->getConsumerSecret(),
        ]);

        $url = $this->getAuthTokenUrl();
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
        $response = $this->httpRequest($url, $data, $headers, 'POST');

        $this->handleAuthResponse($response);
        return $this;
    }

    /**
     * Helper callback for OAuth handshake, and refreshToken()
     *
     * @param GuzzleHttp\Psr7\Response $response
     *   Response object from refreshToken or authToken endpoints.
     *
     * @see salesforceController::oauthCallback()
     * @see self::refreshToken()
     */
    public function handleAuthResponse(Response $response) {
        if ($response->getStatusCode() != 200) {
            throw new \Exception($response->getReasonPhrase(), $response->getStatusCode());
        }

        $data = (new RestResponse($response))->data;
        
        // Do not overwrite an existing refresh token with an empty value.
        if (!empty($data['refresh_token'])) {
            $this->setRefreshToken($data['refresh_token']);
        }
        
        $this->setAccessToken($data['access_token']);
        $this->initializeIdentity($data['id']);
        $this->setInstanceUrl($data['instance_url']);
        return $data;
    }

    /**
     * Retrieve and store the salesforce_auth identity given an ID url.
     *
     * @param string $id
     *   Identity URL.
     *
     * @throws Exception
     */
    public function initializeIdentity($id) {
        $headers = [
            'Authorization' => 'Bearer '.$this->getAccessToken(),
            'Content-type' => 'application/json',
        ];
        $response = $this->httpRequest($id, NULL, $headers);
        if ($response->getStatusCode() != 200) {
            throw new \Exception(t('Unable to access identity service.'), $response->getStatusCode());
        }
        $data = (new RestResponse($response))->data;
        $this->setIdentity($data);
        return $data;
    }

    /**
     * Setter for identity state info.
     *
     * @return $this
     */
    protected function setIdentity($data) {
        $this->state->set('salesforce_auth.identity', $data);
        return $this;
    }

    /**
     * Return the salesforce_auth identity, which is stored in a variable.
     *
     * @return array
     *   Returns FALSE is no identity has been stored.
     */
    public function getIdentity() {
        return $this->state->get('salesforce_auth.identity');
    }

    /**
     * Helper to build the redirect URL for OAUTH workflow.
     *
     * @return string
     *   Redirect URL.
     *
     * @see Drupal\salesforce_auth\Controller\salesforce_authController
     */
    public function getAuthCallbackUrl() {
        return Url::fromRoute('salesforce_auth.oauth_callback', [], [
            'absolute' => TRUE,
            'https' => TRUE,
        ])->toString();
    }

    /**
     * Get salesforce_auth oauth login endpoint. (OAuth step 1)
     *
     * @return string
     *   REST OAuth Login URL.
     */
    public function getAuthEndpointUrl() {
        return $this->getLoginUrl() . '/services/oauth2/authorize';
    }

    /**
     * Get salesforce_auth oauth token endpoint. (OAuth step 2)
      *
     * @return string
     *   REST OAuth Token URL.
     */
    public function getAuthTokenUrl() {
        return $this->getLoginUrl() . '/services/oauth2/token';
    }

    /**
     * Wrapper for "Versions" resource to list information about API releases.
     *
     * @param $reset
     *   Whether to reset cache.
     *
     * @return array
     *   Array of all available salesforce_auth versions.
     */
    public function getVersions($reset = FALSE) {
        $cache = $this->cache->get('salesforce_auth:versions');

        // Force the recreation of the cache when it is older than 24 hours.
        if ($cache && $this->getRequestTime() < ($cache->created + self::LONGTERM_CACHE_LIFETIME) && !$reset) {
            return $cache->data;
        }

        $versions = [];
        $id = $this->getIdentity();
        $url = str_replace('v{version}/', '', $id['urls']['rest']);
        $response = new RestResponse($this->httpRequest($url));
        foreach ($response->data as $version) {
            $versions[$version['version']] = $version;
        }
        $this->cache->set('salesforce_auth:versions', $versions, 0, ['salesforce_auth']);
        return $versions;
    }

    /**
     * @defgroup salesforce_auth_apicalls Wrapper calls around core apiCall()
     */

    /**
     * Available objects and their metadata for your organization's data.
     *
     * @param array $conditions
     *   Associative array of filters to apply to the returned objects. Filters
     *   are applied after the list is returned from salesforce_auth.
     * @param bool $reset
     *   Whether to reset the cache and retrieve a fresh version from salesforce_auth.
     *
     * @return array
     *   Available objects and metadata.
     *
     * @addtogroup salesforce_auth_apicalls
     */
    public function objects(array $conditions = ['updateable' => TRUE], $reset = FALSE) {
        $cache = $this->cache->get('salesforce_auth:objects');

        // Force the recreation of the cache when it is older than 5 minutes.
        if ($cache && $this->getRequestTime() < ($cache->created + self::CACHE_LIFETIME) && !$reset) {
            $result = $cache->data;
        } else {
            $result = $this->apiCall('sobjects');
            $this->cache->set('salesforce_auth:objects', $result, 0, ['salesforce_auth']);
        }

        if (!empty($conditions)) {
            foreach ($result['sobjects'] as $key => $object) {
                foreach ($conditions as $condition => $value) {
                    if (!$object[$condition] == $value) {
                        unset($result['sobjects'][$key]);
                    }
                }
            }
        }

        return $result['sobjects'];
    }

    /**
     * Use SOQL to get objects based on query string.
     *
     * @param SelectQuery $query
     *   The constructed SOQL query.
     *
     * @return SelectQueryResult
     *   Query result object.
     *
     * @addtogroup salesforce_auth_apicalls
     */
    public function query(SelectQuery $query) {
        // $this->moduleHandler->alter('salesforce_auth_query', $query);
        // Casting $query as a string calls SelectQuery::__toString().
        return new SelectQueryResult($this->apiCall('query?q=' . (string) $query));
    }

    /**
     * Given a select query result, fetch the next results set, if it exists.
     *
     * @param SelectQueryResult $results
     *   The query result which potentially has more records
     * @return SelectQueryResult
     *   If there are no more results, $results->records will be empty.
     */
    public function queryMore(SelectQueryResult $results) {
        if ($results->done()) {
            return new SelectQueryResult([
                'totalSize' => $results->size(),
                'done' => TRUE,
                'records' => [],
            ]);
        }
        $version_path = parse_url($this->getApiEndPoint(), PHP_URL_PATH);
        $next_records_url = str_replace($version_path, '', $results->nextRecordsUrl());
        return new SelectQueryResult($this->apiCall($next_records_url));
    }

    /**
     * Retrieve all the metadata for an object.
     *
     * @param string $name
     *   Object type name, E.g., Contact, Account, etc.
     * @param bool $reset
     *   Whether to reset the cache and retrieve a fresh version from salesforce_auth.
     *
     * @return RestResponse_Describe
     *   salesforce_auth object description object.
     *
     * @addtogroup salesforce_auth_apicalls
     */
    public function objectDescribe($name, $reset = FALSE) {
        if (empty($name)) {
            throw new \Exception('No name provided to describe');
        }

        $cache = $this->cache->get('salesforce_auth:object:' . $name);
        // Force the recreation of the cache when it is older than 5 minutes.
        if ($cache && $this->getRequestTime() < ($cache->created + self::CACHE_LIFETIME) && !$reset) {
            return $cache->data;
        } else {
            $response = new RestResponse_Describe($this->apiCall("sobjects/{$name}/describe", [], 'GET', TRUE));
            $this->cache->set('salesforce_auth:object:' . $name, $response, 0, ['salesforce_auth']);
            return $response;
        }
    }

    /**
     * Create a new object of the given type.
     *
     * @param string $name
     *   Object type name, E.g., Contact, Account, etc.
     * @param array $params
     *   Values of the fields to set for the object.
     *
     * @return Drupal\salesforce_auth\SFID
     *   salesforce_auth ID object.
     *
     * @addtogroup salesforce_auth_apicalls
     */
    public function objectCreate($name, array $params) {
        $response = $this->apiCall("sobjects/{$name}", $params, 'POST', TRUE);
        $data = $response->data;
        return new SFID($data['id']);
    }

    /**
     * Create new records or update existing records.
     *
     * The new records or updated records are based on the value of the specified
     * field.  If the value is not unique, REST API returns a 300 response with
     * the list of matching records and throws an Exception.
     *
     * @param string $name
     *   Object type name, E.g., Contact, Account.
     * @param string $key
     *   The field to check if this record should be created or updated.
     * @param string $value
     *   The value for this record of the field specified for $key.
     * @param array $params
     *   Values of the fields to set for the object.
     *
     * @return mixed
     *   Drupal\salesforce_auth\SFID or NULL.
     *
     * @addtogroup salesforce_auth_apicalls
     */
    public function objectUpsert($name, $key, $value, array $params) {
        // If key is set, remove from $params to avoid UPSERT errors.
        if (isset($params[$key])) {
            unset($params[$key]);
        }

        $response = $this->apiCall("sobjects/{$name}/{$key}/{$value}", $params, 'PATCH', TRUE);

        // On update, upsert method returns an empty body. Retreive object id, so that we can return a consistent response.
        if ($response->getStatusCode() == 204) {
            // We need a way to allow callers to distinguish updates and inserts. To
            // that end, cache the original response and reset it after fetching the
            // ID.
            $this->original_response = $response;
            $sf_object = $this->objectReadbyExternalId($name, $key, $value);
            return $sf_object->id();
        }
        $data = $response->data;
        return new SFID($data['id']);
    }

    /**
     * Update an existing object.
     *
     * Update() doesn't return any data. Examine HTTP response or Exception.
     *
     * @param string $name
     *   Object type name, E.g., Contact, Account.
     * @param string $id
     *   salesforce_auth id of the object.
     * @param array $params
     *   Values of the fields to set for the object.
     *
     * @addtogroup salesforce_auth_apicalls
     */
    public function objectUpdate($name, $id, array $params) {
        $this->apiCall("sobjects/{$name}/{$id}", $params, 'PATCH');
    }

    /**
     * Return a full loaded salesforce_auth object.
     *
     * @param string $name
     *   Object type name, E.g., Contact, Account.
     * @param string $id
     *   salesforce_auth id of the object.
     *
     * @return SObject
     *   Object of the requested salesforce_auth object.
     *
     * @addtogroup salesforce_auth_apicalls
     */
    public function objectRead($name, $id) {
        return new SObject($this->apiCall("sobjects/{$name}/{$id}"));
    }

    /**
     * Return a full loaded salesforce_auth object from External ID.
     *
     * @param string $name
     *   Object type name, E.g., Contact, Account.
     * @param string $field
     *   salesforce_auth external id field name.
     * @param string $value
     *   Value of external id.
     *
     * @return SObject
     *   Object of the requested salesforce_auth object.
     *
     * @addtogroup salesforce_auth_apicalls
     */
    public function objectReadbyExternalId($name, $field, $value) {
        return new SObject($this->apiCall("sobjects/{$name}/{$field}/{$value}"));
    }

    /**
     * Delete a salesforce_auth object.
     *
     * Note: if Object with given $id doesn't exist,
     * objectDelete() will assume success unless $throw_exception is given.
     * Delete() doesn't return any data. Examine HTTP response or Exception.
     *
     * @param string $name
     *   Object type name, E.g., Contact, Account.
     * @param string $id
     *   salesforce_auth id of the object.
     * @param bool $throw_exception
     *   (optional) If TRUE, 404 response code will cause RequestException to be
     *   thrown. Otherwise, hide those errors. Default is FALSE.
     *
     * @addtogroup salesforce_auth_apicalls
     */
    public function objectDelete($name, $id, $throw_exception = FALSE) {
        try {
            $this->apiCall("sobjects/{$name}/{$id}", [], 'DELETE');
        }
        catch (RequestException $e) {
            if ($throw_exception || $e->getResponse()->getStatusCode() != 404) {
                throw $e;
            }
        }
    }

    /**
     * Retrieves the list of individual objects that have been deleted within the
     * given timespan for a specified object type.
     *
     * @param string $type
     *   Object type name, E.g., Contact, Account.
     * @param string $startDate
     *   Start date to check for deleted objects (in ISO 8601 format).
     * @param string $endDate
     *   End date to check for deleted objects (in ISO 8601 format).
     * @return GetDeletedResult
     */
    public function getDeleted($type, $startDate, $endDate) {
        return $this->apiCall("sobjects/{$type}/deleted/?start={$startDate}&end={$endDate}");
    }

    /**
     * Return a list of available resources for the configured API version.
     *
     * @return Drupal\salesforce_auth\Rest\RestResponse_Resources
     *
     * @addtogroup salesforce_auth_apicalls
     */
    public function listResources() {
        return new RestResponse_Resources($this->apiCall('', [], 'GET', TRUE));
    }

    /**
     * Return a list of SFIDs for the given object, which have been created or
     * updated in the given timeframe.
     *
     * @param string $name
     *   Object type name, E.g., Contact, Account.
     * @param int $start
     *   Unix timestamp for older timeframe for updates.
     *   Defaults to "-29 days" if empty.
     * @param int $end
     *   Unix timestamp for end of timeframe for updates.
     *   Defaults to now if empty.
     *
     * @return array
     *   return array has 2 indexes:
     *     "ids": a list of SFIDs of those records which have been created or
     *       updated in the given timeframe.
     *     "latestDateCovered": ISO 8601 format timestamp (UTC) of the last date
     *       covered in the request.
     *
     * @see https://developer.salesforce_auth.com/docs/atlas.en-us.api_rest.meta/api_rest/resources_getupdated.htm
     *
     * @addtogroup salesforce_auth_apicalls
     */
    public function getUpdated($name, $start = NULL, $end = NULL) {
        if (empty($start)) {
            $start = strtotime('-29 days');
        }
        $start = urlencode(gmdate(DATE_ATOM, $start));

        if (empty($end)) {
            $end = time();
        }
        $end = urlencode(gmdate(DATE_ATOM, $end));

        return $this->apiCall("sobjects/{$name}/updated/?start=$start&end=$end");
    }

    /**
     * Retrieve all record types for this org. If $name is provided, retrieve
     * record types for the given object type only.
     *
     * @param string $name
     *   Object type name, e.g. Contact, Account, etc.
     *
     * @return array
     *   If $name is given, an array of record types indexed by developer name.
     *   Otherwise, an array of record type arrays, indexed by object type name.
     */
    public function getRecordTypes($name = NULL, $reset = FALSE) {
        $cache = $this->cache->get('salesforce_auth:record_types');

        // Force the recreation of the cache when it is older than CACHE_LIFETIME
        if ($cache && $this->getRequestTime() < ($cache->created + self::CACHE_LIFETIME) && !$reset) {
            $record_types = $cache->data;
        } else {
            $query = new SelectQuery('RecordType');
            $query->fields = array('Id', 'Name', 'DeveloperName', 'SobjectType');
            $result = $this->query($query);
            $record_types = array();
            foreach ($result->records() as $rt) {
                $record_types[$rt->field('SobjectType')][$rt->field('DeveloperName')] = $rt;
            }
            $this->cache->set('salesforce_auth:record_types', $record_types, 0, ['salesforce_auth']);
        }

        if ($name != NULL) {
            if (!isset($record_types[$name])) {
                throw new \Exception("No record types for $name");
            }
            return $record_types[$name];
        }
        return $record_types;
    }

    /**
     * Given a DeveloperName and SObject Name, return the SFID of the
     * corresponding RecordType. DeveloperName doesn't change between salesforce_auth
     * environments, so it's safer to rely on compared to SFID.
     *
     * @param string $name
     *   Object type name, E.g., Contact, Account.
     * @param string $devname
     *   RecordType DeveloperName, e.g. Donation, Membership, etc.
     * @return SFID
     *   The salesforce_auth ID of the given Record Type, or null.
     *
     * @throws Exception if record type not found
     */
    public function getRecordTypeIdByDeveloperName($name, $devname, $reset = FALSE) {
        $record_types = $this->getRecordTypes();
        if (empty($record_types[$name][$devname])) {
            throw new \Exception("No record type $devname for $name");
        }
        return $record_types[$name][$devname]->id();
    }

    /**
     * Utility function to determine object type for given SFID.
     *
     * @param SFID $id
     *   salesforce_auth object ID.
     *
     * @return string
     *   Object type's name.
     *
     * @throws Exception
     *   If SFID doesn't match any object type.
     */
    public function getObjectTypeName(SFID $id) {
        $prefix = substr((string)$id, 0, 3);
        $describe = $this->objects();
        foreach ($describe as $object) {
            if ($prefix == $object['keyPrefix']) {
                return $object['name'];
            }
        }
        throw new \Exception('No matching object type');
    }

    /**
     * Returns REQUEST_TIME.
     *
     * @return string
     *   The REQUEST_TIME server variable.
     */
    protected function getRequestTime() {
        return defined('REQUEST_TIME') ? REQUEST_TIME : (int) $_SERVER['REQUEST_TIME'];
    }
}
