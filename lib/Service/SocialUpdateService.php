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

use OCP\Util;
use OCA\DAV\CardDAV\CardDavBackend;


class SocialUpdateService {

	protected $appName;

	/** @var CardDavBackend */
	private  $davBackend;

	public function __construct(string $AppName,
					CardDavBackend $davBackend) {

		$this->appName = $AppName;
		$this->davBackend = $davBackend;
	}

	/**
	 * @NoAdminRequired
	 *
	 * Retrieves all addressbooks from a user
	 *
	 * @param {string} user the user to query
	 *
	 * @returns {array} array of IAddressBooks
	 */
	protected function getUserAddressbooks(string $user): array {
		$addressbooks = array();
		$principal = 'principals/users/'.$user;

		$books = $this->davBackend->getUsersOwnAddressBooks($principal);
		foreach ($books as $book) {
			array_push($this->davBackend->getAddressBookById($book['id']), $addressbooks);
		}
		return $addressbooks;
		// FIXME: this seems to be only an array of arrays (not IAddressBooks)
		// TODO: try out OCA\DAV\CardDAV\IntegrationIAddressBookProvider, new in NCv19
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
	 * @param {string} userId the user to treat
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
