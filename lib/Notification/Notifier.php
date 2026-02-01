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

use OCA\PhoneTrack\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;

use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {

	public function __construct(
		private IFactory $lFactory,
		private IURLGenerator $url,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->lFactory->get(Application::APP_ID)->t('PhoneTrack');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			// Not my app => throw
			throw new UnknownNotificationException();
		}

		$l10n = $this->lFactory->get(Application::APP_ID, $languageCode);

		switch ($notification->getSubject()) {
			case 'enter_geofence':
				$p = $notification->getSubjectParameters();
				$content = $l10n->t('In session "%s", device "%s" entered geofencing zone "%s".', [$p[0], $p[1], $p[2]]);

				$notification->setParsedSubject($content)
					->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app_black.svg')))
					->setLink($this->url->linkToRouteAbsolute('phonetrack.page.index'));
				return $notification;
			case 'leave_geofence':
				$p = $notification->getSubjectParameters();
				$content = $l10n->t('In session "%s", device "%s" exited geofencing zone "%s".', [$p[0], $p[1], $p[2]]);

				$notification->setParsedSubject($content)
					->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app_black.svg')))
					->setLink($this->url->linkToRouteAbsolute('phonetrack.page.index'));
				return $notification;

			case 'close_proxim':
				$p = $notification->getSubjectParameters();
				$content = $l10n->t('Device "%s" is now closer than %sm to "%s".', [$p[0], $p[1], $p[2]]);

				$notification->setParsedSubject($content)
					->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app_black.svg')))
					->setLink($this->url->linkToRouteAbsolute('phonetrack.page.index'));
				return $notification;
			case 'far_proxim':
				$p = $notification->getSubjectParameters();
				$content = $l10n->t('Device "%s" is now farther than %sm from "%s".', [$p[0], $p[1], $p[2]]);

				$notification->setParsedSubject($content)
					->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app_black.svg')))
					->setLink($this->url->linkToRouteAbsolute('phonetrack.page.index'));
				return $notification;

			case 'quota_reached':
				$p = $notification->getSubjectParameters();
				$content = $l10n->t('Point number quota (%s) was reached with a point of "%s" in session "%s".', [$p[0], $p[1], $p[2]]);

				$notification->setParsedSubject($content)
					->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app_black.svg')))
					->setLink($this->url->linkToRouteAbsolute('phonetrack.page.index'));
				return $notification;

			case 'add_user_share':
				$p = $notification->getSubjectParameters();
				$content = $l10n->t('User "%s" shared PhoneTrack session "%s" with you.', [$p[0], $p[1]]);

				$notification->setParsedSubject($content)
					->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app_black.svg')))
					->setLink($this->url->linkToRouteAbsolute('phonetrack.page.index'));
				return $notification;

			case 'delete_user_share':
				$p = $notification->getSubjectParameters();
				$content = $l10n->t('User "%s" stopped sharing PhoneTrack session "%s" with you.', [$p[0], $p[1]]);

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
