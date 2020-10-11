<?php

namespace CLSystems\Tradedoubler;

/**
 * Tradedoubler.
 *
 * Tradedoubler OpenAPI client library {@link http://dev.tradedoubler.com}.
 *
 * <code>
 *
 * ...
 *
 * $token = ...;
 * $client = new \CLSystems\Tradedoubler\Client($token);
 *
 * $params['language'] = 'es';
 * $response = $tradedoubler->getServiceData('advertisers.products.feed', $params);
 *
 * ...
 *
 * </code>
 */
class Client
{
	/**
	 *
	 */
	const BASE_URL = 'http://api.tradedoubler.com/';
	const VERSION  = '1.0';
	const FORMAT   = '.json';

	/** @var string $token Token to authenticate requests. */
	private $token;

	/** @var string $error Last error thrown during request. */
	private $error;

	/** @var array $services Array with current available services. */
	static $services = [
		'advertisers' => [
			'claims'      => [
				'list'   => '/claims',
				'update' => '/claimUpdates',
				'status' => '/claimStatuses',
			],
			'conversions' => [
				'create' => '/conversions/subscriptions',
				'update' => '/conversions/subscriptions',
				'delete' => '/conversions/subscriptions',
				'list'   => '/conversions/subscriptions',
			],
			'products'    => [
				'create'     => '/products',
				'delete'     => '/products',
				'query'      => '/products',
				'unlimited'  => '/productsUnlimited',
				'categories' => '/productCategories',
				'feed'       => '/productFeeds',
			],
			'vouchers'    => [
				'query' => '/vouchers',
			],
		],
		'publishers'  => [
			'products' => [
				'create'     => '/products',
				'delete'     => '/products',
				'query'      => '/products',
				'unlimited'  => '/productsUnlimited',
				'categories' => '/productCategories',
				'feed'       => '/productFeeds',
			],
			'vouchers'    => [
				'query' => '/vouchers',
			],
		],
	];

	/**
	 * Constructor.
	 *
	 * @param string $token The API token for making request with.
	 */
	public function __construct(string $token)
	{
		$this->token = $token;
	}

	/**
	 * Makes request to Tradedoubler's API.
	 *
	 * Example code:
	 * ---------------------------------------------------------------------------
	 *
	 * <code>
	 *  ...
	 *  $params['language'] = 'es';
	 *  $tradedoubler->getServiceData('advertisers.products.categories', $params);
	 * </code>
	 *
	 * Available services:
	 * ---------------------------------------------------------------------------
	 *
	 * <ul>
	 *  <li>advertises.claims.list</li>
	 *  <li>advertises.claims.update</li>
	 *  <li>advertises.claims.status</li>
	 * </ul>
	 *
	 * @param string $service The path for the service.
	 * @param array $params Optional array with parameters to be sent.
	 * @param string $method HTTP method to be used. Defaults to GET.
	 * @return    mixed                          Returns an array with the response. If any error
	 *                                           occurred, it will return <code>null</code> and you can access
	 *                                           the message by calling <code>getError()</code> on the instance.
	 */
	public function getServiceData(string $service, $params = [], $method = 'GET')
	{
		$method = strtoupper($method);
		$endpoint = $this->getServiceEndpoint($service);

		if ($endpoint)
		{
			$ch = curl_init();

			$opts[CURLOPT_RETURNTRANSFER] = true;
			$opts[CURLOPT_FRESH_CONNECT] = true;

			// choose which method to use.
			switch ($method)
			{
				case 'PUT':
					$opts[CURLOPT_HTTPHEADER] = ['Content-type: application/json'];
					$opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
					$opts[CURLOPT_POSTFIELDS] = json_encode($params);
					$opts[CURLOPT_URL] = $this->buildEndpointURL($endpoint);
				break;
				case 'POST':
					$opts[CURLOPT_HTTPHEADER] = ['Content-type: application/json'];
					$opts[CURLOPT_POST] = true;
					$opts[CURLOPT_POSTFIELDS] = json_encode($params);
					$opts[CURLOPT_URL] = $this->buildEndpointURL($endpoint);
				break;
				case 'DELETE':
					$opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
					$opts[CURLOPT_URL] = $this->buildEndpointURL($endpoint, $params);
				break;
				case 'GET':
				default:
					$opts[CURLOPT_HTTPGET] = true;
					$opts[CURLOPT_URL] = $this->buildEndpointURL($endpoint, $params);
			}

			curl_setopt_array($ch, $opts);

			$json = null;
			$resp = curl_exec($ch);

			if ($resp !== false)
			{
				$json = json_decode($resp, true);
			}
			else
			{
				$this->error = curl_error($ch);
			}

			curl_close($ch);

			return $json;
		}

		$this->error = 'No endpoint found.';

		return null;
	}

	/**
	 * Builds the final endpoint URL.
	 *
	 * @param string $endpoint The endpoint we want to work with {@see Tradedoubler::getServiceEndpoint}.
	 * @param array|null $params Optional array of parameters to be sent.
	 * @return   string                     The final URL.
	 */
	public function buildEndpointURL(string $endpoint, array $params = null): string
	{
		$url = self::BASE_URL . self::VERSION . $endpoint;

		if ($params)
		{
			foreach ($params as $key => $value)
			{
				$url .= ";$key=$value";
			}
		}

		$url .= "?token={$this->token}";

		return $url;
	}

	/**
	 * Retrieves an endpoint for a path.
	 *
	 * Example:
	 * ---------------------------------------------------------------------------
	 *
	 * <code>
	 *  ...
	 *
	 *  $tradedoubler->getServiceEndpoint('advertisers.products.categories');
	 * </code>
	 *
	 * @param string $service The path for the service. This must be like follows: <code>foo.bar.doe</code>.
	 * @return   mixed                   Returns <code>null</code> if the endpoint was not found or string if found.
	 */
	public function getServiceEndpoint(string $service): ?string
	{
		if (preg_match_all('/(?P<keys>[a-z]+)\.?/', $service, $matches))
		{
			return $this->searchEndpointRecursively($matches['keys'], static::$services);
		}

		return null;
	}

	/**
	 * Search for the endpoint recursively.
	 *
	 * @param array $keys Keys to be searched for.
	 * @param array $arr Array with the services.
	 * @param int $i The current working index.
	 * @return   string|null              Returns <code>null</code> if not found or string otherwise.
	 */
	protected function searchEndpointRecursively(array $keys, array $arr, $i = 0): ?string
	{
		$key = $keys[$i];

		if (isset($arr[$key]))
		{
			if (is_array($arr[$key]))
			{
				return $this->searchEndpointRecursively($keys, $arr[$key], ++$i);
			}

			return $arr[$key] . self::FORMAT;
		}

		return null;
	}

	/**
	 * Retrieves the last error thrown.
	 *
	 * @return   string|null
	 */
	public function getError(): ?string
	{
		return $this->error;
	}

	/**
	 * Retrieves the current working token.
	 *
	 * @return   string|null
	 */
	public function getToken(): ?string
	{
		return $this->token;
	}

	/**
	 * Sets the current working token.
	 *
	 * @param string $token The new token to use.
	 */
	public function setToken(string $token)
	{
		$this->token = $token;
	}

}
