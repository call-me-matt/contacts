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

use DateTime;

class SocialApiController extends ApiController {

	protected $appName;

	//** @var IInitialStateService */
	// private  $initialStateService;

	/** @var IFactory */
	private  $languageFactory;
	/** @var IManager */
	private  $manager;
	/** @var IConfig */
	private  $config;

	/**
	 * This constant stores the supported social networks
	 * It is an ordered list, so that first listed items will be checked first
	 * Each item stores the avatar-url-formula as recipe and a cleanup parameter to
	 * extract the profile-id from the users entry
	 * 
	 * @const {array} SOCIAL_CONNECTORS dictionary of supported social networks
	 */
	const SOCIAL_CONNECTORS = [
		'facebook' 	=> [
			'recipe' 	=> 'https://graph.facebook.com/{socialId}/picture?width=720',
			'cleanups' 	=> ['basename'],
		],
		'tumblr' 	=> [
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
								IFactory $languageFactory) {
		parent::__construct($AppName, $request);

		$this->appName = $AppName;
		// $this->initialStateService = $initialStateService;
		$this->languageFactory = $languageFactory;
		$this->manager = $manager;
		$this->config = $config;
	}


	/**
	 * @NoAdminRequired
	 *
	 * returns an array of supported social networks
	 *
	 * @returns {array} array of the supported social networks
	 */
	public function getSupportedNetworks() : array {
		return array_keys(self::SOCIAL_CONNECTORS);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Creates the photo start tag for the vCard
	 *
	 * @param {float} version the version of the vCard
	 * @param {array} header the http response headers containing the image type
	 *
	 * @returns {String} the photo start tag or null in case of errors
	 */
	protected function getPhotoTag(float $version, array $header) : ?string {

		$type = null;

		// get image type from headers
		foreach ($header as $value) {
			if (preg_match('/^Content-Type:/i', $value)) {
				if (stripos($value, "image") !== false) {
					$type = substr($value, stripos($value, "image"));
				}
			}
		}
		if (is_null($type)) {
			return null;
		}

		// return respective photo tag
		if ($version >= 4.0) {
			return "data:" . $type . ";base64,";
		}

		if ($version >= 3.0) {
			$type = str_replace('image/', '', $type);
			return "ENCODING=b;TYPE=" . strtoupper($type) . ":";
		}

		return null;
	}


	/**
	 * @NoAdminRequired
	 *
	 * generate download url for a social entry
	 *
	 * @param {array} socialentries all social data from the contact
	 * @param {String} network the choice which network to use (fallback: take first match)
	 *
	 * @returns {String} the url to the requested information or null in case of errors
	 */
	protected function getSocialConnector(array $socialEntries, string $network) : ?string {

		$connector = null;
		$selection = self::SOCIAL_CONNECTORS;
		// check if dedicated network selected
		if (isset(self::SOCIAL_CONNECTORS[$network])) {
			$selection = array($network => self::SOCIAL_CONNECTORS[$network]);
		}

		// check selected networks in order
		foreach($selection as $socialNetSelected => $socialRecipe) {

			// search for this network in user's profile
			foreach ($socialEntries as $socialNetwork => $profileId) {

				if ($socialNetSelected === strtolower($socialNetwork)) {
					// cleanups: extract social id
					if (in_array('basename', $socialRecipe['cleanups'])) {
						$profileId = basename($profileId);
					}
					if (array_key_exists('regex', $socialRecipe['cleanups'])) {
						if (preg_match($socialRecipe['cleanups']['regex'], $profileId, $matches)) {
							$profileId = $matches[$socialRecipe['cleanups']['group']];
						}
					}
					$connector = str_replace("{socialId}", $profileId, $socialRecipe['recipe']);
					break;
				}
			}
			if ($connector) {
				break;
			}
		}
		return ($connector);
	}


	/**
	 * @NoAdminRequired
	 *
	 * Gets the addressbook from an addressbookId
	 *
	 * @param {String} addressbookId the identifier of the addressbook
	 *
	 * @returns {IAddressBook} the corresponding addressbook or null
	 */
	protected function getAddressBook(string $addressbookId) : ?IAddressBook {
		$addressBook = null;
		$addressBooks = $this->manager->getUserAddressBooks();
		foreach($addressBooks as $ab) {
			if ($ab->getUri() === $addressbookId) {
				$addressBook = $ab;
			}
		}
		return $addressBook;
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
			$addressBook = $this->getAddressBook($addressbookId);
			if (is_null($addressBook)) {
				return new JSONResponse([], Http::STATUS_BAD_REQUEST);
			}

			// search contact in that addressbook, get social data
			$contact = $addressBook->search($contactId, ['UID'], [])[0];
			$socialprofiles = $contact['X-SOCIALPROFILE'];
			if (is_null($socialprofiles)) {
				return new JSONResponse([], Http::STATUS_PRECONDITION_FAILED);
			}

			// retrieve data
			$url = $this->getSocialConnector($socialprofiles, $network);

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

			$photoTag = $this->getPhotoTag($contact['VERSION'], $http_response_header);

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

			if ($changes['PHOTO'] === $contact['PHOTO']) {
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
	 *
	 * Creates a user notification if contacts have been updated
	 *
	 * @param {string} addressbookId the ID of the affected addressbook
	 * @param {array} report the report to communicate
	 */
	protected function notifyUser(string $addressbookId, array $report) {

		$changes = sizeof($report['updated']);
		if (!$changes) {
			return;
		}

		$names = implode(', ', $report['updated']);
		$now = new DateTime();

		$manager = \OC::$server->getNotificationManager(); // FIXME: do propper call without OC::
		//$manager = $this->getContainer()->getServer()->getNotificationManager();
		$notification = $manager->createNotification();

		$notification->setApp($this->appName)
			->setUser('admin') // FIXME: how to get the addressbook owner?
			->setDateTime($now)
			->setObject('updated', $now->format('Y/m/d H:i:s'))
			->setSubject('updateSummary', [
					'addressbook' => $addressbookId,
					'changes' => $changes,
					'names' => $names
					]);

		$manager->notify($notification);
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

			// notify user
			$this->notifyUser($addressbookId, $response);

			return new JSONResponse([$response], Http::STATUS_OK);
	}
}
