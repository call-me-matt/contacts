<?php
/**
 * @copyright 2017 Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Contacts\Cron;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\IUser;
use OCP\IConfig;
use OCP\IUserManager;

use \OCA\Contacts\AppInfo\Application;
use \OCA\Contacts\Controller\SocialApiController;

class SocialUpdateRegistration extends \OC\BackgroundJob\TimedJob {

	private $appName;

	/** @var IUserManager */
	private $userManager;

	/** @var IJobList */
	private $jobList;

	/** @var IConfig */
	private $config;

	/**
	 * RegisterSocialUpdate constructor.
	 *
	 * @param IUserManager $userManager
	 * @param IJobList $jobList
	 */
	public function __construct(string $AppName,
					//  ITimeFactory $time,
					IUserManager $userManager,
					IConfig $config,
					IJobList $jobList) {
		//parent::__construct($time);
		
		$this->appName = $AppName;
		$this->userManager = $userManager;
		$this->config = $config;
		$this->jobList = $jobList;

		// Run once a week
		parent::setInterval(7 * 24 * 60 * 60);
	}

	/**
	 * @inheritDoc
	 */
	protected function run($arguments) {

		// check if admin allows for social updates:
		$isAdminEnabled = $this->config->getAppValue($this->appName, 'allowSocialSync', 'yes');
		if (!($isAdminEnabled === 'yes')) {
			return;
		}

		$this->userManager->callForSeenUsers(function (IUser $user) {

			// check if user did not opt-out:
			$isUserEnabled = $this->config->getUserValue($user->getUID(), $this->appName, 'enableSocialSync', 'yes');
			if ($isUserEnabled === 'yes') {
				$this->jobList->add(SocialUpdate::class, [
					'userId' => $user->getUID()
				]);
			}
		});
	}
}
