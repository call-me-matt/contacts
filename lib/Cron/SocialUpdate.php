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
use \OCA\Contacts\Service\SocialUpdateService;
use \OCA\Contacts\Controller\SocialApiController;

use OCP\IRequest;
use OCP\Contacts\IManager;
use OCP\IConfig;
use OCP\L10N\IFactory;
use OCP\Util;
use OCA\DAV\CardDAV\CardDavBackend;

class SocialUpdate extends \OC\BackgroundJob\TimedJob {

    /** @var SocialUpdateService */
    private $social;

    public function __construct(string $AppName,
					IRequest $request,
					IManager $manager,
					IConfig $config,
					IFactory $languageFactory,
					CardDavBackend $davBackend)
    {

	$this->social = new SocialUpdateService($AppName, $request, $manager, $config, $languageFactory, $davBackend);

        // Run once a week
        // parent::setInterval(7 * 24 * 60 * 60);

        // FIXME: for testing -- Run every x seconds
        parent::setInterval(10);
    }

    protected function run($arguments) {
	$userId = $arguments['userId'];
        $this->social->cronUpdate($userId);
    }

}

