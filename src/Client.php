<?php

namespace CLSystems\Tradedoubler;

use Exception;
use function urlencode;

/**
 * Tradedoubler OpenAPI client library {@link https://tradedoubler.docs.apiary.io}.
 * <code>
 * ...
 *
 * $credentials = ['user' => 'your_login_username', 'password' => 'your_password'];
 * $client = (new \CLSystems\Tradedoubler\Client())->login(array $credentials, string $clientId, string $clientSecret);
 *
 * $merchants = $client->getMerchantList(int $siteId)
 *
 * ...
 * </code>
 */
class Client
{

	protected $sitesAllowed = [];
	protected $client = null;
	protected $dateFormat = null;
	protected $apiConnectUrl = 'https://connect.tradedoubler.com';
	protected $apiUrl = 'https://api.tradedoubler.com';
	/**
	 * @var array
	 */
	private $credentials;
	/**
	 * @var string
	 */
	private $grantType;
	/**
	 * @var string
	 */
	private $token;
	/**
	 * @var string
	 */
	private $clientId;
	/**
	 * @var string
	 */
	private $clientSecret;

	/**
	 * Login to TradeDoubler API
	 * @see https://tradedoubler.docs.apiary.io/#/reference/o-auth-2-0/bearer-and-refresh-token/bearer-token/200?mc=reference%2Fo-auth-2-0%2Fbearer-and-refresh-token%2Fbearer-token%2F200
	 *
	 * @param array $credentials
	 * @param string $clientId
	 * @param string $clientSecret
	 * @throws Exception
	 * @return Client
	 */
	public function login(array $credentials, string $clientId, string $clientSecret) : Client
	{
		$this->credentials = $credentials;
		$this->clientId = $clientId;
		$this->clientSecret = $clientSecret;
		$this->grantType = 'password';
		$this->getToken();
		return $this;
	}

	/**
	 * Fetch all programs from TradeDoubler
	 * @see https://tradedoubler.docs.apiary.io/#/reference/programs/program/list-programs/200?mc=reference%2Fprograms%2Fprogram%2Flist-programs%2F200
	 *
	 * @param int $siteId
	 * @param array|null $filters
	 * @return array
	 * @throws Exception
	 */
	public function getProgramList(int $siteId, array $filters = null): array
	{
		$results = [];
		try
		{
			$limit = 100;
			$offset = 0;
			$loop = true;
			while (true === $loop)
			{
				$urlPrograms = $this->apiConnectUrl . '/publisher/programs?sourceId=' . $siteId . '&limit=' . $limit . '&offset=' . $offset;
				if (false === empty($filters))
				{
					foreach ($filters as $key => $value)
					{
						$urlPrograms .= '&' . urlencode($key) . '=' . urlencode($value);
					}
				}
				$merchants = self::callApi($urlPrograms);
				if (true === isset($merchants[0]['code']) && true === isset($merchants[0]['message']))
				{
					// Some error occurred
					throw new Exception('[TradeDoubler][getProgramList] ' . $merchants[0]['message']);
				}
				else if (true === isset($merchants['items']) && true === isset($merchants['total']))
				{
					foreach ($merchants['items'] as $merchantJson)
					{
						$obj = [];
						$obj['cid'] = $merchantJson['id'];
						$obj['name'] = $merchantJson['name'];
						$obj['status_id'] = $merchantJson['statusId'];
						//Possible values: 0: Not Applied, 1: Under Consideration, 2: On-hold while under consideration, 3: Accepted, 4: Ended, 5: Denied, 6: On Hold while Accepted, 7: Final Denied, 8: Written Off
						switch ($merchantJson['statusId'])
						{
							case 0:
								$obj['status'] = 'Not Applied';
							break;
							case 1:
								$obj['status'] = ' Under Consideration';
							break;
							case 2:
								$obj['status'] = 'On-hold while under consideration';
							break;
							case 3:
								$obj['status'] = 'Accepted';
							break;
							case 4:
								$obj['status'] = 'Ended';
							break;
							case 5:
								$obj['status'] = 'Denied';
							break;
							case 6:
								$obj['status'] = 'On Hold while Accepted';
							break;
							case 7:
								$obj['status'] = 'Final Denied';
							break;
							case 8:
								$obj['status'] = 'Written Off';
							break;
							default:
								$obj['status'] = 'Unknown';
								echo '[TradeDoubler][getProgramList] Merchant status unexpected ' . $merchantJson['statusId'];
							break;
						}
						$obj['launch_date'] = $merchantJson['startDate'];
						$obj['application_date'] = $merchantJson['applicationDate'];
						$obj += $merchantJson;
						$results[] = $obj;
					}
					if ((int)$merchants['total'] <= $offset)
					{
						$loop = false;
					}
					$offset = (int)($limit + $offset);
				}
				else
				{
					echo '[TradeDoubler][getProgramList] invalid response ';
					var_dump($curl_results);
					$loop = false;
				}
			}
		}
		catch (Exception $exception)
		{
			throw new Exception('[TradeDoubler][getMerchantList][Exception] ' . $exception->getMessage());
		}
		return $results;
	}

	/**
	 * @see https://tradedoubler.docs.apiary.io/#/reference/programs/program/program-detail/200?mc=reference%2Fprograms%2Fprogram%2Fprogram-detail%2F200
	 *
	 * @param int $programId
	 * @return array
	 * @throws Exception
	 */
	public function getProgramDetails(int $siteId, int $programId): array
	{
		$program = [];
		try
		{
			$urlProgramDetails = $this->apiConnectUrl . '/publisher/programs/detail?sourceId=' . $siteId . '&programId=' . $programId ;
			$programJson = self::callApi($urlProgramDetails);
			if (true === isset($programJson['code']) && true === isset($programJson['message']))
			{
				// Some error occurred
				throw new Exception('[TradeDoubler][getProgramDetails] ' . $programJson['message']);
			}
			else if (false === empty($programJson['id']))
			{
				$program = $programJson;
			}
			else
			{
				echo '[TradeDoubler][getProgramDetails] invalid response for program ' . $programId . ': ';
				var_dump($curl_results, $programJson);
			}
		}
		catch (Exception $exception)
		{
			throw new Exception('[TradeDoubler][getProgramDetails][Exception] ' . $exception->getMessage());
		}
		return $program;
	}

	/**
	 * Fetch vouchers from TradeDoubler
	 * @see http://dev.tradedoubler.com/vouchers/publisher/#Get_vouchers_service
	 *
	 * @param int $siteId
	 * @param array|null $filters
	 * @return array
	 */
	public function getVoucherList(int $siteId, array $filters = null): array {

		$results = [];
		try
		{
			$limit = 100;
			$offset = 0;
			$loop = true;
			while (true === $loop)
			{
				$urlVouchers = $this->apiUrl . '/publisher/programs?sourceId=' . $siteId . '&limit=' . $limit . '&offset=' . $offset;
				if (false === empty($filters))
				{
					foreach ($filters as $key => $value)
					{
						$urlVouchers .= '&' . urlencode($key) . '=' . urlencode($value);
					}
				}
				$merchants = self::callApi($urlVouchers);
				if (true === isset($merchants[0]['code']) && true === isset($merchants[0]['message']))
				{
					// Some error occurred
					throw new Exception('[TradeDoubler][getProgramList] ' . $merchants[0]['message']);
				}
				else if (true === isset($merchants['items']) && true === isset($merchants['total']))
				{
					foreach ($merchants['items'] as $merchantJson)
					{
						$obj = [];
						$obj['cid'] = $merchantJson['id'];
						$obj['name'] = $merchantJson['name'];
						$obj['status_id'] = $merchantJson['statusId'];
						//Possible values: 0: Not Applied, 1: Under Consideration, 2: On-hold while under consideration, 3: Accepted, 4: Ended, 5: Denied, 6: On Hold while Accepted, 7: Final Denied, 8: Written Off
						switch ($merchantJson['statusId'])
						{
							case 0:
								$obj['status'] = 'Not Applied';
							break;
							case 1:
								$obj['status'] = ' Under Consideration';
							break;
							case 2:
								$obj['status'] = 'On-hold while under consideration';
							break;
							case 3:
								$obj['status'] = 'Accepted';
							break;
							case 4:
								$obj['status'] = 'Ended';
							break;
							case 5:
								$obj['status'] = 'Denied';
							break;
							case 6:
								$obj['status'] = 'On Hold while Accepted';
							break;
							case 7:
								$obj['status'] = 'Final Denied';
							break;
							case 8:
								$obj['status'] = 'Written Off';
							break;
							default:
								$obj['status'] = 'Unknown';
								echo '[TradeDoubler][getProgramList] Merchant status unexpected ' . $merchantJson['statusId'];
							break;
						}
						$obj['launch_date'] = $merchantJson['startDate'];
						$obj['application_date'] = $merchantJson['applicationDate'];
						$obj += $merchantJson;
						$results[] = $obj;
					}
					if ((int)$merchants['total'] <= $offset)
					{
						$loop = false;
					}
					$offset = (int)($limit + $offset);
				}
				else
				{
					echo '[TradeDoubler][getVoucherList] invalid response ';
					var_dump($curl_results);
					$loop = false;
				}
			}
		}
		catch (Exception $exception)
		{
			throw new Exception('[TradeDoubler][getMerchantList][Exception] ' . $exception->getMessage());
		}
		return $results;
	}

	/**
	 * @param string $clientId
	 * @param string $clientSecret
	 * @return string
	 * @throws Exception
	 */
	private function getToken(): string
	{
		try
		{
			if (false === empty($this->token))
			{
				return $this->token;
			}
			// Retrieve Bearer token
			$loginUrl = $this->apiConnectUrl . '/uaa/oauth/token';
			$params = [
				'grant_type' => $this->grantType,
				'username'   => $this->credentials['user'],
				'password'   => $this->credentials['password'],
			];

			$apiKey = base64_encode($this->clientId . ':' . $this->clientSecret);
			$p = [];
			foreach ($params as $key => $value)
			{
				$p[] = $key . '=' . urlencode($value);
			}
			$post_params = implode('&', $p);

			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $loginUrl);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_params);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic " . $apiKey, "Content-Type: application/x-www-form-urlencoded"]);

			$curl_results = curl_exec($ch);
			curl_close($ch);
			$response = json_decode($curl_results, false, 512, JSON_THROW_ON_ERROR);
			if ($response)
			{
				if (isset($response->error))
				{
					if (isset($response->error_description))
					{
						throw new Exception('[TradeDoubler][getToken] ' . $response->error_description);
					}
				}
				if (isset($response->access_token))
				{
					$this->token = $response->access_token;
				}

			}
			return $this->token;
		}
		catch (Exception $exception)
		{
			throw new Exception('[TradeDoubler][getToken][Exception] ' . $exception->getMessage());
		}

	}

	/**
	 * Call the
	 *
	 * @param string $url
	 * @return array
	 * @throws Exception
	 */
	private function callApi(string $url): array
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
//		curl_setopt($ch, CURLOPT_POST, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $this->getToken(), "Content-Type: application/json"]);

		$curl_results = curl_exec($ch);
		curl_close($ch);

		return json_decode($curl_results, true, 512, JSON_PARTIAL_OUTPUT_ON_ERROR);
	}
}
