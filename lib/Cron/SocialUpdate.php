<?php
/**
 * @copyright 2020 Matthias Heinisch <nextcloud@matthiasheinisch.de>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Contacts\Cron;

use \OCA\Contacts\AppInfo\Application;
use OCA\Contacts\Service\SocialApiService;
use OCP\Util;

class SocialUpdate extends \OC\BackgroundJob\QueuedJob {

    private $appName;

    /** @var SocialUpdateService */
    private $social;

    public function __construct(string $AppName, SocialApiService $social)
    {
	$this->appName = $AppName;
	$this->social = $social;
    }

    protected function run($arguments) {
	$userId = $arguments['userId'];

	// update contacts with first available social media profile
	$this->social->updateAddressbooks('any', $userId);
    }
}
