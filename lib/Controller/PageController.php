<?php
/**
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IInitialStateService;
use OCP\IConfig;
use OCP\IRequest;
use OCP\L10N\IFactory;
use OCP\Util;
use OCA\Contacts\Service\SocialApiService;

class PageController extends Controller {

	protected $appName;

	/** @var IConfig */
	private  $config;

	/** @var IInitialStateService */
	private $initialStateService;

	/** @var IFactory */
	private $languageFactory;

	/** @var SocialApiService */
	private $socialApi;

	public function __construct(string $appName,
								IRequest $request,
								IConfig $config,
								IInitialStateService $initialStateService,
								IFactory $languageFactory,
								SocialApiService $socialApi) {
		parent::__construct($appName, $request);

		$this->appName = $appName;
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->languageFactory = $languageFactory;

		$this->socialApi = $socialApi;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * Default routing
	 */
	public function index(): TemplateResponse {
		$userId = \OC_User::getUser();

		$locales = $this->languageFactory->findAvailableLocales();
		$defaultProfile = $this->config->getAppValue($this->appName, 'defaultProfile', 'HOME');
		$supportedNetworks = $this->socialApi->getSupportedNetworks();
		$allowSocialSync = $this->config->getAppValue($this->appName, 'allowSocialSync', 'yes');
		$enableSocialSync = $this->config->getUserValue($userId, $this->appName, 'enableSocialSync', 'yes');

		$this->initialStateService->provideInitialState($this->appName, 'locales', $locales);
		$this->initialStateService->provideInitialState($this->appName, 'defaultProfile', $defaultProfile);
		$this->initialStateService->provideInitialState($this->appName, 'supportedNetworks', $supportedNetworks);
		$this->initialStateService->provideInitialState($this->appName, 'allowSocialSync', $allowSocialSync);
		$this->initialStateService->provideInitialState($this->appName, 'enableSocialSync', $enableSocialSync);

		Util::addScript($this->appName, 'contacts');
		Util::addStyle($this->appName, 'contacts');

		return new TemplateResponse($this->appName, 'main');
	}
}
