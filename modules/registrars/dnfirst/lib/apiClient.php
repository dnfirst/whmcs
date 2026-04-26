<?php

namespace WHMCS\Module\Registrar\DNFirst;

use Exception;

class apiResponse {
	public $status;
	public $data;
	public $results;
}

class apiClient {
	const API_URL = 'https://api.dnfirst.com/v1/';
	const SANDBOX_API_URL = 'https://api-sandbox.dnfirst.com/v1/';

	public $sandboxMode;
	public $authToken;

	/**
	 * Make external API call to registrar API.
	 *
	 * @param string $action
	 * @param array $postfields
	 *
	 * @return apiResponse
	 * @throws Exception Bad API response
	 *
	 * @throws Exception Connection error
	 */
	public function call($action, $method, $postfields = NULL): apiResponse {

		if ( $this->sandboxMode ) {
			$url = self::SANDBOX_API_URL;
		} else {
			$url = self::API_URL;
		}
		if (empty($this->authToken)) {
			throw new Exception('API Auth Token not set');
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url . $action);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: {$this->authToken}"));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 100);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		if (!is_null($postfields)) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postfields));
		}

		$response = new apiResponse();

		$response->data = curl_exec($ch);
		$info = curl_getinfo($ch);

		$response->status = $info['http_code'];

		if (isset($info['http_code']) && $info['http_code'] == 400) {
			logModuleCall(
				'dnfirst',
				"{$method} {$action}",
				$postfields,
				'Input Error: ' . $info['http_code'] . ' - ' . $response->data,
				NULL,
				NULL
			);

			throw new Exception('Invalid Input: ' . $response->data);
		}

		if (isset($info['http_code']) && $info['http_code'] == 500) {
			logModuleCall(
				'dnfirst',
				"{$method} {$action}",
				$postfields,
				'API Error: ' . $info['http_code'] . ' - ' . $response->data,
				NULL,
				NULL
			);

			throw new Exception('API Error: ' . $info['http_code'] . ' - ' . $response->data);
		}

		$errno = curl_errno($ch);

		if ($errno) {

			logModuleCall(
				'dnfirst',
				"{$method} {$action}",
				$postfields,
				'Connection Error: ' . $errno . ' - ' . curl_error($ch),
				NULL,
				NULL
			);

			throw new Exception('Connection Error: ' . $errno . ' - ' . curl_error($ch));
		}

		if ( !empty($response->data) ) {
			$response->results = json_decode($response->data, true);

			logModuleCall(
				'dnfirst',
				"{$method} {$action}",
				$postfields,
				$response->data,
				$response->results,
				NULL
			);

			if (json_last_error() !== JSON_ERROR_NONE) {
				throw new Exception('Bad response received from API');
			}
		}
		return $response;
	}

}
