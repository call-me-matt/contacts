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

use \OCA\Contacts\Controller\SocialApiController;

/*
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAddressBook;
*/
use OCP\IRequest;
use OCP\Contacts\IManager;
use OCP\IConfig;
use OCP\L10N\IFactory;
use OCP\Util;

class SocialUpdateService extends SocialApiController {

	protected $appName;

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
					IFactory $languageFactory) {

		parent::__construct($AppName, $request, $manager, $config, $languageFactory);

		$this->appName = $AppName;
		$this->languageFactory = $languageFactory;
		$this->manager = $manager;
		$this->config = $config;
	}


	/**
	 * @NoAdminRequired
	 *
	 * Creates a user notification about updated contacts
	 *
	 * @param {string} userId the user to notify
	 * @param {array} report the report to communicate
	 */
	protected function notifyUser(string $userId, array $report) {

		$changes = sizeof($report['updated']);
		if (!$changes) {
			return;
		}

		$names = implode(', ', $report['updated']);
		$now = new \DateTime();

		$manager = \OC::$server->getNotificationManager(); // FIXME: do propper call without OC::
		//$manager = $this->getContainer()->getServer()->getNotificationManager();
		$notification = $manager->createNotification();

		$notification->setApp($this->appName)
			->setUser('admin') // FIXME: for debugging. put $userId here later
			->setDateTime($now)
			->setObject('updated', $now->format('Y/m/d H:i:s'))
			->setSubject('updateSummary', [
					'changes' => $changes,
					'names' => $names
					]);

		$manager->notify($notification);
	}


	/**
	 * @NoAdminRequired
	 *
	 * Triggers a social update of all address books
	 *
	 */
	public function cronUpdate($userId) {
		// TODO: get all user owned address books

		// TODO: run updateAddressBook

		// notify user
		// FIXME: replace dummy content
		$msg = array();
		array_push($msg, $userId);
		$report = array('updated' =>  $msg);
		$this->notifyUser($userId, $report);
	}
}
