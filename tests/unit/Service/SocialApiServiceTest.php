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

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\Contacts\IManager;
use OCP\IAddressBook;

use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\CardDAV\ContactsManager;
use OCP\IURLGenerator;
use OCP\IL10N;

use PHPUnit\Framework\MockObject\MockObject;
use ChristophWurst\Nextcloud\Testing\TestCase;


class SocialApiServiceTest extends TestCase {

	private $service;

	/** @var IManager|MockObject */
	private  $manager;
	/** @var IConfig|MockObject */
	private  $config;
	/** @var IL10N|MockObject */
	private $l10n;
	/** @var IURLGenerator|MockObject */
	private $urlGen;
	/** @var CardDavBackend|MockObject */
	private  $davBackend;

	public function setUp() {
		parent::setUp();

		$this->manager = $this->createMock(IManager::class);
		$this->config = $this->createMock(IConfig::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->urlGen = $this->createMock(IURLGenerator::class);
		$this->davBackend = $this->createMock(CardDavBackend::class);
		$this->service = new SocialApiService(
			'contacts',
			$this->manager,
			$this->config,
			$this->l10n,
			$this->urlGen,
			$this->davBackend
		);
	}

	public function socialProfileProvider() {
		return [
			'no social profiles'	 	=> ['any',	 array(),							new JSONResponse([], Http::STATUS_NOT_IMPLEMENTED)],
			'facebook profile' 		=> ['facebook', [array('type' => 'facebook', 'value' => '4')],		new JSONResponse([], Http::STATUS_OK)],
			'facebook invalid profile' 	=> ['facebook', [array('type' => 'facebook', 'value' => 'zuck')],		new JSONResponse([], Http::STATUS_NOT_FOUND)],
			'facebook public page' 	=> ['facebook', [array('type' => 'facebook', 'value' => 'Nextclouders')],	new JSONResponse([], Http::STATUS_OK)],
			'instagram profile'		=> ['instagram', [array('type' => 'instagram', 'value' => 'zuck')],		new JSONResponse([], Http::STATUS_OK)],
			'instagram invalid profile'	=> ['instagram', [array('type' => 'instagram', 'value' => '@zuck')],		new JSONResponse([], Http::STATUS_BAD_REQUEST)],
			'tumblr profile' 		=> ['tumblr',	[array('type' => 'tumblr', 'value' => 'nextcloudperu')],	new JSONResponse([], Http::STATUS_OK)],
			'tumblr invalid profile'	=> ['tumblr',	[array('type' => 'tumblr', 'value' => '@nextcloudperu')],	new JSONResponse([], Http::STATUS_NOT_FOUND)],
			'invalid insta, valid tumblr'	=> ['any',	[array('type' => 'instagram', 'value' => '@zuck'), array('type' => 'tumblr', 'value' => 'nextcloudperu')],	new JSONResponse([], Http::STATUS_OK)],
			'invalid fb, valid tumblr'	=> ['any',	[array('type' => 'facebook', 'value' => '@zuck'), array('type' => 'tumblr', 'value' => 'nextcloudperu')],	new JSONResponse([], Http::STATUS_NOT_FOUND)],
		];
	}


	public function testSupportedNetworks() {

		$this->config
			->method('getAppValue')
			->willReturn('yes');

		$result = $this->service->getSupportedNetworks();

		$this->assertContains('facebook', $result);
		$this->assertContains('instagram', $result);
		$this->assertContains('tumblr', $result);
	}

	public function testDeactivatedSocial() {
		$this->config
			->method('getAppValue')
			->willReturn('no');

		$result = $this->service->getSupportedNetworks();

		$this->assertEmpty($result);
	}


	/**
	 * @dataProvider socialProfileProvider
	 */
	public function testUpdateContact($network, $social, $expected) {

		$contact = [
			'URI' => '3225c0d5-1bd2-43e5-a08c-4e65eaa406b0',
			'VERSION' => '4.0',
			'PHOTO' => '-',
			'X-SOCIALPROFILE' => $social,
		];
		$addressbook = $this->createMock(IAddressBook::class);
		$addressbook
			->method('getUri')
			->willReturn('contacts');
		$addressbook
			->method('search')
			->willReturn(array($contact));

		$this->manager
			->method('getUserAddressBooks')
			->willReturn(array($addressbook));

		$result = $this->service->updateContact('contacts', '3225c0d5-1bd2-43e5-a08c-4e65eaa406b0', $network);

		$this->assertEquals($expected, $result);

		// insert delay to prevent rate limiting exceptions
		sleep(0.7);
	}

	/* TODO: needs propper stub of davBackend
	public function testUpdateAddressbooks() {

		$this->config
			->method('getAppValue')
			->willReturn('yes');
		$this->config
			->method('getUserValue')
			->willReturn('yes');

		$contact = [
			'URI' => '3225c0d5-1bd2-43e5-a08c-4e65eaa406b0',
			'VERSION' => '4.0',
			'PHOTO' => '-',
			'X-SOCIALPROFILE' => [array('type' => 'facebook', 'value' => '4')],
		];
		$addressbook = $this->createMock(IAddressBook::class);
		$addressbook
			->method('getUri')
			->willReturn('contacts');
		$addressbook
			->method('search')
			->willReturn(array($contact));

		$this->manager
			->method('getUserAddressBooks')
			->willReturn(array($addressbook));

		$this->davBackend
			->method('getAddressBooksForUser')
			->willReturn(array(array$addressbook)); // FIXME: this is not correct

		$result = $this->service->updateAddressbooks('facebook', 'admin');

		$this->assertEquals($expected, $result);
	}
	*/
}
