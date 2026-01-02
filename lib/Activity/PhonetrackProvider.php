<?php

/**
 * @copyright Copyright (c) 2019 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
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

namespace OCA\PhoneTrack\Activity;

use OCA\PhoneTrack\AppInfo\Application;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\Activity\IEvent;
use OCP\Activity\IProvider;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;

class PhonetrackProvider implements IProvider {

	public function __construct(
		private IURLGenerator $url,
		private ActivityManager $activityManager,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private IL10N $l,
	) {
	}

	private function getUserParam(IEvent $event): array {
		$author = $event->getAuthor();
		if ($author !== '') {
			// there is an author, the user might not exist anymore
			$user = $this->userManager->get($author);
			if ($user !== null) {
				return [
					'type' => 'user',
					'id' => $author,
					'name' => $user->getDisplayName()
				];
			} else {
				return [
					'type' => 'user',
					'id' => '0',
					'name' => $this->l->t('Deleted user (%s)', [$author]),
				];
			}
		} else {
			// if there is no activity entry author, look for the author in the subject parameters
			$subjectParams = $event->getSubjectParameters();

			if (isset($subjectParams['author'])) {
				$user = $this->userManager->get($subjectParams['author']);
				// for the activity emails (taken from oc_activity_mq in which there is no entry author...)
				// we need to get the author from the subject params
				// (that's why we pass an author param when triggering the event even if the entry author is set)
				if ($user !== null) {
					return [
						'type' => 'user',
						'id' => $subjectParams['author'],
						'name' => $user->getDisplayName()
					];
				} else {
					// if the author is not found as a user, it is a name
					return [
						'type' => 'user',
						'id' => '0',
						'name' => $subjectParams['author'] ?: $this->l->t('Unknown author'),
					];
				}
			} elseif (isset($subjectParams['share_label'])) {
				// new way: we get the share label
				return [
					'type' => 'user',
					'id' => '0',
					'name' => $subjectParams['share_label']
						? $this->l->t('Shared access (%s)', [$subjectParams['share_label']])
						: $this->l->t('Shared access'),
				];
			} else {
				// fallback, this should never happen
				return [
					'type' => 'user',
					'id' => '0',
					'name' => $this->l->t('No author'),
				];
			}
		}
	}

	public function parse($language, IEvent $event, ?IEvent $previousEvent = null) {
		if ($event->getApp() !== Application::APP_ID) {
			throw new UnknownActivityException();
		}

		$event = $this->getIcon($event);

		$subjectIdentifier = $event->getSubject();
		$subjectParams = $event->getSubjectParameters();
		$ownActivity = ($event->getAuthor() === $event->getAffectedUser());
		$params = [
			'user' => $this->getUserParam($event),
		];

		if ($event->getObjectType() === ActivityManager::PHONETRACK_OBJECT_SESSION) {
			if (isset($subjectParams['session']) && $event->getObjectName() === '') {
				$event->setObject($event->getObjectType(), $event->getObjectId(), $subjectParams['session']['name']);
			}
			$session = [
				'type' => 'highlight',
				'id' => (string)$event->getObjectId(),
				'name' => $event->getObjectName(),
				'link' => $this->phonetrackUrl('/session/' . $event->getObjectId()),
			];
			$params['session'] = $session;
		}

		if (isset($subjectParams['device']) && $event->getObjectType() === ActivityManager::PHONETRACK_OBJECT_DEVICE) {
			if ($event->getObjectName() === '') {
				$event->setObject($event->getObjectType(), $event->getObjectId(), $subjectParams['device']['name']);
			}
			$device = [
				'type' => 'highlight',
				'id' => (string)$event->getObjectId(),
				'name' => $event->getObjectName(),
			];

			if (array_key_exists('session', $subjectParams)) {
				$device['link'] = $this->phonetrackUrl('/session/' . $subjectParams['session']['id']);
			}
			$params['device'] = $device;
		}

		$params = $this->parseParamForSession('session', $subjectParams, $params);
		$params = $this->parseParamForSession('session2', $subjectParams, $params);
		$params = $this->parseParamForSession('geofence', $subjectParams, $params);
		$params = $this->parseParamForDevice('device', $subjectParams, $params);
		$params = $this->parseParamForDevice('device2', $subjectParams, $params);
		$params = $this->parseParamForDevice('meters', $subjectParams, $params);
		$params = $this->parseParamForWho($subjectParams, $params);

		try {
			$subject = $this->activityManager->getActivityFormat($subjectIdentifier, $subjectParams, $ownActivity);
			$this->setSubjects($event, $subject, $params);
		} catch (\Exception $e) {
		}
		return $event;
	}

	/**
	 * @param IEvent $event
	 * @param string $subject
	 * @param array $parameters
	 */
	protected function setSubjects(IEvent $event, $subject, array $parameters) {
		$placeholders = $replacements = $richParameters = [];
		foreach ($parameters as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			if (is_array($parameter) && array_key_exists('name', $parameter)) {
				$replacements[] = $parameter['name'];
				$richParameters[$placeholder] = $parameter;
			} else {
				$replacements[] = '';
			}
		}

		$event->setParsedSubject(str_replace($placeholders, $replacements, $subject))
			->setRichSubject($subject, $richParameters);
		$event->setSubject($subject, $parameters);
	}

	private function getIcon(IEvent $event) {
		$event->setIcon($this->url->imagePath('phonetrack', 'app_black.svg'));
		if (strpos($event->getSubject(), 'geofence') !== false) {
			$event->setIcon($this->url->imagePath('phonetrack', 'geofence.svg'));
		}
		if (strpos($event->getSubject(), 'proximity') !== false) {
			$event->setIcon($this->url->imagePath('phonetrack', 'proximity.svg'));
		}
		return $event;
	}

	private function parseParamForSession($paramName, $subjectParams, $params) {
		if (array_key_exists($paramName, $subjectParams)) {
			$params[$paramName] = [
				'type' => 'highlight',
				'id' => (string)$subjectParams[$paramName]['id'],
				'name' => $subjectParams[$paramName]['name'],
				//'link' => $this->phonetrackUrl('?'),
			];
		}
		return $params;
	}
	private function parseParamForDevice($paramName, $subjectParams, $params) {
		if (array_key_exists($paramName, $subjectParams)) {
			$name = isset($subjectParams[$paramName]['alias'])
						? $subjectParams[$paramName]['alias'] . ' (' . $subjectParams[$paramName]['name'] . ')'
						: $subjectParams[$paramName]['name'];
			$params[$paramName] = [
				'type' => 'highlight',
				'id' => (string)$subjectParams[$paramName]['id'],
				'name' => (string)$name,
				//'link' => $this->phonetrackUrl('?'),
			];
		}
		return $params;
	}

	private function parseParamForWho($subjectParams, $params) {
		if (array_key_exists('who', $subjectParams)) {
			if ($subjectParams['type'] === 'u') {
				$user = $this->userManager->get($subjectParams['who']);
				$params['who'] = [
					'type' => 'user',
					'id' => (string)$subjectParams['who'],
					'name' => $user !== null ? $user->getDisplayName() : $subjectParams['who']
				];
			} else {
				$group = $this->groupManager->get($subjectParams['who']);
				$params['who'] = [
					'type' => 'highlight',
					'id' => (string)$subjectParams['who'],
					'name' => $group !== null ? $group->getDisplayName() : $subjectParams['who']
				];
			}
		}
		return $params;
	}

	public function phonetrackUrl($endpoint) {
		return $this->url->linkToRouteAbsolute('phonetrack.page.index') . $endpoint;
	}
}
