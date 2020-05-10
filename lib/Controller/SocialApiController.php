<?php
/**
 * @copyright Copyright (c) 2020 Matthias Heinisch <nextcloud@matthiasheinisch.de>
 *
 * @author Matthias Heinisch <nextcloud@matthiasheinisch.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Contacts\Controller;

use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
// use OCP\IInitialStateService;
use OCP\IConfig;
use OCP\Contacts\IManager;
use OCP\IAddressBook;
use OCP\L10N\IFactory;
use OCP\IRequest;
use OCP\Util;
use OCP\IUser;

use OCA\Contacts\Service\SocialApiService;


class SocialApiController extends ApiController {

	protected $appName;

	/** @var IFactory */
	private  $languageFactory;
	/** @var IManager */
	private  $manager;
	/** @var IConfig */
	private  $config;
	/** @var SocialApiService */
	private  $socialApiService;

	/**
	 * This constant stores the supported social networks
	 * It is an ordered list, so that first listed items will be checked first
	 * Each item stores the avatar-url-formula as recipe and cleanup parameters
	 *
	 * @const {array} SOCIAL_CONNECTORS dictionary of supported social networks
	 */
	const SOCIAL_CONNECTORS = [
		'instagram' 	=> [
			'recipe' 	=> 'https://www.instagram.com/{socialId}/?__a=1',
			'cleanups' 	=> ['basename', 'json' => 'graphql->user->profile_pic_url_hd'],
		],
		'facebook' 	=> [
			'recipe' 	=> 'https://graph.facebook.com/{socialId}/picture?width=720',
			'cleanups' 	=> ['basename'],
		],
		'tumblr' 	=> [
			'recipe' 	=> 'https://api.tumblr.com/v2/blog/{socialId}/avatar/512',
			'cleanups' 	=> ['regex' => '/(?:http[s]*\:\/\/)*(.*?)\.(?=[^\/]*\..{2,5})/i', 'group' => 1], // "subdomain"
		],
		/* untrusted
		'twitter' 	=> [
			'recipe' 	=> 'http://avatars.io/twitter/{socialId}',
			'cleanups' 	=> ['basename'],
		],
		*/
	];

	public function __construct(string $AppName,
					IRequest $request,
					IManager $manager,
					IConfig $config,
					// IInitialStateService $initialStateService,
					IFactory $languageFactory,
					SocialApiService $socialApiService) {
		parent::__construct($AppName, $request);

		$this->appName = $AppName;
		// $this->initialStateService = $initialStateService;
		$this->languageFactory = $languageFactory;
		$this->manager = $manager;
		$this->config = $config;
		$this->socialApiService = $socialApiService;
	}


	/**
	 * update appconfig (admin setting)
	 *
	 * @param {String} key the identifier to change
	 * @param {String} allow the value to set
	 *
	 * @returns {JSONResponse} an empty JSONResponse with respective http status code
	 */
	public function setAppConfig($key, $allow) {
		$this->config->setAppValue($this->appName, $key, $allow);
		return new JSONResponse([], Http::STATUS_OK);
	}


	/**
	 * @NoAdminRequired
	 *
	 * update appconfig (user setting)
	 *
	 * @param {String} key the identifier to change
	 * @param {String} allow the value to set
	 *
	 * @returns {JSONResponse} an empty JSONResponse with respective http status code
	 */
	public function setUserConfig($key, $allow) {
		$user = \OC_User::getUser();
		$this->config->setUserValue($user, $this->appName, $key, $allow);
		return new JSONResponse([], Http::STATUS_OK);
	}


	/**
	 * @NoAdminRequired
	 *
	 * retrieve appconfig (user setting)
	 *
	 * @param {String} key the identifier to retrieve
	 *
	 * @returns {string} the desired value or null if not existing
	 */
	public function getUserConfig($key) {
		$user = \OC_User::getUser();
		return $this->config->getUserValue($user, $this->appName, $key, 'null');
	}


	/**
	 * @NoAdminRequired
	 *
	 * returns an array of supported social networks
	 *
	 * @returns {array} array of the supported social networks
	 */
	public function getSupportedNetworks() : array {
		$isAdminEnabled = $this->config->getAppValue($this->appName, 'allowSocialSync', 'yes');
		if ($isAdminEnabled !== 'yes') {
			return array();
		}
		return array_keys(self::SOCIAL_CONNECTORS);
	}


	/**
	 * @NoAdminRequired
	 *
	 * Retrieves social profile data for a contact and updates the entry
	 *
	 * @param {String} addressbookId the addressbook identifier
	 * @param {String} contactId the contact identifier
	 * @param {String} network the social network to use (if unkown: take first match)
	 *
	 * @returns {JSONResponse} an empty JSONResponse with respective http status code
	 */
	public function updateContact(string $addressbookId, string $contactId, string $network) : JSONResponse {

		$url = null;

		try {
			// get corresponding addressbook
			$addressBook = $this->socialApiService->getAddressBook($addressbookId);
			if (is_null($addressBook)) {
				return new JSONResponse([], Http::STATUS_BAD_REQUEST);
			}

			// search contact in that addressbook, get social data
			$contact = $addressBook->search($contactId, ['UID'], ['types' => true])[0];
			if (!isset($contact['X-SOCIALPROFILE'])) {
				return new JSONResponse([], Http::STATUS_PRECONDITION_FAILED);
			}
			$socialprofiles = $contact['X-SOCIALPROFILE'];
			// retrieve data
			$url = $this->socialApiService->getSocialConnector(self::SOCIAL_CONNECTORS, $socialprofiles, $network);

			if (empty($url)) {
				return new JSONResponse([], Http::STATUS_NOT_IMPLEMENTED);
			}

			$opts = [
				"http" => [
					"method" => "GET",
					"header" => "User-Agent: Nextcloud Contacts App"
				]
			];
			$context = stream_context_create($opts);
			$socialdata = file_get_contents($url, false, $context);

			$photoTag = $this->socialApiService->getPhotoTag($contact['VERSION'], $http_response_header);

			if (!$socialdata || $photoTag === null) {
				return new JSONResponse([], Http::STATUS_NOT_FOUND);
			}

			// update contact
			$changes = array();
			$changes['URI'] = $contact['URI'];

			if (!empty($contact['PHOTO'])) {
				// overwriting without notice!
			}
			$changes['PHOTO'] = $photoTag . base64_encode($socialdata);

			if (isset($contact['PHOTO']) && $changes['PHOTO'] === $contact['PHOTO']) {
				return new JSONResponse([], Http::STATUS_NOT_MODIFIED);
			}

			$addressBook->createOrUpdate($changes, $addressbookId);
		}
		catch (Exception $e) {
			return new JSONResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new JSONResponse([], Http::STATUS_OK);
	}


	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * // FIXME: for testing purpose only
	 *
	 * Updates social profile data for all contacts of an addressbook
	 * // TODO: how to exclude certain contacts?
	 *
	 * @param {String} addressbookId the addressbook identifier
	 * @param {String} network the social network to use (fallback: take first match)
	 *
	 * @returns {JSONResponse} JSONResponse with the list of changed and failed contacts
	 */
	public function updateAddressbook(string $addressbookId, string $network) : JSONResponse {
	// FIXME: public or protected?

			$delay = 2;
			$response = [
				'updated' => array(),
				'checked' => array(),
				'failed' => array(),
			];

			// get corresponding addressbook
			$addressBook = $this->getAddressBook($addressbookId);
			if (is_null($addressBook)) {
				return new JSONResponse([], Http::STATUS_BAD_REQUEST);
			}

			// get contacts in that addressbook // FIXME: is there a better way?
			$contacts = $addressBook->search('', ['UID'], ['types' => true]);
			if (is_null($contacts)) {
				return new JSONResponse([], Http::STATUS_PRECONDITION_FAILED); 
			}

			// update one contact after another
			foreach ($contacts as $contact) {
				// delay to prevent rate limiting issues
				// FIXME: do we need to send an Http::STATUS_PROCESSING ?
				sleep($delay);

				try {
					$r = $this->updateContact($addressbookId, $contact['UID'], $network);

					if ($r->getStatus() === Http::STATUS_OK) {
						array_push($response['updated'], $contact['FN']);
					} elseif ($r->getStatus() === Http::STATUS_NOT_MODIFIED) {
						array_push($response['checked'], $contact['FN']);
					} else {
						if (!isset($response['failed'][$r->getStatus()])) {
							$response['failed'][$r->getStatus()] = array();
						}
						array_push($response['failed'][$r->getStatus()], $contact['FN']);
					}
				}
				catch (Exception $e) {
					array_push($response['failed'], $contact['FN']);
				}
			}
			return new JSONResponse([$response], Http::STATUS_OK);
	}
}
