<?php
/**
 * @copyright Copyright (c) 2020 Matthias Heinisch <contacts@matthiasheinisch.de>
 *
 * @author Matthias Heinisch <contacts@matthiasheinisch.de>
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
// use OCP\IInitialStateService;
use OCP\IConfig;
use OCP\L10N\IFactory;
use OCP\IRequest;

class AvatarController extends Controller {

	protected $appName;

	// /** @var IInitialStateService */
	// private $initialStateService;

	/** @var IFactory */
	private $languageFactory;
	/** @var IConfig */
	private  $config;

	public function __construct(string $AppName,
								IRequest $request,
								IConfig $config,
								// IInitialStateService $initialStateService,
								IFactory $languageFactory) {
		parent::__construct($AppName, $request);

		$this->appName = $AppName;
		// $this->initialStateService = $initialStateService;
		$this->languageFactory = $languageFactory;
		$this->config = $config;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * Overview page to update avatars from social media
	 * for a complete addressbook
	 */
	public function view(): TemplateResponse {
		return new TemplateResponse(
			'contacts',
			'avatars'); // templates/avatars.php
	}


	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * Retrieves the social profile picture for a contact
	 *
	 * param id profile identifier
	 * param network from where to retrieve
	 */
	public function fetch($network, $id) {
		$url = "";
		$response = 404;

		try {
			// add your social networks here!
			if ($network == 'facebook') {
				$url = "https://graph.facebook.com/" . ($id) . "/picture?width=720";
			} else {
				$response = 400;
				throw new Exception('Unknown network');
			}

			$host = parse_url($url);
			if (!$host) {
				$response = 404;
				throw new Exception('Could not parse URL');
			}
			$opts = [
				"http" => [
					"method" => "GET",
					"header" => "User-Agent: Nextcloud Contacts App"
				]
			];
			$context = stream_context_create($opts);
			$image = file_get_contents($url, false, $context);
			if (!$image) {
				throw new Exception('Could not parse URL');
				$response = 404;
			} else {
				$response = 200;
				header("Content-type:image/png");
				echo $image;
			}
		} 
		catch (Exception $e) {
		}

		http_response_code($response);
		exit;
	}
}
