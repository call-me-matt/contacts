<?php
/**
 * @copyright Copyright (c) 2016, Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Matthias Heinisch <nextcloud@fmatthiasheinisch.de>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Contacts\Notification;


use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\IAction;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {

	/** @var string */
	protected $appName;
	/** @var IFactory */
	protected $l10nFactory;
	/** @var IURLGenerator */
	protected $url;
	/** @var IConfig */
	protected $config;

	public function __construct(string $appName,
								IFactory $l10nFactory,
								IURLGenerator $url,
								IConfig $config) {
		$this->appName = $appName;
		$this->l10nFactory = $l10nFactory;
		$this->url = $url;
		$this->config = $config;
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return $this->appName;
	}

	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName(): string {
		return $this->l10nFactory->get($this->appName)->t('SocialSync');
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== $this->appName) {
			// Not my app => throw
			throw new \InvalidArgumentException();
		}

		$parameters = $notification->getSubjectParameters();
		$l = $this->l10nFactory->get($this->appName, $languageCode);

		switch ($notification->getSubject()) {
			case 'updateSummary':
				try {
					$notification->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'categories/social.svg')));

					$notification->setParsedSubject($l->t('SocialSync performed: %d contacts updated', [$parameters['changes']]));

					$names = $parameters['names'];
					$notification->setParsedMessage($names);

					return $notification;
				} catch (Exception $e) {
					throw new \InvalidArgumentException();
				}

			default:
				// Unknown subject => Unknown notification => throw
				throw new \InvalidArgumentException();
		}
	}
}
