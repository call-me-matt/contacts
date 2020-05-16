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

namespace OCA\Contacts\Service;

use OCA\Contacts\Service\Social\CompositeSocialProvider;

use OCP\Contacts\IManager;
use OCP\IAddressBook;

use OCP\IConfig;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;

use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\CardDAV\ContactsManager;
use OCP\IURLGenerator;
use OCP\IL10N;


class SocialApiService {

	protected $appName;

	/** @var CompositeSocialProvider */
	private $socialProvider;
	/** @var IManager */
	private  $manager;
	/** @var IConfig */
	private  $config;
	/** @var IL10N  */
	private $l10n;
	/** @var IURLGenerator  */
	private $urlGen;
	/** @var CardDavBackend */
	private  $davBackend;

	public function __construct(string $AppName,
					CompositeSocialProvider $socialProvider,
					IManager $manager,
					IConfig $config,
					IL10N $l10n,
					IURLGenerator $urlGen,
					CardDavBackend $davBackend) {

		$this->appName = $AppName;
		$this->socialProvider = $socialProvider;
		$this->manager = $manager;
		$this->config = $config;
		$this->l10n = $l10n;
		$this->urlGen = $urlGen;
		$this->davBackend = $davBackend;
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
		return $this->socialProvider->getSupportedNetworks();
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
	 * Gets the addressbook of an addressbookId
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
	 * Retrieves and initiates all addressbooks from a user
	 *
	 * @param {string} user the user to query
	 * @param {IManager} the contact manager to load
	 */
	protected function registerAddressbooks($user, IManager $manager) {
		$cm = new ContactsManager($this->davBackend, $this->l10n);
		$cm->setupContactsProvider($manager, $user, $this->urlGen);
		//FIXME: better would be: davBackend->getUsersOwnAddressBooks($principal); (no shared or system address books)
		// ... or the promising IAddressBookProvider, which I cant get running
		$this->manager = $manager;
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
			$contact = $addressBook->search($contactId, ['UID'], ['types' => true])[0];
			if (!isset($contact['X-SOCIALPROFILE'])) {
				return new JSONResponse([], Http::STATUS_PRECONDITION_FAILED);
			}
			$socialprofiles = $contact['X-SOCIALPROFILE'];
			// retrieve data
			$url = $this->socialProvider->getSocialConnector($socialprofiles, $network);

			if (empty($url)) {
				return new JSONResponse([], Http::STATUS_NOT_IMPLEMENTED);
			}
			if ($url === 'invalid') {
				return new JSONResponse([], Http::STATUS_BAD_REQUEST);
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
	 *
	 * Updates social profile data for all contacts of an addressbook
	 * // TODO: how to exclude certain contacts?
	 *
	 * @param {String} network the social network to use (fallback: take first match)
	 * @param {String} user the address book owner
	 *
	 * @returns {JSONResponse} JSONResponse with the list of changed and failed contacts
	 */
	public function updateAddressbooks(string $network, string $user) : JSONResponse {

		// double check!
		$isAdminAllowed = $this->config->getAppValue($this->appName, 'allowSocialSync', 'yes');
		if (!($isAdminAllowed === 'yes')) {
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}
		$isUserEnabled = $this->config->getUserValue($user, $this->appName, 'enableSocialSync', 'no');
		if ($isUserEnabled !== 'yes') {
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$delay = 1;
		$response = [
			'updated' => array(),
			'checked' => array(),
			'failed' => array(),
		];

		// get corresponding addressbook
		$this->registerAddressbooks($user, $this->manager);

		$addressBooks = $this->manager->getUserAddressBooks();
		// TODO: filter out system addr books
		// TODO: keep only owned address books (not shared ones)

		foreach ($addressBooks as $addressBook) {
			if (is_null($addressBook)) {
				break;
			}

			// get contacts in that addressbook
			$contacts = $addressBook->search('', ['UID'], ['types' => true]);
			// TODO: filter for contacts with social profiles
			if (is_null($contacts)) {
				break;
			}

			// update one contact after another
			foreach ($contacts as $contact) {
				// delay to prevent rate limiting issues
				// FIXME: do we need to send an Http::STATUS_PROCESSING ?
				sleep($delay);

				try {
					$r = $this->updateContact($addressBook->getURI(), $contact['UID'], $network);

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
		}
		return new JSONResponse([$response], Http::STATUS_OK);
	}
}
