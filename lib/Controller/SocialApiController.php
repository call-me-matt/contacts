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

use OCP\IConfig;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

use OCA\Contacts\Service\SocialApiService;
use OCA\Contacts\AppInfo\Application;


class SocialApiController extends ApiController {

	/** @var IConfig */
	private  $config;
	/** @var SocialApiService */
	private  $socialApiService;

	public function __construct(
					IRequest $request,
					IConfig $config,
					SocialApiService $socialApiService) {
		parent::__construct(Application::APP_ID, $request);

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
		$permittedKeys = ['allowSocialSync'];
		if (!in_array($key, $permittedKeys)) {
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}
		$this->config->setAppValue(Application::APP_ID, $key, $allow);
		return new JSONResponse([], Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 *
	 * returns an array of supported social networks
	 *
	 * @returns {array} array of the supported social networks
	 */
	public function getSupportedNetworks() : array {
		return $this->socialApiService->getSupportedNetworks();
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
		return $this->socialApiService->updateContact($addressbookId, $contactId, $network);
	}
}
