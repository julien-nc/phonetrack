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

use InvalidArgumentException;
use OCA\PhoneTrack\Service\SessionService;
use OCA\PhoneTrack\Db\SessionMapper;
use OCA\PhoneTrack\Db\Session;
use OCA\PhoneTrack\Db\DeviceMapper;
use OCA\PhoneTrack\Db\Device;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IL10N;
use OCP\IUser;

class ActivityManager {

	const PHONETRACK_OBJECT_SESSION = 'phonetrack_session';
	const PHONETRACK_OBJECT_DEVICE = 'phonetrack_device';

	const SUBJECT_GEOFENCE_ENTER = 'geofence_enter';
	const SUBJECT_GEOFENCE_EXIT = 'geofence_exit';

	const SUBJECT_PROXIMITY_CLOSE = 'proximity_close';
	const SUBJECT_PROXIMITY_FAR = 'proximity_far';

	const SUBJECT_SESSION_SHARE = 'session_share';
	const SUBJECT_SESSION_UNSHARE = 'session_unshare';

	public function __construct(
		private IManager $manager,
		private SessionService $sessionService,
		private SessionMapper $sessionMapper,
		private DeviceMapper $deviceMapper,
		private IL10N $l10n,
		private ?string $userId,
	) {
	}

	/**
	 * @param $subjectIdentifier
	 * @param array $subjectParams
	 * @param bool $ownActivity
	 * @return string
	 */
	public function getActivityFormat($subjectIdentifier, $subjectParams = [], $ownActivity = false) {
		$subject = '';
		switch ($subjectIdentifier) {
			case self::SUBJECT_GEOFENCE_ENTER:
				$subject = $this->l10n->t('PhoneTrack device {device} of session {session} has entered geofence {geofence}');
				break;
			case self::SUBJECT_GEOFENCE_EXIT:
				$subject = $this->l10n->t('PhoneTrack device {device} of session {session} has exited geofence {geofence}');
				break;
			case self::SUBJECT_PROXIMITY_CLOSE:
				$subject = $this->l10n->t('PhoneTrack device {device} of session {session} is now closer than {meters} m to {device2} of session {session2}');
				break;
			case self::SUBJECT_PROXIMITY_FAR:
				$subject = $this->l10n->t('PhoneTrack device {device} of session {session} is now farther than {meters} m from {device2} of session {session2}');
				break;
			case self::SUBJECT_SESSION_SHARE:
				$subject = $ownActivity ? $this->l10n->t('You shared PhoneTrack session {session} with {who}') : $this->l10n->t('PhoneTrack session {session} is now shared with {who}');
				break;
			case self::SUBJECT_SESSION_UNSHARE:
				$subject = $ownActivity ? $this->l10n->t('You stopped sharing session {session} with {who}') : $this->l10n->t('PhoneTrack session {session} is not shared with {who} anymore');
				break;
			default:
				break;
		}
		return $subject;
	}

	public function triggerEvent($objectType, $entity, $subject, $additionalParams = [], $author = null) {
		try {
			$event = $this->createEvent($objectType, $entity, $subject, $additionalParams, $author);
			if ($event !== null) {
				$this->sendToUsers($event);
			}
		} catch (\Exception $e) {
			// Ignore exception for undefined activities on update events
		}
	}

	/**
	 * @param $objectType
	 * @param $entity
	 * @param $subject
	 * @param array $additionalParams
	 * @return IEvent|null
	 * @throws \Exception
	 */
	private function createEvent($objectType, $entity, $subject, $additionalParams = [], $author = null) {
		try {
			$object = $this->findObjectForEntity($objectType, $entity);
		} catch (DoesNotExistException $e) {
			\OC::$server->getLogger()->error('Could not create activity entry for ' . $subject . '. Entity not found.', (array)$entity);
			return null;
		} catch (MultipleObjectsReturnedException $e) {
			\OC::$server->getLogger()->error('Could not create activity entry for ' . $subject . '. Entity not found.', (array)$entity);
			return null;
		}

		/**
		 * Automatically fetch related details for subject parameters
		 * depending on the subject
		 */
		$eventType = 'phonetrack';
		$subjectParams = [];
		$message = null;
		$objectName = null;
		switch ($subject) {
			// No need to enhance parameters since entity already contains the required data
			case self::SUBJECT_GEOFENCE_ENTER:
			case self::SUBJECT_GEOFENCE_EXIT:
				$subjectParams = $this->findDetailsForDevice($entity->getId());
				$objectName = $object->getName();
				$eventType = 'phonetrack_geofence_event';
				break;
			case self::SUBJECT_PROXIMITY_FAR:
			case self::SUBJECT_PROXIMITY_CLOSE:
				$subjectParams = $this->findDetailsForDevice($entity->getId());
				if (\array_key_exists('device2', $additionalParams)) {
					$dev2id = $additionalParams['device2']['id'];
					$dev2details = $this->findDetailsForDevice($dev2id);
					$additionalParams['device2'] = $dev2details['device'];
					$additionalParams['session2'] = $dev2details['session'];
				}
				$objectName = $object->getName();
				$eventType = 'phonetrack_proximity_event';
				break;
			case self::SUBJECT_SESSION_SHARE:
			case self::SUBJECT_SESSION_UNSHARE:
				$subjectParams = $this->findDetailsForSession($entity->getId());
				$objectName = $object->getName();
				break;
			default:
				throw new \Exception('Unknown subject for activity.');
				break;
		}
		$subjectParams['author'] = $this->l10n->t('A PhoneTrack client');

		$event = $this->manager->generateEvent();
		$event->setApp('phonetrack')
			->setType($eventType)
			->setAuthor($author === null ? $this->userId ?? '' : $author)
			->setObject($objectType, (int)$object->getId(), $objectName)
			->setSubject($subject, array_merge($subjectParams, $additionalParams))
			->setTimestamp(time());

		return $event;
	}

	/**
	 * Publish activity to all users that have access to the session of a given object
	 *
	 * @param IEvent $event
	 */
	private function sendToUsers(IEvent $event) {
		switch ($event->getObjectType()) {
			case self::PHONETRACK_OBJECT_DEVICE:
				$mapper = $this->deviceMapper;
				$token = $mapper->find($event->getObjectId())->getSessionid();
				$sessionId = $this->sessionMapper->findByToken($token)->getId();
				break;
			case self::PHONETRACK_OBJECT_SESSION:
				$mapper = $this->sessionMapper;
				$sessionId = $event->getObjectId();
				break;
		}
		/** @var IUser $user */
		foreach ($this->sessionService->findUsers($sessionId) as $user) {
			$event->setAffectedUser($user->getUID());
			/** @noinspection DisconnectedForeachInstructionInspection */
			$this->manager->publish($event);
		}
	}

	/**
	 * @param $objectType
	 * @param $entity
	 * @return null|Session
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	private function findObjectForEntity($objectType, $entity) {
		$className = \get_class($entity);
		$objectId = null;
		if ($objectType === self::PHONETRACK_OBJECT_DEVICE) {
			switch ($className) {
				case Device::class:
					$objectId = $entity->getId();
					break;
				default:
					throw new InvalidArgumentException('No entity relation present for '. $className . ' to ' . $objectType);
			}
			return $this->deviceMapper->find($objectId);
		}
		if ($objectType === self::PHONETRACK_OBJECT_SESSION) {
			switch ($className) {
				case Session::class:
					$objectId = $entity->getId();
					break;
				default:
					throw new InvalidArgumentException('No entity relation present for '. $className . ' to ' . $objectType);
			}
			return $this->sessionMapper->find($objectId);
		}
		throw new InvalidArgumentException('No entity relation present for '. $className . ' to ' . $objectType);
	}

	private function findDetailsForDevice($deviceId) {
		$device = $this->deviceMapper->find($deviceId);
		$session = $this->sessionMapper->findByToken($device->getSessionid());
		$device = [
			'id' => $device->getId(),
			'name' => $device->getName(),
			'alias' => $device->getAlias()
		];
		$session = [
			'id' => $session->getId(),
			'name' => $session->getName()
		];
		return [
			'device' => $device,
			'session' => $session
		];
	}

	private function findDetailsForSession($sessionId, $subject = null) {
		$session = $this->sessionMapper->find($sessionId);
		$session = [
			'id' => $session->getId(),
			'name' => $session->getName()
		];
		return [
			'session' => $session
		];
	}

}
