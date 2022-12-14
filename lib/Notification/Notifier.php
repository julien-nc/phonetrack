<?php
/**
 * Nextcloud - phonetrack
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2018
 */

namespace OCA\PhoneTrack\Notification;


use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

use OCA\PhoneTrack\AppInfo\Application;

class Notifier implements INotifier {

	/** @var IFactory */
	protected $factory;

	/** @var IUserManager */
	protected $userManager;

	/** @var INotificationManager */
	protected $notificationManager;

	/** @var IURLGenerator */
	protected $url;

	/**
	 * @param IFactory $factory
	 * @param IUserManager $userManager
	 * @param INotificationManager $notificationManager
	 * @param IURLGenerator $urlGenerator
	 */
	public function __construct(IFactory $factory, IUserManager $userManager, INotificationManager $notificationManager, IURLGenerator $urlGenerator) {
		$this->factory = $factory;
		$this->userManager = $userManager;
		$this->notificationManager = $notificationManager;
		$this->url = $urlGenerator;
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return 'phonetrack';
	}
	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName(): string {
		return $this->lFactory->get('phonetrack')->t('PhoneTrack');
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 * @since 9.0.0
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'phonetrack') {
			// Not my app => throw
			throw new \InvalidArgumentException();
		}

		$l = $this->factory->get('phonetrack', $languageCode);

		switch ($notification->getSubject()) {
		case 'enter_geofence':
			$p = $notification->getSubjectParameters();
			$content = $l->t('In session "%s", device "%s" entered geofencing zone "%s".', [$p[0], $p[1], $p[2]]);

			$notification->setParsedSubject($content)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app_black.svg')))
				->setLink($this->url->linkToRouteAbsolute('phonetrack.page.index'));
			return $notification;
		case 'leave_geofence':
			$p = $notification->getSubjectParameters();
			$content = $l->t('In session "%s", device "%s" exited geofencing zone "%s".', [$p[0], $p[1], $p[2]]);

			$notification->setParsedSubject($content)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app_black.svg')))
				->setLink($this->url->linkToRouteAbsolute('phonetrack.page.index'));
			return $notification;

		case 'close_proxim':
			$p = $notification->getSubjectParameters();
			$content = $l->t('Device "%s" is now closer than %sm to "%s".', [$p[0], $p[1], $p[2]]);

			$notification->setParsedSubject($content)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app_black.svg')))
				->setLink($this->url->linkToRouteAbsolute('phonetrack.page.index'));
			return $notification;
		case 'far_proxim':
			$p = $notification->getSubjectParameters();
			$content = $l->t('Device "%s" is now farther than %sm from "%s".', [$p[0], $p[1], $p[2]]);

			$notification->setParsedSubject($content)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app_black.svg')))
				->setLink($this->url->linkToRouteAbsolute('phonetrack.page.index'));
			return $notification;

		case 'quota_reached':
			$p = $notification->getSubjectParameters();
			$content = $l->t('Point number quota (%s) was reached with a point of "%s" in session "%s".', [$p[0], $p[1], $p[2]]);

			$notification->setParsedSubject($content)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app_black.svg')))
				->setLink($this->url->linkToRouteAbsolute('phonetrack.page.index'));
			return $notification;

		case 'add_user_share':
			$p = $notification->getSubjectParameters();
			$content = $l->t('User "%s" shared PhoneTrack session "%s" with you.', [$p[0], $p[1]]);

			$notification->setParsedSubject($content)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app_black.svg')))
				->setLink($this->url->linkToRouteAbsolute('phonetrack.page.index'));
			return $notification;

		case 'delete_user_share':
			$p = $notification->getSubjectParameters();
			$content = $l->t('User "%s" stopped sharing PhoneTrack session "%s" with you.', [$p[0], $p[1]]);

			$notification->setParsedSubject($content)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app_black.svg')))
				->setLink($this->url->linkToRouteAbsolute('phonetrack.page.index'));
			return $notification;

		default:
			// Unknown subject => Unknown notification => throw
			throw new \InvalidArgumentException();
		}
	}
}
