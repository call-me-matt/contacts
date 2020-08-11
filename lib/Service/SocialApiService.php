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
use OCA\Contacts\AppInfo\Application;

use OCP\Contacts\IManager;
use OCP\IAddressBook;

use OCP\Util;
use OCP\IConfig;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Http\Client\IClientService;
use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\CardDAV\ContactsManager;
use OCP\IURLGenerator;
use OCP\IL10N;

class SocialApiService {
	private $appName;
	/** @var CompositeSocialProvider */
	private $socialProvider;
	/** @var IManager */
	private $manager;
	/** @var IConfig */
	private $config;
	/** @var IClientService */
	private $clientService;
	/** @var IL10N  */
	private $l10n;
	/** @var IURLGenerator  */
	private $urlGen;
	/** @var CardDavBackend */
	private $davBackend;


	public function __construct(
					CompositeSocialProvider $socialProvider,
					IManager $manager,
					IConfig $config,
					IClientService $clientService,
					IL10N $l10n,
					IURLGenerator $urlGen,
					CardDavBackend $davBackend) {
		$this->appName = Application::APP_ID;
		$this->socialProvider = $socialProvider;
		$this->manager = $manager;
		$this->config = $config;
		$this->clientService = $clientService;
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
		$syncAllowedByAdmin = $this->config->getAppValue($this->appName, 'allowSocialSync', 'yes');
		if ($syncAllowedByAdmin !== 'yes') {
			return [];
		}
		return $this->socialProvider->getSupportedNetworks();
	}


	/**
	 * @NoAdminRequired
	 *
	 * Adds/updates photo for contact
	 *
	 * @param {pointer} contact reference to the contact to update
	 * @param {string} imageType the image type of the photo
	 * @param {string} photo the photo as base64 string
	 */
	protected function addPhoto(array &$contact, string $imageType, string $photo) {
		$version = $contact['VERSION'];

		if (!empty($contact['PHOTO'])) {
			// overwriting without notice!
		}

		if ($version >= 4.0) {
			// overwrite photo
			$contact['PHOTO'] = "data:" . $imageType . ";base64," . $photo;
		} elseif ($version >= 3.0) {
			// add new photo
			$imageType = str_replace('image/', '', $imageType);
			$contact['PHOTO;ENCODING=b;TYPE=' . $imageType . ';VALUE=BINARY'] = $photo;

			// remove previous photo (necessary as new attribute is not equal to 'PHOTO')
			$contact['PHOTO'] = '';
		}
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
		foreach ($addressBooks as $ab) {
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
	 * @param {string} userId the user to query
	 * @param {IManager} the contact manager to load
	 */
	protected function registerAddressbooks($userId, IManager $manager) {
		$coma = new ContactsManager($this->davBackend, $this->l10n);
		$coma->setupContactsProvider($manager, $userId, $this->urlGen);
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
				return new JSONResponse([], Http::STATUS_BAD_REQUEST);
			}

			$httpResult = $this->clientService->NewClient()->get($url);
			$socialdata = $httpResult->getBody();
			$imageType = $httpResult->getHeader('content-type');

			if (!$socialdata || $imageType === null) {
				return new JSONResponse([], Http::STATUS_NOT_FOUND);
			}

			// update contact
			$changes = [];
			$changes['URI'] = $contact['URI'];
			$changes['VERSION'] = $contact['VERSION'];
			$this->addPhoto($changes, $imageType, base64_encode($socialdata));

			if (isset($contact['PHOTO']) && $changes['PHOTO'] === $contact['PHOTO']) {
				return new JSONResponse([], Http::STATUS_NOT_MODIFIED);
			}

			$addressBook->createOrUpdate($changes, $addressbookId);
		} catch (Exception $e) {
			return new JSONResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new JSONResponse([], Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Stores the result of social avatar updates for each contact
	 * (used during batch updates in updateAddressbooks)
	 *
	 * @param {array} report where the results are added
	 * @param {String} entry the element to add
	 * @param {string} status the (http) status code
	 *
	 * @returns {array} the report including the new entry
	 */
	protected function registerUpdateResult(array $report, string $entry, string $status) : array {
		// initialize report on first call
		if (empty($report)) {
			$report = [
				'updated' => [],
				'checked' => [],
				'failed' => [],
			];
		}
		// add entry to respective sub-array
		switch ($status) {
			case Http::STATUS_OK:
				array_push($report['updated'], $entry);
				break;
			case Http::STATUS_NOT_MODIFIED:
				array_push($report['checked'], $entry);
				break;
			default:
				if (!isset($report['failed'][$status])) {
					$report['failed'][$status] = [];
				}
				array_push($report['failed'][$status], $entry);
		}
		return $report;
	}


	/**
	 * @NoAdminRequired
	 *
	 * Updates social profile data for all contacts of an addressbook
	 *
	 * @param {String} network the social network to use (fallback: take first match)
	 * @param {String} userId the address book owner
	 *
	 * @returns {JSONResponse} JSONResponse with the list of changed and failed contacts
	 */
	public function updateAddressbooks(string $network, string $userId) : JSONResponse {

		// double check!
		$syncAllowedByAdmin = $this->config->getAppValue($this->appName, 'allowSocialSync', 'yes');
		$bgSyncEnabledByUser = $this->config->getUserValue($userId, $this->appName, 'enableSocialSync', 'no');
		if (($syncAllowedByAdmin !== 'yes') || ($bgSyncEnabledByUser !== 'yes')) {
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$delay = 1;
		$response = [];

		// get corresponding addressbook
		$this->registerAddressbooks($userId, $this->manager);
		$addressBooks = $this->manager->getUserAddressBooks();

		foreach ($addressBooks as $addressBook) {
			if ((is_null($addressBook) ||
				(Util::getVersion()[0] >= 20) &&
				//TODO: remove version check ^ when dependency for contacts is min NCv20 (see info.xml)
				($addressBook->isShared() || $addressBook->isSystemAddressBook()))) {
				// TODO: filter out deactivated books, see https://github.com/nextcloud/server/issues/17537
				continue;
			}

			// get contacts in that addressbook
			$contacts = $addressBook->search('', ['X-SOCIALPROFILE'], ['types' => true]);

			// update one contact after another
			foreach ($contacts as $contact) {
				// delay to prevent rate limiting issues
				// TODO: do we need to send an Http::STATUS_PROCESSING ?
				sleep($delay);

				try {
					$r = $this->updateContact($addressBook->getURI(), $contact['UID'], $network);
					$response = $this->registerUpdateResult($response, $contact['FN'], $r->getStatus());
				} catch (Exception $e) {
					$response = $this->registerUpdateResult($response, $contact['FN'], '-1');
				}
			}
		}
		return new JSONResponse([$response], Http::STATUS_OK);
	}
}
