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
		'Instagram' 	=> [
			'recipe' 	=> 'https://www.instagram.com/{socialId}/?__a=1',
			'cleanups' 	=> ['basename', 'json' => 'graphql->user->profile_pic_url_hd'],
		],
		'Facebook' 	=> [
			'recipe' 	=> 'https://graph.facebook.com/{socialId}/picture?width=720',
			'cleanups' 	=> ['basename'],
		],
		'Tumblr' 	=> [
			'recipe' 	=> 'https://api.tumblr.com/v2/blog/{socialId}/avatar/512',
			'cleanups' 	=> ['regex' => '/(?:http[s]*\:\/\/)*(.*?)\.(?=[^\/]*\..{2,5})/i', 'group' => 1], // "subdomain"
		],
		/* untrusted
		'instagram' 	=> [
			'recipe' 	=> 'http://avatars.io/instagram/{socialId}',
			'cleanups' 	=> ['basename'],
		],
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
	 */
	public function setConfig($key, $allow) {
		$this->config->setAppValue($this->appName, $key, $allow);
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
}
