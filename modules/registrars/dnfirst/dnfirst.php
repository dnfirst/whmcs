<?php

if (!defined("WHMCS")) {
	die("This file cannot be accessed directly");
}
require_once __DIR__ . '/lib/apiClient.php';

use WHMCS\Carbon;
use WHMCS\Domain\Registrar\Domain;
use WHMCS\Domains\DomainLookup\SearchResult;
use WHMCS\Domain\TopLevel\ImportItem;
use WHMCS\Module\Registrar\DNFirst\apiClient;

/**
 * Define module related metadata
 *
 * Provide some module information including the display name and API Version to
 * determine the method of decoding the input values.
 *
 * @return array
 */
function DNFirst_MetaData() {
	return array(
		'DisplayName' => 'DNFirst Registrar Module',
		'APIVersion' => '1.0',
	);
}

/**
 * Define registrar configuration options.
 *
 * The values you return here define what configuration options
 * we store for the module. These values are made available to
 * each module function.
 *
 * You can store an unlimited number of configuration settings.
 * The following field types are supported:
 *  * Text
 *  * Password
 *  * Yes/No Checkboxes
 *  * Dropdown Menus
 *  * Radio Buttons
 *  * Text Areas
 *
 * @return array
 */
function DNFirst_getConfigArray() {
	return [
		// Friendly display name for the module
		'FriendlyName' => [
			'Type' => 'System',
			'Value' => 'DNFirst Registrar Module for WHMCS',
		],
		// a password field type allows for masked text input
		'APIKey' => [
			'FriendlyName' => 'API Key',
			'Type' => 'password',
			'Size' => '48',
			'Default' => '',
			'Description' => 'Enter secret value here',
		],
		// the yesno field type displays a single checkbox option
		'SandboxMode' => [
			'FriendlyName' => 'Sandbox Mode',
			'Type' => 'yesno',
			'Description' => 'Tick to enable',
		],
		'SandboxAPIKey' => [
			'FriendlyName' => 'Sandbox API Key',
			'Type' => 'password',
			'Size' => '48',
			'Default' => '',
			'Description' => 'Enter secret value here',
		],
	];
}

/**
 * Internal function to get the API client.
 */
function DNFirst_GetApi($params) {
	$api = new ApiClient();
	$api->sandboxMode = $params['SandboxMode'];
	$api->authToken = $params['SandboxMode'] ? $params['SandboxAPIKey'] : $params['APIKey'];

	return $api;
}
/**
 * Register a domain.
 *
 * Attempt to register a domain with the domain registrar.
 *
 * This is triggered when the following events occur:
 * * Payment received for a domain registration order
 * * When a pending domain registration order is accepted
 * * Upon manual request by an admin user
 *
 * @param array $params common module parameters
 *
 * @return array
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 */
function DNFirst_RegisterDomain($params) {

	$domainName = $params['sld'] . '.' . $params['tld'];

	$postData = [
		"years" => $params['regperiod'],
		"premiumDomains" => (bool)$params['premiumEnabled'],
		"nameServers" => [$params['ns1_punycode'], $params['ns2_punycode'], $params['ns3_punycode'], $params['ns4_punycode'], $params['ns5_punycode']],
		"registrant" => [
			"company_name" => $params["companyname"],
			"first_name" => $params["firstname"],
			"last_name" => $params["lastname"],
			"email_address" => $params["email"],
			"voice_number" => $params["fullphonenumber"],
			"street1" => $params["address1"],
			"street2" => $params["address2"],
			"city" => $params["city"],
			"state" => $params["state"] ?: $params["fullstate"],
			"postal_code" => $params["postcode"],
			"country_id" => $params["countrycode"],
		]
	];
	// (bool) $params['dnsmanagement'];
	// (bool) $params['idprotection'];

	if (strtolower($params['tld']) === 'us') { // for .us domains
		$postData['nexusCategory'] = $params['additionalfields']['Nexus Category'];
		$postData['nexusCountry'] = $params['additionalfields']['Nexus Country'];

		$postData['nexusApplicationPurpose'] = match ($params['additionalfields']['Application Purpose']) {
			"Business use for profit" => "P1",
			"Non-profit business", "Club", "Association", "Religious Organization" => "P2",
			"Educational purposes" => "P4",
			"Government purposes" => "P5",
			default => "P3",
		};
	}
	/**
	 * Premium domain parameters.
	 *
	 * Premium domains enabled informs you if the admin user has enabled
	 * the selling of premium domain names. If this domain is a premium name,
	 * `premiumCost` will contain the cost price retrieved at the time of
	 * the order being placed. The premium order should only be processed
	 * if the cost price now matches the previously fetched amount.
	 */
	$premiumDomainsEnabled = (bool)$params['premiumEnabled'];

	if ($premiumDomainsEnabled && isset($params['premiumCost'])) {
		$postData['amount'] = $params['premiumCost'];
	}

	/*
	 * IDN
	 */
	if ( (bool) $params['idn'] ) {
		$postData['idnLanguage'] = $params['idnlanguage'];
		$domainName = $params['domain_punycode'];
	}


	try {
		$api = DNFirst_GetApi($params);
		$response = $api->call('domain/' . $domainName . '/register', 'POST', $postData);

		if ( $response->status !== 201 ) {
			throw new Exception("Failed to register domain");
		}
		return array(
			'success' => true,
		);

	} catch (\Exception $e) {
		return array(
			'error' => $e->getMessage(),
		);
	}
}

function DNFirst_TransferDomain($params) {

	$domainName = $params['sld'] . '.' . $params['tld'];

	$postData = [
		"years" => $params['regperiod'],
		"premiumDomains" => (bool)$params['premiumEnabled'],
		"eppCode" => $params['eppcode'],
		"nameServers" => [$params['ns1_punycode'], $params['ns2_punycode'], $params['ns3_punycode'], $params['ns4_punycode'], $params['ns5_punycode']],
		"registrant" => [
			"company_name" => $params["companyname"],
			"first_name" => $params["firstname"],
			"last_name" => $params["lastname"],
			"email_address" => $params["email"],
			"voice_number" => $params["fullphonenumber"],
			"street1" => $params["address1"],
			"street2" => $params["address2"],
			"city" => $params["city"],
			"state" => $params["state"] ?: $params["fullstate"],
			"postal_code" => $params["postcode"],
			"country_id" => $params["countrycode"],
		]
	];
	// (bool) $params['dnsmanagement'];
	// (bool) $params['idprotection'];

	/**
	 * Premium domain parameters.
	 *
	 * Premium domains enabled informs you if the admin user has enabled
	 * the selling of premium domain names. If this domain is a premium name,
	 * `premiumCost` will contain the cost price retrieved at the time of
	 * the order being placed. The premium order should only be processed
	 * if the cost price now matches the previously fetched amount.
	 */
	$premiumDomainsEnabled = (bool)$params['premiumEnabled'];

	if ($premiumDomainsEnabled && isset($params['premiumCost'])) {
		$postData['amount'] = $params['premiumCost'];
	}

	/*
	 * IDN
	 */
	if ( (bool) $params['idn'] ) {
		$postData['idnLanguage'] = $params['idnlanguage'];
		$domainName = $params['domain_punycode'];
	}


	try {
		$api = DNFirst_GetApi($params);
		$response = $api->call('domain/' . $domainName . '/transfer', 'POST', $postData);

		if ( $response->status !== 201 ) {
			throw new Exception("Failed to create domain transfer reqeust");
		}
		return array(
			'success' => true,
		);

	} catch (\Exception $e) {
		return array(
			'error' => $e->getMessage(),
		);
	}

}

function DNFirst_RenewDomain($params) {

	$domainName = $params['sld'] . '.' . $params['tld'];

	$premiumDomainsEnabled = (bool)$params['premiumEnabled'];

	$postData = [
		"premiumDomains" => $premiumDomainsEnabled,
		"years" => $params['regperiod'],
	];

	if ($premiumDomainsEnabled && isset($params['premiumCost'])) {
		$postData['amount'] = $params['premiumCost'];
	}

	try {
		$api = DNFirst_GetApi($params);
		$response = $api->call('domain/' . $domainName . '/renew', 'POST', $postData);

		if ( $response->status === 404 ) {
			throw new Exception("Domain does not exist");
		}
		if ( $response->status !== 201 ) {
			throw new Exception("Failed to renew domain");
		}

		return array(
			'success' => true,
		);

	} catch (Exception $e) {
		return array(
			'error' => $e->getMessage(),
		);
	}
}

function DNFirst_GetNameservers($params) {
	$domainName = $params['sld'] . '.' . $params['tld'];
	$api = DNFirst_GetApi($params);
	try {
		$response = $api->call('domain/' . $domainName . '/info', 'GET');

		if ( $response->status !== 200 ) {
			throw new Exception("Failed to fetch domain info");
		}

		$nameServers = $response->results['nameServers'];
		if ( empty($nameServers) ) {
			throw new Exception("No nameservers found");
		}

		return array(
			'ns1' => isset($nameServers[0]) ? $nameServers[0] : '' ,
			'ns2' => isset($nameServers[1]) ? $nameServers[1] : '' ,
			'ns3' => isset($nameServers[2]) ? $nameServers[2] : '' ,
			'ns4' => isset($nameServers[3]) ? $nameServers[3] : '' ,
			'ns5' => isset($nameServers[4]) ? $nameServers[4] : '' ,
		);

	} catch ( Exception $e ) {
		return array(
			'error' => $e->getMessage(),
		);
	}

}

/**
 * Save nameserver changes.
 *
 * This function should submit a change of nameservers request to the
 * domain registrar.
 *
 * @param array $params common module parameters
 *
 * @return array
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 */
function DNFirst_SaveNameservers($params) {

	$domainName = $params['sld'] . '.' . $params['tld'];

	$nameServers = array();
	foreach ( array("ns1_punycode","ns2_punycode","ns3_punycode","ns4_punycode","ns5_punycode" ) as $ns ) {
		if ( isset( $params[ $ns ] ) ) {
			$nameServers[] = $params[ $ns ];
		}
	}

	$postData = [
		"nameServers" => $nameServers
	];


	try {
		$api = DNFirst_GetApi($params);
		$response = $api->call('domain/' . $domainName, 'PATCH', $postData);

		if ( $response->status !== 204 ) {
			throw new Exception("Failed to update name servers");
		}

		return array(
			'success' => true,
		);

	} catch (\Exception $e) {
		return array(
			'error' => $e->getMessage(),
		);
	}
}

/**
 * Get the current WHOIS Contact Information.
 *
 * Should return a multi-level array of the contacts and name/address
 * fields that be modified.
 *
 * @param array $params common module parameters
 *
 * @return array
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 */
function DNFirst_GetContactDetails($params) {
	$domainName = $params['sld'] . '.' . $params['tld'];
	$api = DNFirst_GetApi($params);
	try {
		$response = $api->call('domain/' . $domainName . '/info', 'GET');
		if ( $response->status !== 200 ) {
			throw new Exception("Failed to retrieve domain info");
		}

		$contact = $response->results['registrant'];
		if ( empty($contact) ) {
			throw new Exception("No registrant found");
		}
		return array(
			'Registrant' => array(
				'First Name' => $contact['first_name'],
				'Last Name' => $contact['last_name'],
				'Company Name' => $contact['company_name'],
				'Email Address' => $contact['email_address'],
				'Address 1' => $contact['street1'],
				'Address 2' => $contact['street2'],
				'City' => $contact['city'],
				'State' => $contact['state'],
				'Postcode' => $contact['postal_code'],
				'Country' => $contact['country_id'],
				'Phone Number' => $contact['voice_number'],
			),

		);


	} catch ( Exception $e ) {
		return array(
			'error' => $e->getMessage(),
		);
	}
}

/**
 * Update the WHOIS Contact Information for a given domain.
 *
 * Called when a change of WHOIS Information is requested within WHMCS.
 * Receives an array matching the format provided via the `GetContactDetails`
 * method with the values from the users input.
 *
 * @param array $params common module parameters
 *
 * @return array
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 */
function DNFirst_SaveContactDetails($params) {
	$domainName = $params['sld'] . '.' . $params['tld'];

	$contactDetails = $params['contactdetails']['Registrant'];

	$postData = [
		"registrant" => [
			"company_name" => $contactDetails["Company Name"],
			"first_name" => $contactDetails["First Name"],
			"last_name" => $contactDetails["Last Name"],
			"email_address" => $contactDetails["Email Address"],
			"voice_number" => $contactDetails["Phone Number"],
			"street1" => $contactDetails["Address 1"],
			"street2" => $contactDetails["Address 2"],
			"city" => $contactDetails["City"],
			"state" => $contactDetails["State"],
			"postal_code" => $contactDetails["Postcode"],
			"country_id" => $contactDetails["Country"],
		]
	];


	try {
		$api = DNFirst_GetApi($params);
		$response = $api->call('domain/' . $domainName, 'PATCH', $postData);

		if ( $response->status !== 204 ) {
			throw new Exception("Failed to update domain contact");
		}
		logModuleCall(
			'dnfirst',
			"DNFirst_SaveContactDetails",
			$postData,
			array(
				'success' => true,
			),
			NULL,
			NULL
		);

		return array(
			'success' => true,
		);

	} catch (\Exception $e) {
		logModuleCall(
			'dnfirst',
			"DNFirst_SaveContactDetails",
			$postData,
			array(
				'error' => $e->getMessage(),
			),
			NULL,
			NULL
		);

		return array(
			'error' => $e->getMessage(),
		);
	}

}

/**
 * Check Domain Availability.
 *
 * Determine if a domain or group of domains are available for
 * registration or transfer.
 *
 * @param array $params common module parameters
 * @return array|WHMCS\Domains\DomainLookup\ResultsList An ArrayObject based collection of \WHMCS\Domains\DomainLookup\SearchResult results
 * @throws Exception Upon domain availability check failure.
 *
 * @see WHMCS\Domains\DomainLookup\ResultsList
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @see SearchResult
 */
function DNFirst_CheckAvailability($params) {
	logModuleCall(
		'dnfirst',
		"CheckAvailability",
		NULL,
		NULL,
		NULL,
		NULL
	);

	$postData = [
		"premiumDomains" => (bool)$params['premiumEnabled'],
	];
	$tldsToInclude = $params['tldsToInclude'];

	foreach ( $tldsToInclude as $tld ) {
		$postData['domains'][] = ($params['punyCodeSearchTerm'] ?: $params['searchTerm']) . $tld;
	}

	try {
		$api = DNFirst_GetApi($params);
		$response = $api->call('domain/search', 'POST', $postData);
		if ( $response->status !== 200 ) {
			throw new Exception("Failed to retrieve domain check response");
		}


		$results = new WHMCS\Domains\DomainLookup\ResultsList();
		foreach ($response->results as $domain) {

			$sld = substr($domain['domainName'],0, strpos($domain['domainName'],'.'));
			$tld = str_replace($sld.".","", $domain['domainName']);

			logModuleCall(
				'dnfirst',
				"CheckAvailability",
				NULL,
				"sld: " . $sld ."\ntld: " . $tld,
				NULL,
				NULL
			);

			// Instantiate a new domain search result object
			$searchResult = new SearchResult($sld, $tld);

			// Determine the appropriate status to return
			if ($domain['error'] ) {
				$status = SearchResult::UNKNOWN;
			} elseif ($domain['available']) {
				$status = SearchResult::STATUS_NOT_REGISTERED;
			} elseif ($domain['reserved']) {
				$status = SearchResult::STATUS_RESERVED;
			} elseif (!$domain['available']) {
				$status = SearchResult::STATUS_REGISTERED;
			} else {
				$status = SearchResult::STATUS_TLD_NOT_SUPPORTED;
			}
			$searchResult->setStatus($status);

			// Return premium information if applicable
			if ($domain['premium']) {
				$searchResult->setPremiumDomain(true);
				$searchResult->setPremiumCostPricing(
					array(
						'register' => $domain['fee'],
						//'renew' => $domain['premiumRenewPrice'],
						'CurrencyCode' => 'USD',
					)
				);
			}

			// Append to the search results list
			$results->append($searchResult);
		}
		logModuleCall(
			'dnfirst',
			"CheckAvailability",
			NULL,
			"success: " . var_export($results, true),
			NULL,
			NULL
		);

		return $results;

	} catch (\Throwable $e) {
		logModuleCall(
			'dnfirst',
			"CheckAvailability",
			NULL,
			"error: " . $e->getMessage() . " " . $e->getLine() . " " . $e->getFile() . " " . $e->getTraceAsString(),
			NULL,
			NULL
		);

		$results = new WHMCS\Domains\DomainLookup\ResultsList();

		foreach ( $postData['domains'] as $domain ) {
			$searchResult = new SearchResult($domain);
			$searchResult->setStatus(SearchResult::UNKNOWN);

			$results->append($searchResult);
		}


		return $results;
	}

}

function DNFirst_GetDomainInformation($params) {
	$domainName = $params['sld'] . '.' . $params['tld'];
	$api = DNFirst_GetApi($params);
	try {
		$response = $api->call('domain/' . $domainName . '/info', 'GET');
		if ( $response->status === 404 ) {
			throw new Exception("Domain name does not exist");
		}
		if ( $response->status !== 200 ) {
			throw new Exception("Failed to retrieve domain information");
		}
		/*
		 * Array
		(
				[domainName] => blahasdfasdf234234.com
				[nameServers] => Array
						(
								[0] => ns1.quantumnames.com
								[1] => ns2.quantumnames.com
						)

				[hostServers] => Array
						(
						)

				[created] => 2026-03-31
				[expires] => 2027-03-31
				[lock] =>
				[privacyProtection] =>
		)

		STATUS_ARCHIVED

		STATUS_DELETED

		STATUS_EXPIRED

		STATUS_INACTIVE

		STATUS_SUSPENDED

		STATUS_PENDING_DELETE
		 */
		$status = Domain::STATUS_ACTIVE;
		if (is_null($response->results['created'])) {
			$status = Domain::STATUS_INACTIVE;
		} else if (!is_null($response->results['expires']) && (new DateTime($response->results['expires'])) < ( new DateTime) ) {
			$status = Domain::STATUS_EXPIRED;
		} else if ($response->results['suspended']) {
			$status = Domain::STATUS_SUSPENDED;
		}

		return (new Domain)
			->setDomain($domainName)
			->setNameservers($response->results['nameServers'])
			->setRegistrationStatus(Domain::STATUS_ACTIVE)
			->setTransferLock($response->results['lock'])
			->setTransferLockExpiryDate(!is_null($response->results['transferLockExpires']) ? Carbon::createFromFormat('Y-m-d', $response->results['transferLockExpires']) : null)
			->setExpiryDate(Carbon::createFromFormat('Y-m-d', $response->results['expires'])) // $response['expirydate'] = YYYY-MM-DD
			->setRestorable($response->results['restorable'])
			->setIdProtectionStatus($response->results['privacyProtection'])
			->setDnsManagementStatus($response->results['dnsManagement'])
			->setEmailForwardingStatus(false)
			->setIsIrtpEnabled(in_array($response->results['tld'], ['.com']))
			->setIrtpOptOutStatus($response->results['irtp']['optoutstatus'])
			->setIrtpTransferLock($response->results['irtp']['lockstatus'])
			//->IrtpTransferLockExpiryDate($irtpTransferLockExpiryDate)
			->setDomainContactChangePending($response->results['status']['contactpending'])
			->setPendingSuspension($response->results['status']['pendingsuspend'])
			->setDomainContactChangeExpiryDate($response->results['status']['expires'])
			->setRegistrantEmailAddress($response->results['registrant']['email_address'])
			->setIrtpVerificationTriggerFields(
				[
					'Registrant' => [
						'First Name',
						'Last Name',
						'Organization Name',
						'Email',
					],
				]
			);
	} catch ( Exception $e ) {
		return array(
			'error' => $e->getMessage(),
		);
	}
}

/**
 * Get registrar lock status.
 *
 * Also known as Domain Lock or Transfer Lock status.
 *
 * @param array $params common module parameters
 *
 * @return string|array Lock status or error message
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 */
function DNFirst_GetRegistrarLock($params) {
	$domainName = $params['sld'] . '.' . $params['tld'];
	$api = DNFirst_GetApi($params);
	try {
		$response = $api->call('domain/' . $domainName . '/info', 'GET');
		if ( $response->status === 404 ) {
			throw new Exception("Domain name does not exist");
		}
		if ( $response->status !== 200 ) {
			throw new Exception("Failed to retrieve domain information");
		}

		return $response->results['lock'] ? 'locked' : 'unlocked';

	} catch (\Exception $e) {
		return array(
			'error' => $e->getMessage(),
		);
	}
}

/**
 * Set registrar lock status.
 *
 * @param array $params common module parameters
 *
 * @return array
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 */
function DNFirst_SaveRegistrarLock($params) {

	$domainName = $params['sld'] . '.' . $params['tld'];
	$postData = [
		"locked" => $params['lockenabled'] === "locked",
	];


	try {
		$api = DNFirst_GetApi($params);
		$response = $api->call('domain/' . $domainName, 'PATCH', $postData);

		if ( $response->status === 404 ) {
			throw new Exception("Domain name does not exist");
		}
		if ( $response->status !== 204 ) {
			throw new Exception("Failed to update domain lock");
		}

		return ['success' => true];

	} catch (\Exception $e) {
		return array(
			'error' => $e->getMessage(),
		);
	}}

/**
 * Get DNS Records for DNS Host Record Management.
 *
 * @param array $params common module parameters
 *
 * @return array DNS Host Records
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 */
function DNFirst_GetDNS($params) {
	$domainName = $params['sld'] . '.' . $params['tld'];
	$api = DNFirst_GetApi($params);
	try {
		$response = $api->call('dns/' . $domainName . '/records', 'GET');
		if ( $response->status === 404 ) {
			throw new Exception("Domain name does not exist");
		}
		if ( $response->status !== 200 ) {
			throw new Exception("Failed to retrieve dns records");
		}

		$hostRecords = array();
		foreach ($response->results as $record) {
			$hostRecords[] = array(
				"hostname" => $record['name'], // eg. www
				"type" => $record['type'], // eg. A
				"address" => $record['content'], // eg. 10.0.0.1
				"priority" => $record['pri'], // eg. 10 (N/A for non-MX records)
			);
		}
		return $hostRecords;

	} catch (\Exception $e) {
		return array(
			'error' => $e->getMessage(),
		);
	}
}

/**
 * Update DNS Host Records.
 *
 * @param array $params common module parameters
 *
 * @return array
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 */
function DNFirst_SaveDNS($params) {
	$domainName = $params['sld'] . '.' . $params['tld'];
	$api = DNFirst_GetApi($params);
	try {
		$records = $params['hostrecords'];

		$response = $api->call('dns/' . $domainName . '/records', 'POST', $records);
		if ( $response->status === 404 ) {
			throw new Exception("Domain name does not exist");
		}
		if ( $response->status !== 201 ) {
			throw new Exception("Failed to update dns records");
		}

	} catch (\Exception $e) {
		return array(
			'error' => $e->getMessage(),
		);
	}

}

/**
 * Enable/Disable ID Protection.
 *
 * @param array $params common module parameters
 *
 * @return array
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 */
function DNFirst_IDProtectToggle($params) {
	// user defined configuration values
	$userIdentifier = $params['APIUsername'];
	$apiKey = $params['APIKey'];
	$SandboxMode = $params['SandboxMode'];
	$accountMode = $params['AccountMode'];
	$emailPreference = $params['EmailPreference'];

	// domain parameters
	$sld = $params['sld'];
	$tld = $params['tld'];

	// id protection parameter
	$protectEnable = (bool)$params['protectenable'];

	// Build post data
	$postfields = array(
		'username' => $userIdentifier,
		'password' => $apiKey,
		'SandboxMode' => $SandboxMode,
		'domain' => $sld . '.' . $tld,
	);

	try {
		$api = new ApiClient();

		if ($protectEnable) {
			$api->call('EnableIDProtection', $postfields);
		} else {
			$api->call('DisableIDProtection', $postfields);
		}

		return array(
			'success' => 'success',
		);

	} catch (Exception $e) {
		return array(
			'error' => $e->getMessage(),
		);
	}
}

/**
 * Request EEP Code.
 *
 * Supports both displaying the EPP Code directly to a user or indicating
 * that the EPP Code will be emailed to the registrant.
 *
 * @param array $params common module parameters
 *
 * @return array
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 */
function DNFirst_GetEPPCode($params) {
	$domainName = $params['sld'] . '.' . $params['tld'];
	$api = DNFirst_GetApi($params);
	try {
		$response = $api->call('domain/' . $domainName . '/transferCode', 'GET');

		if ( $response->status === 404 ) {
			throw new Exception("Domain name does not exist");
		}
		if ( $response->status !== 200 ) {
			throw new Exception("Failed to retrieve EPP Code");
		}
		if ( empty($response->results['code']) ) {
			throw new Exception("Failed to retrieve EPP Code");
		}

		return ['eppcode' => $response->results['code']];
	} catch ( \Exception $e ) {
		return array(
			'error' => $e->getMessage(),
		);
	}
}

/**
 * Release a Domain.
 *
 * Used to initiate a transfer out such as an IPSTAG change for .UK
 * domain names.
 *
 * @param array $params common module parameters
 *
 * @return array
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 */
function DNFirst_ReleaseDomain($params) {
	$api = DNFirst_GetApi($params);

	// domain parameters
	$sld = $params['sld'];
	$tld = $params['tld'];

	return array(
		'error' => "Unimplemented",
	);
}

/**
 * Delete Domain.
 *
 * @param array $params common module parameters
 *
 * @return array
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 */
function DNFirst_RequestDelete($params) {
	$domainName = $params['sld'] . '.' . $params['tld'];
	$api = DNFirst_GetApi($params);
	try {
		$response = $api->call('domain/' . $domainName, 'DELETE');

		if ( $response->status === 404 ) {
			throw new Exception("Domain name does not exist");
		}
		if ( $response->status !== 204 ) {
			throw new Exception("Failed to delete domain name");
		}

		return ['success' => 'success'];
	} catch ( \Exception $e ) {
		return array(
			'error' => $e->getMessage(),
		);
	}

}

/**
 * Register a Nameserver.
 *
 * Adds a child nameserver for the given domain name.
 *
 * @param array $params common module parameters
 *
 * @return array
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 */
function DNFirst_RegisterNameserver($params) {
	$domainName = $params['sld'] . '.' . $params['tld'];
	try {
		$api = DNFirst_GetApi($params);

		$postFields = [
			'host' => $params['nameserver'],
			'ipAddresses' => [$params['ipaddress']],
		];

		$response = $api->call('domain/' . $domainName .'/host', 'POST', $postFields);

		if ( $response->status === 404 ) {
			throw new Exception("Domain name does not exist");
		}
		if ( $response->status === 409 ) {
			throw new Exception("Host server already exists");
		}
		if ( $response->status !== 201 ) {
			throw new Exception("Failed to create host server name");
		}

		return ['success' => 'success'];
	} catch ( \Exception $e ) {
		return array(
			'error' => $e->getMessage(),
		);
	}

}

/**
 * Modify a Nameserver.
 *
 * Modifies the IP of a child nameserver.
 *
 * @param array $params common module parameters
 *
 * @return array
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 */
function DNFirst_ModifyNameserver($params) {
	$domainName = $params['sld'] . '.' . $params['tld'];
	try {
		$api = DNFirst_GetApi($params);
		$postFields = [
			'ipAddresses' => [$params['newipaddress']],
		];

		$response = $api->call('domain/' . $domainName .'/host'.$params['nameserver'], 'PATCH', $postFields);

		if ( $response->status === 404 ) {
			throw new Exception("Domain name does not exist");
		}
		if ( $response->status !== 204 ) {
			throw new Exception("Failed to update host server");
		}

		return ['success' => 'success'];
	} catch ( \Exception $e ) {
		return array(
			'error' => $e->getMessage(),
		);
	}

}

/**
 * Delete a Nameserver.
 *
 * @param array $params common module parameters
 *
 * @return array
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 */
function DNFirst_DeleteNameserver($params) {
	$domainName = $params['sld'] . '.' . $params['tld'];
	try {
		$api = DNFirst_GetApi($params);

		$response = $api->call('domain/' . $domainName .'/host/'.$params['nameserver'], 'DELETE');

		if ( $response->status === 404 ) {
			throw new Exception("Host sever does not exist");
		}
		if ( $response->status !== 204 ) {
			throw new Exception("Failed to delete host server");
		}

		return ['success' => 'success'];
	} catch ( \Exception $e ) {
		return array(
			'error' => $e->getMessage(),
		);
	}

}

/**
 * Sync Domain Status & Expiration Date.
 *
 * Domain syncing is intended to ensure domain status and expiry date
 * changes made directly at the domain registrar are synced to WHMCS.
 * It is called periodically for a domain.
 *
 * @param array $params common module parameters
 *
 * @return array
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 */
function DNFirst_Sync($params) {
	$domainName = $params['sld'] . '.' . $params['tld'];
	try {
		$api = DNFirst_GetApi($params);
		$response = $api->call('domain/' . $domainName . '/info', 'GET');
		if ( $response->status === 404 ) {
			throw new Exception("Domain name does not exist");
		}
		if ( $response->status !== 200 ) {
			throw new Exception("Failed to retrieve domain information");
		}

		return array(
			'expirydate' => $response->results['expires'], // Format: YYYY-MM-DD
			'active' => $response->results['status'] === "ok", // Return true if the domain is active
			'transferredAway' => $response->results['transfer']['status'] === "transferredAway", // Return true if the domain is transferred out
		);

	} catch (\Exception $e) {
		return array(
			'error' => $e->getMessage(),
		);
	}
}

/**
 * Incoming Domain Transfer Sync.
 *
 * Check status of incoming domain transfers and notify end-user upon
 * completion. This function is called daily for incoming domains.
 *
 * @param array $params common module parameters
 *
 * @return array
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 */
function DNFirst_TransferSync($params) {
	$domainName = $params['sld'] . '.' . $params['tld'];
	try {
		$api = DNFirst_GetApi($params);
		$response = $api->call('domain/' . $domainName . '/info', 'GET');
		if ( $response->status === 404 ) {
			throw new Exception("Domain name does not exist");
		}
		if ( $response->status !== 200 ) {
			throw new Exception("Failed to retrieve domain information");
		}

		if ($response->results['transfer']['status'] === 'none' && !empty($response->results['expires']) ) {
			return array(
				'completed' => true,
				'expirydate' => $response->results['expires'], // Format: YYYY-MM-DD
			);
		} elseif ($response->results['transfer']['status'] === 'failed') {
			return array(
				'failed' => true,
				'reason' => $response->results['transfer']['reason'], // Reason for the transfer failure if available
			);
		} else {
			// No status change, return empty array
			return array();
		}

	} catch (\Exception $e) {
		return array(
			'error' => $e->getMessage(),
		);
	}
}

function DNFirst_ClientAreaCustomButtonArray($params) {
}

function DNFirst_ClientAreaAllowedFunctions($params) {
}

function DNFirst_ClientArea($params) {
	return NULL;
}

function DNFirst_GetTldPricing($params) {

	try {
		$api = DNFirst_GetApi($params);
		$response = $api->call('tlds', 'GET');
		if ( $response->status !== 200 ) {
			throw new Exception("Failed to retrieve tld information");
		}

		$results = new WHMCS\Results\ResultsList;
		foreach ($response->results as $tld => $extension) {
			// All the set methods can be chained and utilised together.
			$item = (new ImportItem)
				->setExtension($tld)
				->setMinYears($extension['minPeriod'])
				->setMaxYears($extension['maxPeriod'])
				->setRegisterPrice($extension['registrationPrice'])
				->setRenewPrice($extension['renewalPrice'])
				->setTransferPrice($extension['transferPrice'])
				->setRedemptionFeePrice($extension['redemptionFee'])
				->setEppRequired($extension['transferEppAuthCodeRequired'])
				->setCurrency($extension['currency']);

			$results[] = $item;
		}
		return $results;

	} catch (\Exception $e) {
		return array(
			'error' => $e->getMessage(),
		);
	}

}

function DNFirst_ResendTransfer(array $params) {
	throw new Exception("Not yet implemented");
}
function DNFirst_ResendValidationMails(array $params) {

	throw new Exception("Not yet implemented");
}