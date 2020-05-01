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

use OCP\IConfig;
use OCP\Contacts\IManager;
use OCP\IAddressBook;
use OCP\L10N\IFactory;
use OCP\IRequest;
use OCP\Util;


class SocialUpdateService {

	protected $appName;

	//** @var IInitialStateService */
	// private  $initialStateService;

	/** @var IFactory */
	private  $languageFactory;
	/** @var IManager */
	private  $manager;
	/** @var IConfig */
	private  $config;

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


// FIXME: only for testing, dummy notification
	/**
	 * @NoAdminRequired
	 *
	 * Creates a user notification if contacts have been updated
	 *
	 * @param {string} addressbookId the ID of the affected addressbook
	 * @param {array} report the report to communicate
	 */
	public function doCronNotify() {
		$now = new \DateTime();

		$manager = \OC::$server->getNotificationManager(); // FIXME: do propper call without OC::
		//$manager = $this->getContainer()->getServer()->getNotificationManager();
		$notification = $manager->createNotification();

		$notification->setApp($this->appName)
			->setUser('admin') // FIXME: how to get the addressbook owner?
			->setDateTime($now)
			->setObject('updated', $now->format('Y/m/d H:i:s'))
			->setSubject('updateSummary', [
					'addressbook' => 'filled_later',
					'changes' => 999,
					'names' => 'Notification from the Service'
					]);

		$manager->notify($notification);
	}

}
