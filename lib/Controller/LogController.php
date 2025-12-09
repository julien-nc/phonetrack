<?php

namespace OCA\PhoneTrack\Controller;

use DateTime;
use DateTimeZone;
use Exception;

use OCA\PhoneTrack\Activity\ActivityManager;
use OCA\PhoneTrack\AppInfo\Application;
use OCA\PhoneTrack\Db\Device;
use OCA\PhoneTrack\Db\DeviceMapper;
use OCA\PhoneTrack\Db\Geofence;
use OCA\PhoneTrack\Db\GeofenceMapper;
use OCA\PhoneTrack\Db\Point;
use OCA\PhoneTrack\Db\PointMapper;
use OCA\PhoneTrack\Db\Proxim;
use OCA\PhoneTrack\Db\ProximMapper;
use OCA\PhoneTrack\Db\SessionMapper;
use OCA\PhoneTrack\Db\ShareMapper;
use OCA\PhoneTrack\Service\ToolsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;

use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IConfig;

use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;

use OCP\Mail\IMailer;
use OCP\Notification\IManager;
use Psr\Log\LoggerInterface;
use Throwable;

class LogController extends Controller {

	private $dbtype;
	private $dbdblquotes;
	private $defaultDeviceName;

	public const LOG_OWNTRACKS = 'Owntracks';

	public function __construct(
		string $AppName,
		IRequest $request,
		private IConfig $config,
		private IAppConfig $appConfig,
		private IManager $notificationManager,
		private IUserManager $userManager,
		private IL10N $l10n,
		private LoggerInterface $logger,
		private ActivityManager $activityManager,
		private SessionMapper $sessionMapper,
		private DeviceMapper $deviceMapper,
		private PointMapper $pointMapper,
		private ProximMapper $proximMapper,
		private GeofenceMapper $geofenceMapper,
		private ShareMapper $shareMapper,
		private IDBConnection $db,
		private IMailer $mailer,
		private ?string $userId,
	) {
		parent::__construct($AppName, $request);
		$this->dbtype = $config->getSystemValue('dbtype');

		if ($this->dbtype === 'pgsql') {
			$this->dbdblquotes = '"';
		} else {
			$this->dbdblquotes = '';
		}
		$this->defaultDeviceName = ['yourname', 'devicename', 'name'];
	}

	/*
	 * quote and choose string escape function depending on database used
	 */
	private function db_quote_escape_string($str) {
		return $this->db->quote($str ?? '');
	}

	/**
	 * if devicename is not set to default value, we take it
	 */
	private function chooseDeviceName(?string $devicename, ?string $tid = null): string {
		if ((!in_array($devicename, $this->defaultDeviceName))
			&& $devicename !== ''
			&& (!is_null($devicename))
		) {
			$dname = $devicename;
		} elseif ($tid !== '' && !is_null($tid)) {
			$dname = $tid;
		} else {
			$dname = 'unknown';
		}
		return $dname;
	}

	private function checkProxims(float $lat, float $lon, int $deviceId, string $userid, string $deviceName, string $sessionName, $sessionId): void {
		try {
			$lastPoint = $this->pointMapper->getLastDevicePoint($deviceId);
		} catch (DoesNotExistException) {
			return;
		}
		$proxims = $this->proximMapper->findByDeviceId($deviceId);
		foreach ($proxims as $proxim) {
			$this->checkProxim($lat, $lon, $deviceId, $proxim, $userid, $lastPoint, $deviceName, $sessionId);
		}
	}

	private function getSessionOwnerOfDevice(int $deviceId) {
		$owner = null;
		$sqlGet = '
			SELECT ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '
			FROM *PREFIX*phonetrack_devices
			INNER JOIN *PREFIX*phonetrack_sessions
				ON *PREFIX*phonetrack_devices.session_token=*PREFIX*phonetrack_sessions.token
			WHERE *PREFIX*phonetrack_devices.id=' . $this->db_quote_escape_string($deviceId) . ' ;';
		$req = $this->db->prepare($sqlGet);
		$res = $req->execute();
		while ($row = $res->fetch()) {
			$owner = $row['user'];
		}
		$res->closeCursor();
		return $owner;
	}

	private function checkProxim(
		float $newLat, float $newLon, int $movingDeviceId, Proxim $proxim, string $userid,
		Point $lastPoint, string $movingDeviceName, string $sessionToken,
	): void {
		$emailaddr = $proxim->getEmailaddr();
		if ($emailaddr === null) {
			$emailaddr = '';
		}
		$proximId = $proxim->getId();

		// get the deviceid of other device
		if ($movingDeviceId === $proxim->getDeviceid1()) {
			$otherDeviceId = $proxim->getDeviceid2();
		} else {
			$otherDeviceId = $proxim->getDeviceid1();
		}

		// get coords of other device
		try {
			$lastOtherPoint = $this->pointMapper->getLastDevicePoint($otherDeviceId);
		} catch (DoesNotExistException) {
			return;
		}
		$latOther = $lastOtherPoint->getLat();
		$lonOther = $lastOtherPoint->getLon();

		$otherDevice = $this->deviceMapper->find($otherDeviceId);

		// previous coords of observed device
		$prevLat = $lastPoint->getLat();
		$prevLon = $lastPoint->getLon();
		$prevDist = ToolsService::distance($prevLat, $prevLon, $latOther, $lonOther);
		$currDist = ToolsService::distance($newLat, $newLon, $latOther, $lonOther);

		// if distance was not close and is now close
		if ($proxim->getLowlimit() !== 0 && $prevDist >= $proxim->getLowlimit() && $currDist < $proxim->getLowlimit()) {
			// devices are now close !

			// if the observed device is 'deviceid2', then we might have the wrong userId
			if ($movingDeviceId === $proxim->getDeviceid2()) {
				$userid = $this->getSessionOwnerOfDevice($proxim->getDeviceid1());
			}
			$dev1name = $movingDeviceName;
			$dev2name = $otherDevice->getName();
			$dev2alias = $otherDevice->getAlias();
			if (!empty($dev2alias)) {
				$dev2name = $dev2alias . ' (' . $dev2name . ')';
			}

			// activity
			$deviceObj = $this->deviceMapper->find($movingDeviceId);
			$this->activityManager->triggerEvent(
				ActivityManager::PHONETRACK_OBJECT_DEVICE,
				$deviceObj,
				ActivityManager::SUBJECT_PROXIMITY_CLOSE,
				[
					'device2' => ['id' => $otherDeviceId],
					'meters' => [
						'id' => 0,
						'name' => $proxim->getLowlimit(),
					],
				]
			);

			// NOTIFICATIONS
			if ($proxim->getSendnotif() !== 0) {
				$userIds = $this->shareMapper->getSessionSharedUserIdList($sessionToken);
				$userIds[] = $userid;

				try {
					foreach ($userIds as $aUserId) {
						$notification = $this->notificationManager->createNotification();

						$acceptAction = $notification->createAction();
						$acceptAction->setLabel('accept')
							->setLink('/apps/phonetrack', 'GET');

						$declineAction = $notification->createAction();
						$declineAction->setLabel('decline')
							->setLink('/apps/phonetrack', 'GET');

						$notification->setApp('phonetrack')
							->setUser($aUserId)
							->setDateTime(new DateTime())
							->setObject('closeproxim', (string)$proximId)
							->setSubject('close_proxim', [$dev1name, $proxim->getLowlimit(), $dev2name])
							->addAction($acceptAction)
							->addAction($declineAction);

						$this->notificationManager->notify($notification);
					}
				} catch (Exception $e) {
					$this->logger->warning('Error sending PhoneTrack notification', ['exception' => $e]);
				}
			}

			if ($proxim->getSendemail() !== 0) {

				$user = $this->userManager->get($userid);
				$userEmail = $user->getEMailAddress();
				$mailFromA = $this->config->getSystemValue('mail_from_address', 'phonetrack');
				$mailFromD = $this->config->getSystemValue('mail_domain', 'nextcloud.your');

				// EMAIL
				$emailaddrArray = explode(',', $emailaddr);
				if (
					(count($emailaddrArray) === 1 && $emailaddrArray[0] === '')
					&& !empty($userEmail)
				) {
					$emailaddrArray[] = $userEmail;
				}

				if (!empty($mailFromA) && !empty($mailFromD)) {
					$mailfrom = $mailFromA . '@' . $mailFromD;

					foreach ($emailaddrArray as $addrTo) {
						if ($addrTo !== null && $addrTo !== '' && filter_var($addrTo, FILTER_VALIDATE_EMAIL)) {
							try {
								$message = $this->mailer->createMessage();
								$message->setSubject($this->l10n->t('PhoneTrack proximity alert (%s and %s)', [$dev1name, $dev2name]));
								$message->setFrom([$mailfrom => 'PhoneTrack']);
								$message->setTo([trim($addrTo) => '']);
								$message->setPlainBody(
									$this->l10n->t('PhoneTrack device %s is now closer than %s m to %s.', [
										$dev1name,
										$proxim->getLowlimit(),
										$dev2name
									])
								);
								$this->mailer->send($message);
							} catch (Exception $e) {
								$this->logger->warning('Error during PhoneTrack mail sending : ' . $e, ['app' => $this->appName]);
							}
						}
					}
				}
			}
			if ($proxim->getUrlclose() !== '' && str_starts_with($proxim->getUrlclose(), 'http')) {
				// GET
				if ($proxim->getUrlclosepost() === 0) {
					try {
						$xml = file_get_contents($proxim->getUrlclose());
					} catch (Exception $e) {
						$this->logger->warning('Error during PhoneTrack proxim URL query', ['exception' => $e]);
					}
				} else {
					// POST
					try {
						$parts = parse_url($proxim->getUrlclose());
						parse_str($parts['query'], $data);

						$url = $parts['scheme'] . '://' . $parts['host'] . $parts['path'];

						$options = [
							'http' => [
								'header' => "Content-type: application/x-www-form-urlencoded\r\n",
								'method' => 'POST',
								'content' => http_build_query($data),
							]
						];
						$context = stream_context_create($options);
						$result = file_get_contents($url, false, $context);
					} catch (Exception $e) {
						$this->logger->warning('Error during PhoneTrack proxim POST URL query', ['exception' => $e]);
					}
				}
			}
		} elseif ($proxim->getHighlimit() !== 0 && $prevDist <= $proxim->getHighlimit() && $currDist > $proxim->getHighlimit()) {
			// devices are now far !

			// if the observed device is 'deviceid2', then we might have the wrong userId
			if ($movingDeviceId === $proxim->getDeviceid2()) {
				$userid = $this->getSessionOwnerOfDevice($proxim->getDeviceid1());
			}
			$dev1name = $movingDeviceName;
			$dev2name = $otherDevice->getName();
			$dev2alias = $otherDevice->getAlias();
			if (!empty($dev2alias)) {
				$dev2name = $dev2alias . ' (' . $dev2name . ')';
			}

			// activity
			$deviceObj = $this->deviceMapper->find($movingDeviceId);
			$this->activityManager->triggerEvent(
				ActivityManager::PHONETRACK_OBJECT_DEVICE,
				$deviceObj,
				ActivityManager::SUBJECT_PROXIMITY_FAR,
				[
					'device2' => ['id' => $otherDeviceId],
					'meters' => [
						'id' => 0,
						'name' => $proxim->getHighlimit(),
					],
				]
			);

			// NOTIFICATIONS
			if ($proxim->getSendnotif() !== 0) {
				$userIds = $this->shareMapper->getSessionSharedUserIdList($sessionToken);
				$userIds[] = $userid;

				try {
					foreach ($userIds as $aUserId) {
						$notification = $this->notificationManager->createNotification();

						$acceptAction = $notification->createAction();
						$acceptAction->setLabel('accept')
							->setLink('/apps/phonetrack', 'GET');

						$declineAction = $notification->createAction();
						$declineAction->setLabel('decline')
							->setLink('/apps/phonetrack', 'GET');

						$notification->setApp('phonetrack')
							->setUser($aUserId)
							->setDateTime(new DateTime())
							->setObject('farproxim', (string)$proximId)
							->setSubject('far_proxim', [$dev1name, $proxim->getHighlimit(), $dev2name])
							->addAction($acceptAction)
							->addAction($declineAction);

						$this->notificationManager->notify($notification);
					}
				} catch (Exception $e) {
					$this->logger->warning('Error sending PhoneTrack notification', ['exception' => $e]);
				}
			}

			if ($proxim->getSendemail() !== 0) {

				$user = $this->userManager->get($userid);
				$userEmail = $user->getEMailAddress();
				$mailFromA = $this->config->getSystemValue('mail_from_address', 'phonetrack');
				$mailFromD = $this->config->getSystemValue('mail_domain', 'nextcloud.your');

				$emailaddrArray = explode(',', $emailaddr);
				if (
					(count($emailaddrArray) === 1 && $emailaddrArray[0] === '')
					&& !empty($userEmail)
				) {
					$emailaddrArray[] = $userEmail;
				}

				if (!empty($mailFromA) && !empty($mailFromD)) {
					$mailfrom = $mailFromA . '@' . $mailFromD;

					foreach ($emailaddrArray as $addrTo) {
						if ($addrTo !== null && $addrTo !== '' && filter_var($addrTo, FILTER_VALIDATE_EMAIL)) {
							try {
								$message = $this->mailer->createMessage();
								$message->setSubject($this->l10n->t('PhoneTrack proximity alert (%s and %s)', [$dev1name, $dev2name]));
								$message->setFrom([$mailfrom => 'PhoneTrack']);
								$message->setTo([trim($addrTo) => '']);
								$message->setPlainBody(
									$this->l10n->t('PhoneTrack device %s is now farther than %s m from %s.', [
										$dev1name,
										$proxim->getHighlimit(),
										$dev2name
									])
								);
								$this->mailer->send($message);
							} catch (Exception $e) {
								$this->logger->warning('Error during PhoneTrack mail sending', ['exception' => $e]);
							}
						}
					}
				}
			}
			if ($proxim->getUrlfar() !== '' && str_starts_with($proxim->getUrlfar(), 'http')) {
				// GET
				if ($proxim->getUrlfarpost() === 0) {
					try {
						$xml = file_get_contents($proxim->getUrlfar());
					} catch (Exception $e) {
						$this->logger->warning('Error during PhoneTrack proxim URL query', ['exception' => $e]);
					}
				} else {
					// POST
					try {
						$parts = parse_url($proxim->getUrlfar());
						parse_str($parts['query'], $data);

						$url = $parts['scheme'] . '://' . $parts['host'] . $parts['path'];

						$options = [
							'http' => [
								'header' => "Content-type: application/x-www-form-urlencoded\r\n",
								'method' => 'POST',
								'content' => http_build_query($data),
							]
						];
						$context = stream_context_create($options);
						$result = file_get_contents($url, false, $context);
					} catch (Exception $e) {
						$this->logger->warning('Error during PhoneTrack proxim POST URL query', ['exception' => $e]);
					}
				}
			}
		}
	}

	private function checkGeoFences(
		float $lat, float $lon, int $deviceId, string $userid, string $deviceName,
		string $sessionname, string $sessionToken,
	) {
		try {
			$lastPoint = $this->pointMapper->getLastDevicePoint($deviceId);
		} catch (DoesNotExistException) {
			return;
		}
		$fences = $this->geofenceMapper->findByDeviceId($deviceId);
		foreach ($fences as $fence) {
			$this->checkGeoGence($lat, $lon, $lastPoint, $deviceId, $fence, $userid, $deviceName, $sessionname, $sessionToken);
		}
	}

	private function checkGeoGence(
		float $lat, float $lon, Point $lastPoint, int $devid, Geofence $fence,
		string $userid, string $devicename, string $sessionname, string $sessionToken,
	) {
		$emailaddr = $fence->getEmailaddr();
		if ($emailaddr === null) {
			$emailaddr = '';
		}

		/*
		// first point of this device
		if ($lastPoint === null) {
			if (   $lat > $latmin
				&& $lat < $latmax
				&& $lon > $lonmin
				&& $lon < $lonmax
			) {
			}
		}
		*/

		$lastLat = $lastPoint->getLat();
		$lastLon = $lastPoint->getLon();

		// if previous point not in fence
		if (!($lastLat > $fence->getLatmin() && $lastLat < $fence->getLatmax() && $lastLon > $fence->getLonmin() && $lastLon < $fence->getLonmax())) {
			// and new point in fence
			if ($lat > $fence->getLatmin() && $lat < $fence->getLatmax() && $lon > $fence->getLonmin() && $lon < $fence->getLonmax()) {
				// device ENTERED the fence !
				$user = $this->userManager->get($userid);
				$userEmail = $user->getEMailAddress();
				$mailFromA = $this->config->getSystemValue('mail_from_address', 'phonetrack');
				$mailFromD = $this->config->getSystemValue('mail_domain', 'nextcloud.your');

				// activity
				$deviceObj = $this->deviceMapper->find($devid);
				$this->activityManager->triggerEvent(
					ActivityManager::PHONETRACK_OBJECT_DEVICE,
					$deviceObj,
					ActivityManager::SUBJECT_GEOFENCE_ENTER,
					[
						'geofence' => [
							'id' => $fence->getId(),
							'name' => $fence->getName(),
						],
					]
				);

				// NOTIFICATIONS
				if ($fence->getSendnotif() !== 0) {
					$userIds = $this->shareMapper->getSessionSharedUserIdList($sessionToken);
					$userIds[] = $userid;

					try {
						foreach ($userIds as $aUserId) {
							$notification = $this->notificationManager->createNotification();

							$acceptAction = $notification->createAction();
							$acceptAction->setLabel('accept')
								->setLink('/apps/phonetrack', 'GET');

							$declineAction = $notification->createAction();
							$declineAction->setLabel('decline')
								->setLink('/apps/phonetrack', 'GET');

							$notification->setApp('phonetrack')
								->setUser($aUserId)
								->setDateTime(new DateTime())
								->setObject('entergeofence', (string)$fence->getId()) // $type and $id
								->setSubject('enter_geofence', [$sessionname, $devicename, $fence->getName()])
								->addAction($acceptAction)
								->addAction($declineAction);

							$this->notificationManager->notify($notification);
						}
					} catch (Exception $e) {
						$this->logger->warning('Error sending PhoneTrack notification', ['exception' => $e]);
					}
				}

				// EMAIL
				if ($fence->getSendemail() !== 0) {
					$emailAddrArray = explode(',', $emailaddr);
					if (
						(count($emailAddrArray) === 1 && $emailAddrArray[0] === '')
						&& !empty($userEmail)
					) {
						$emailAddrArray[] = $userEmail;
					}
					if (!empty($mailFromA) && !empty($mailFromD)) {
						$mailFrom = $mailFromA . '@' . $mailFromD;

						foreach ($emailAddrArray as $addrTo) {
							if ($addrTo !== null && $addrTo !== '' && filter_var($addrTo, FILTER_VALIDATE_EMAIL)) {
								try {
									$message = $this->mailer->createMessage();
									$message->setSubject($this->l10n->t('Geofencing alert'));
									$message->setFrom([$mailFrom => 'PhoneTrack']);
									$message->setTo([trim($addrTo) => '']);
									$message->setPlainBody(
										$this->l10n->t('In PhoneTrack session "%s", device "%s" has entered geofence "%s".', [
											$sessionname,
											$devicename,
											$fence->getName(),
										])
									);
									$this->mailer->send($message);
								} catch (Exception $e) {
									$this->logger->warning('Error during PhoneTrack mail sending', ['exception' => $e]);
								}
							}
						}
					}
				}
				if ($fence->getUrlenter() !== '' && str_starts_with($fence->getUrlenter(), 'http')) {
					// GET
					$urlenter = str_replace(['%loc'], sprintf('%f:%f', $lat, $lon), $fence->getUrlenter());
					if ($fence->getUrlenterpost() === 0) {
						try {
							$xml = file_get_contents($urlenter);
						} catch (Exception $e) {
							$this->logger->warning('Error during PhoneTrack geofence URL query', ['exception' => $e]);
						}
					} else {
						// POST
						try {
							$parts = parse_url($fence->getUrlenter());
							parse_str($parts['query'], $data);

							$url = $parts['scheme'] . '://' . $parts['host'] . $parts['path'];

							$options = [
								'http' => [
									'header' => "Content-type: application/x-www-form-urlencoded\r\n",
									'method' => 'POST',
									'content' => http_build_query($data)
								]
							];
							$context = stream_context_create($options);
							$result = file_get_contents($url, false, $context);
						} catch (Exception $e) {
							$this->logger->warning('Error during PhoneTrack geofence POST URL query', ['exception' => $e]);
						}
					}
				}
			}
		} // previous point in fence
		else {
			// if new point NOT in fence
			if (!($lat > $fence->getLatmin() && $lat < $fence->getLatmax() && $lon > $fence->getLonmin() && $lon < $fence->getLonmax())) {
				// device EXITED the fence !
				$user = $this->userManager->get($userid);
				$userEmail = $user->getEMailAddress();
				$mailFromA = $this->config->getSystemValue('mail_from_address', 'phonetrack');
				$mailFromD = $this->config->getSystemValue('mail_domain', 'nextcloud.your');

				// activity
				$deviceObj = $this->deviceMapper->find($devid);
				$this->activityManager->triggerEvent(
					ActivityManager::PHONETRACK_OBJECT_DEVICE,
					$deviceObj,
					ActivityManager::SUBJECT_GEOFENCE_EXIT,
					[
						'geofence' => [
							'id' => $fence->getId(),
							'name' => $fence->getName(),
						],
					]
				);

				// NOTIFICATIONS
				if ($fence->getSendnotif() !== 0) {
					$userIds = $this->shareMapper->getSessionSharedUserIdList($sessionToken);
					$userIds[] = $userid;

					try {
						foreach ($userIds as $aUserId) {
							$notification = $this->notificationManager->createNotification();

							$acceptAction = $notification->createAction();
							$acceptAction->setLabel('accept')
								->setLink('/apps/phonetrack', 'GET');

							$declineAction = $notification->createAction();
							$declineAction->setLabel('decline')
								->setLink('/apps/phonetrack', 'GET');

							$notification->setApp('phonetrack')
								->setUser($aUserId)
								->setDateTime(new DateTime())
								->setObject('leavegeofence', (string)$fence->getId()) // $type and $id
								->setSubject('leave_geofence', [$sessionname, $devicename, $fence->getName()])
								->addAction($acceptAction)
								->addAction($declineAction);

							$this->notificationManager->notify($notification);
						}
					} catch (Exception $e) {
						$this->logger->warning('Error sending PhoneTrack notification', ['exception' => $e]);
					}
				}

				// EMAIL
				if ($fence->getSendemail() !== 0) {
					$emailAddrArray = explode(',', $emailaddr);
					if (
						(count($emailAddrArray) === 1 && $emailAddrArray[0] === '')
						&& !empty($userEmail)
					) {
						$emailAddrArray[] = $userEmail;
					}
					if (!empty($mailFromA) && !empty($mailFromD)) {
						$mailFrom = $mailFromA . '@' . $mailFromD;

						foreach ($emailAddrArray as $addrTo) {
							if ($addrTo !== null && $addrTo !== '' && filter_var($addrTo, FILTER_VALIDATE_EMAIL)) {
								try {
									$message = $this->mailer->createMessage();
									$message->setSubject($this->l10n->t('Geofencing alert'));
									$message->setFrom([$mailFrom => 'PhoneTrack']);
									$message->setTo([trim($addrTo) => '']);
									$message->setPlainBody(
										$this->l10n->t('In PhoneTrack session "%s", device "%s" exited geofence %s.', [
											$sessionname,
											$devicename,
											$fence->getName(),
										])
									);
									$this->mailer->send($message);
								} catch (Exception $e) {
									$this->logger->warning('Error during PhoneTrack mail sending : ' . $e, ['app' => $this->appName]);
								}
							}
						}
					}
				}
				if ($fence->getUrlleave() !== '' && str_starts_with($fence->getUrlleave(), 'http')) {
					// GET
					if ($fence->getUrlleavepost() === 0) {
						$urlleave = str_replace(['%loc'], sprintf('%f:%f', $lat, $lon), $fence->getUrlleave());
						try {
							$xml = file_get_contents($urlleave);
						} catch (Exception $e) {
							$this->logger->warning('Error during PhoneTrack geofence URL query', ['exception' => $e]);
						}
					} else {
						// POST
						try {
							$parts = parse_url($fence->getUrlleave());
							parse_str($parts['query'], $data);

							$url = $parts['scheme'] . '://' . $parts['host'] . $parts['path'];

							$options = [
								'http' => [
									'header' => "Content-type: application/x-www-form-urlencoded\r\n",
									'method' => 'POST',
									'content' => http_build_query($data)
								]
							];
							$context = stream_context_create($options);
							$result = file_get_contents($url, false, $context);
						} catch (Exception $e) {
							$this->logger->warning('Error during PhoneTrack geofence POST URL query', ['exception' => $e]);
						}
					}
				}
			}
		}
	}

	private function checkQuota(int $deviceidToInsert, string $userid, string $devicename, string $sessionname,
		int $nbPointsToInsert = 1) {
		$quota = $this->appConfig->getValueInt(Application::APP_ID, 'pointQuota', 0, lazy: true);
		if ($quota === 0) {
			return true;
		}

		// does the user have more points than allowed ?
		$nbPoints = $this->pointMapper->countPointsPerUser($userid);

		// if there is enough 'space'
		if ($nbPoints + $nbPointsToInsert <= $quota) {
			// if we just reached the quota : notify the user
			if ($nbPoints + $nbPointsToInsert === $quota) {
				$notification = $this->notificationManager->createNotification();

				$acceptAction = $notification->createAction();
				$acceptAction->setLabel('accept')
					->setLink('/apps/phonetrack', 'GET');

				$declineAction = $notification->createAction();
				$declineAction->setLabel('decline')
					->setLink('/apps/phonetrack', 'GET');

				$notification->setApp('phonetrack')
					->setUser($userid)
					->setDateTime(new DateTime())
					->setObject('quotareached', (string)$nbPoints)
					->setSubject('quota_reached', [$quota, $devicename, $sessionname])
					->addAction($acceptAction)
					->addAction($declineAction)
				;

				$this->notificationManager->notify($notification);
			}

			return true;
		}

		// so we need space
		$nbExceedingPoints = $nbPoints + $nbPointsToInsert - $quota;

		$userChoice = $this->config->getUserValue($userid, 'phonetrack', 'quotareached', 'block');

		if ($userChoice !== 'block') {
			if ($userChoice === 'rotatedev') {
				// delete the most points we can from device
				// if it's not enough, do global rotate
				$count = $this->deviceMapper->countPointsPerDevice($deviceidToInsert);

				// delete what we can
				$nbToDelete = min($count, $nbExceedingPoints);
				if ($nbToDelete > 0) {
					$this->pointMapper->deleteFirstPointsOfDevice($deviceidToInsert, $nbToDelete, $this->dbtype);
				}
				// update the space we need after this deletion
				$nbExceedingPoints = $nbExceedingPoints - $nbToDelete;
			}

			// if rotateglob
			// or if rotatedev was not enough to free the space we need
			if ($userChoice === 'rotateglob' || $nbExceedingPoints > 0) {
				if ($this->dbtype === 'mysql') {
					$sqldel = '
						SELECT p.id AS id
						FROM *PREFIX*phonetrack_points AS p
						INNER JOIN *PREFIX*phonetrack_devices AS d ON p.deviceid=d.id
						INNER JOIN *PREFIX*phonetrack_sessions AS s ON d.session_token=s.token
						WHERE s.' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($userid) . '
						ORDER BY timestamp ASC LIMIT ' . $nbExceedingPoints . ' ;';
					$req = $this->db->prepare($sqldel);
					$res = $req->execute();
					$pids = [];
					while ($row = $res->fetch()) {
						$pids[] = $row['id'];
					}
					$res->closeCursor();

					foreach ($pids as $pid) {
						$sqldel = '
							DELETE FROM *PREFIX*phonetrack_points
							WHERE id=' . $this->db_quote_escape_string($pid) . ' ;';
						$req = $this->db->prepare($sqldel);
						$req->execute();
						$req->closeCursor();
					}
				} else {
					$sqldel = '
						DELETE FROM *PREFIX*phonetrack_points
						WHERE *PREFIX*phonetrack_points.id IN
							(SELECT p.id
							FROM *PREFIX*phonetrack_points AS p
							INNER JOIN *PREFIX*phonetrack_devices AS d ON p.deviceid=d.id
							INNER JOIN *PREFIX*phonetrack_sessions AS s ON d.session_token=s.token
							WHERE s.' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($userid) . '
							ORDER BY timestamp ASC LIMIT ' . $nbExceedingPoints . ')
						 ;';
					$req = $this->db->prepare($sqldel);
					$req->execute();
					$req->closeCursor();
				}
			}
		}

		return ($userChoice !== 'block');
	}

	#[NoAdminRequired]
	public function addPoint2(
		int $sessionId, int $deviceId, float $lat, float $lon, int $timestamp,
		?float $accuracy = null, ?float $altitude = null, ?float $batterylevel = null, ?int $satellites = null,
		string $useragent = '', ?float $speed = null, ?float $bearing = null,
	) {
		try {
			$session = $this->sessionMapper->getUserSessionById($this->userId, $sessionId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return new DataResponse(['error' => 'session_not_found'], Http::STATUS_NOT_FOUND);
		}
		if ($session->getLocked() !== 0) {
			return new DataResponse(['error' => 'session_locked'], Http::STATUS_FORBIDDEN);
		}

		try {
			$device = $this->deviceMapper->getBySessionIdAndDeviceId($session->getId(), $deviceId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return new DataResponse(['error' => 'device_not_found'], Http::STATUS_NOT_FOUND);
		}

		$this->checkGeoFences($lat, $lon, $device->getId(), $session->getUser(), $device->getName(), $session->getName(), $session->getToken());
		$this->checkProxims($lat, $lon, $device->getId(), $session->getUser(), $device->getName(), $session->getName(), $session->getToken());
		$quotaClearance = $this->checkQuota($device->getId(), $session->getUser(), $device->getName(), $session->getName());

		if (!$quotaClearance) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$point = $this->pointMapper->addPoint(
			$device->getId(), $lat, $lon, $timestamp, $accuracy, $altitude, $batterylevel, $satellites, $useragent, $speed, $bearing,
		);
		return new DataResponse($point);
	}

	#[NoAdminRequired]
	public function addPoint(
		string $token, string $devicename, float $lat, float $lon, ?float $alt, ?int $timestamp,
		?float $acc, ?float $bat, ?int $sat, ?string $useragent, ?float $speed, ?float $bearing,
	) {
		if ($token === '' || $devicename === '') {
			return new DataResponse([
				'done' => 2,
				'pointid' => null,
				'deviceid' => null,
			]);
		}
		if ($bat !== null) {
			$bat = (int)$bat;
		}
		$logPostResult = $this->logPost($token, $devicename, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, $useragent, $speed, $bearing);
		$dbPointId = $logPostResult['pointId'];
		$dbDeviceId = $logPostResult['deviceId'];
		if ($logPostResult['done'] === 1) {
			try {
				$device = $this->deviceMapper->getByName($token, $devicename);
				$dbDeviceId = $device->getId();
			} catch (DoesNotExistException $e) {
				$device = null;
			}

			// if it's reserved and a device token was given
			if ($device === null) {
				try {
					$device = $this->deviceMapper->getByNameToken($token, $devicename);
					$dbDeviceId = $device->getId();
				} catch (DoesNotExistException $e) {
					$device = null;
				}
			}

			if ($dbDeviceId !== null && $dbPointId !== null) {
				$done = 1;
			} else {
				$done = 4;
			}
		} else {
			// logpost didn't work
			$done = 3;
			// because of quota
			if ($logPostResult['done'] === 2) {
				$done = 5;
			}
		}

		return new DataResponse([
			'done' => $done,
			'pointid' => $dbPointId,
			'deviceid' => $dbDeviceId,
		]);
	}

	/**
	 * @param string $token
	 * @param string $devicename
	 * @param float $lat
	 * @param float $lon
	 * @param float|null $alt
	 * @param int|null $timestamp
	 * @param float|null $acc
	 * @param int|null $bat
	 * @param int|null $sat
	 * @param string|null $useragent
	 * @param float|null $speed
	 * @param float|null $bearing
	 * @param string|null $datetime
	 * @return array
	 * @throws MultipleObjectsReturnedException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws \OCP\DB\Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function logPost(
		string $token, string $devicename, float $lat, float $lon, ?float $alt = null,
		?int $timestamp = null, ?float $acc = null, ?int $bat = null, ?int $sat = null,
		?string $useragent = '', ?float $speed = null, ?float $bearing = null,
		?string $datetime = null,
	): array {
		$result = [
			'done' => 0,
			'friends' => [],
			'pointId' => null,
			'deviceId' => null,
		];
		// TODO insert speed and bearing in m/s and degrees
		if ($devicename === '' || $token === '' || (is_null($timestamp) && is_null($datetime))) {
			return $result;
		}
		try {
			$session = $this->sessionMapper->findByToken($token);
		} catch (DoesNotExistException $e) {
			try {
				$share = $this->shareMapper->findByShareToken($token);
				$session = $this->sessionMapper->find($share->getSessionId());
			} catch (DoesNotExistException $e) {
				return $result;
			}
		}

		if ($session->getLocked() === 1) {
			$result['done'] = 3;
			return $result;
		}

		$humanReadableDeviceName = $devicename;
		// check if this devicename is reserved or exists
		try {
			$device = $this->deviceMapper->getByName($session->getToken(), $devicename);
		} catch (DoesNotExistException $e) {
			$device = null;
		}

		// the device exists
		if ($device !== null) {
			if ($device->getAlias()) {
				$humanReadableDeviceName = $device->getAlias() . ' (' . $device->getName() . ')';
			} else {
				$humanReadableDeviceName = $device->getName();
			}
			// this device id reserved => logging refused if the request does not come from correct user
			if ($device->getNametoken()) {
				// here, we check if we're logged in as the session owner
				if ($this->userId && $session->getUser() === $this->userId) {
					// if so, accept to (manually) log with name and not nametoken
				} else {
					return $result;
				}
			}
		} else {
			// the device with this device name does not exist
			// check if the device name corresponds to a name token
			try {
				$device = $this->deviceMapper->getByNameToken($session->getToken(), $devicename);
			} catch (DoesNotExistException $e) {
				$device = null;
			}

			// there is a device which has this nametoken => we log for this device
			if ($device !== null) {
				if ($device->getAlias()) {
					$humanReadableDeviceName = $device->getAlias() . ' (' . $device->getName() . ')';
				} else {
					$humanReadableDeviceName = $device->getName();
				}
			} else {
				// device does not exist and there is no reservation corresponding
				// => we create it
				$device = new Device();
				$device->setName($devicename);
				$device->setSessionToken($session->getToken());
				$device->setSessionId($session->getId());
				$device = $this->deviceMapper->insert($device);
			}
		}

		if ($timestamp !== null) {
			// correct timestamp if needed
			if ($timestamp > 10000000000) {
				$timestamp = $timestamp / 1000;
			}
		} else {
			// we have a datetime
			try {
				$d = new DateTime($datetime);
				$timestamp = $d->getTimestamp();
			} catch (Exception|Throwable $e) {
				try {
					$dateTimeZone = null;
					if (($session->getUser() ?? '') !== '') {
						$timezone = $this->config->getUserValue($session->getUser(), 'core', 'timezone');
						if ($timezone !== '') {
							$dateTimeZone = new DateTimeZone($timezone);
						}
					}
					$d = DateTime::createFromFormat('F d, Y \a\t h:iA', $datetime, $dateTimeZone);
					$timestamp = $d->getTimestamp();
				} catch (Exception|Throwable $e) {
					return $result;
				}
			}
		}

		if ($acc !== null) {
			$acc = (float)sprintf('%.2f', $acc);
		}

		// geofences, proximity alerts, quota
		$this->checkGeoFences($lat, $lon, $device->getId(), $session->getUser(), $humanReadableDeviceName, $session->getName(), $session->getToken());
		$this->checkProxims($lat, $lon, $device->getId(), $session->getUser(), $humanReadableDeviceName, $session->getName(), $session->getToken());
		$quotaClearance = $this->checkQuota($device->getId(), $session->getUser(), $humanReadableDeviceName, $session->getName());

		if (!$quotaClearance) {
			$result['done'] = 2;
			return $result;
		}

		$dbPoint = new Point();
		$dbPoint->setDeviceid($device->getId());
		$dbPoint->setLat($lat);
		$dbPoint->setLon($lon);
		$dbPoint->setTimestamp($timestamp);
		$dbPoint->setAltitude($alt);
		$dbPoint->setAccuracy($acc);
		$dbPoint->setBatterylevel($bat);
		$dbPoint->setSatellites($sat);
		$dbPoint->setSpeed($speed);
		$dbPoint->setBearing($bearing);
		$dbPoint->setUseragent($useragent ?? '');
		$dbPoint = $this->pointMapper->insert($dbPoint);
		$result['pointId'] = $dbPoint->getId();
		$result['deviceId'] = $device->getId();

		$result['done'] = 1;

		if ($session->getPublic() === 1 && $useragent === self::LOG_OWNTRACKS) {
			$friendSQL = '
							SELECT p.`deviceid`, `nametoken`, `name`, `lat`, `lon`,
								`speed`, `altitude`, `batterylevel`, `accuracy`,
								`timestamp`
							FROM `*PREFIX*phonetrack_points` p
							JOIN (
								SELECT `deviceid`,
									MAX(`timestamp`) `lastupdate`
								FROM `*PREFIX*phonetrack_points` po
								GROUP BY `deviceid`
							) l ON p.`deviceid` = l.`deviceid`
							AND p.`timestamp` = l.`lastupdate`
							JOIN `*PREFIX*phonetrack_devices` d ON p.`deviceid` = d.`id`
							WHERE `session_token` = ?
						';
			$friendRequest = $this->db->prepare($friendSQL);
			$res = $friendRequest->execute([$token]);
			$friends = [];
			while ($row = $res->fetch()) {
				// we don't store the tid, so we fall back to the last
				// two chars of the nametoken
				// TODO feels far from unique, currently 32 ids max
				$tid = substr($row['nametoken'] ?? 'ab', -2);
				$location = [
					'_type' => 'location',
					// Tracker ID used to display the initials of a user (iOS,Android/string/optional) required for http mode
					'tid' => $tid,
					// latitude (iOS,Android/float/meters/required)
					'lat' => (float)$row['lat'],
					// longitude (iOS,Android/float/meters/required)
					'lon' => (float)$row['lon'],
					// UNIX epoch timestamp in seconds of the location fix (iOS,Android/integer/epoch/required)
					'tst' => (int)$row['timestamp'],
				];

				if (isset($row['speed'])) {
					// velocity (iOS/integer/kmh/optional)
					$location['vel'] = (int)$row['speed'];
				}
				if (isset($row['altitude'])) {
					// Altitude measured above sea level (iOS/integer/meters/optional)
					$location['alt'] = (int)$row['altitude'];
				}
				if (isset($row['batterylevel'])) {
					// Device battery level (iOS,Android/integer/percent/optional)
					$location['batt'] = (int)$row['batterylevel'];
				}
				if (isset($row['accuracy'])) {
					// Accuracy of the reported location in meters without unit (iOS/integer/meters/optional)
					$location['acc'] = (int)$row['accuracy'];
				}
				$friends[] = $location;
				$friends[] = [
					'_type' => 'card',
					'tid' => $tid,
					//'face'=>'/9j/4AAQSkZJR...', // TODO lookup avatar?
					'name' => $row['name'],
				];
			}
			$result['friends'] = $friends;
		}
		return $result;
	}

	/**
	 * @param string $token
	 * @param string $devicename
	 * @param array $points
	 * @return array
	 * @throws MultipleObjectsReturnedException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws \OCP\DB\Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function logPostMultiple(string $token, string $devicename, array $points): array {
		$result = [
			'done' => 0,
			'friends' => [],
		];
		if ($devicename === '' || $token === '') {
			return $result;
		}
		// check if session exists
		try {
			$session = $this->sessionMapper->findByToken($token);
		} catch (DoesNotExistException $e) {
			// is it a share token?
			try {
				$share = $this->shareMapper->findByShareToken($token);
				$session = $this->sessionMapper->find($share->getSessionId());
			} catch (DoesNotExistException $e) {
				return $result;
			}
		}

		if ($session->getLocked() === 1) {
			$result['done'] = 3;
			return $result;
		}

		$humanReadableDeviceName = $devicename;
		// check if this device name is reserved or exists
		try {
			$device = $this->deviceMapper->getByName($session->getToken(), $devicename);
		} catch (DoesNotExistException $e) {
			$device = null;
		}

		// the device exists
		if ($device !== null) {
			if ($device->getAlias()) {
				$humanReadableDeviceName = $device->getAlias() . ' (' . $device->getName() . ')';
			} else {
				$humanReadableDeviceName = $device->getName();
			}
			// this device id reserved => logging refused if the request does not come from correct user
			if ($device->getNametoken()) {
				// here, we check if we're logged in as the session owner
				if ($this->userId && $session->getUser() === $this->userId) {
					// if so, accept to (manually) log with name and not nametoken
				} else {
					return $result;
				}
			}
		} else {
			// the device with this device name does not exist
			// check if the device name corresponds to a nametoken
			try {
				$device = $this->deviceMapper->getByNameToken($session->getToken(), $devicename);
			} catch (DoesNotExistException $e) {
				$device = null;
			}

			// there is a device which has this nametoken => we log for this device
			if ($device !== null) {
				if ($device->getAlias()) {
					$humanReadableDeviceName = $device->getAlias() . ' (' . $device->getName() . ')';
				} else {
					$humanReadableDeviceName = $device->getName();
				}
			} else {
				// device does not exist and there is no reservation corresponding
				// => we create it
				$device = new Device();
				$device->setName($devicename);
				$device->setSessionToken($session->getToken());
				$device->setSessionId($session->getId());
				$device = $this->deviceMapper->insert($device);
			}
		}

		// check quota once before inserting anything
		// it will delete points to make room if needed
		$quotaClearance = $this->checkQuota($device->getId(), $session->getUser(), $humanReadableDeviceName, $session->getName(), count($points));

		if (!$quotaClearance) {
			$result['done'] = 2;
			return $result;
		}

		// check geofences and proxims only once with the last point
		if (count($points) > 0) {
			$lastPointToInsert = $points[count($points) - 1];
			$lat = $lastPointToInsert[0];
			$lon = $lastPointToInsert[1];
			$this->checkGeoFences(
				(float)$lat, (float)$lon, $device->getId(), $session->getUser(), $humanReadableDeviceName, $session->getName(), $session->getToken()
			);
			$this->checkProxims(
				(float)$lat, (float)$lon, $device->getId(), $session->getUser(), $humanReadableDeviceName, $session->getName(), $session->getToken()
			);
		}

		$useragent = null;
		$pointsToInsert = [];
		foreach ($points as $point) {
			$lat = $point[0];
			$lon = $point[1];
			$timestamp = $point[2];
			$alt = $point[3];
			$acc = $point[4];
			$bat = $point[5];
			$sat = $point[6];
			$useragent = $point[7];
			$speed = $point[8];
			$bearing = $point[9];

			if ($lat !== '' && is_numeric($lat)
				&& $lon !== '' && is_numeric($lon)
				&& $timestamp !== '' && is_numeric($timestamp)
			) {
				// correct timestamp if needed
				$timestamp = (int)$timestamp;
				if ($timestamp > 10000000000) {
					$timestamp = $timestamp / 1000;
				}
				$alt = is_numeric($alt) ? (float)$alt : null;
				$acc = is_numeric($acc) ? (float)$acc : null;
				$bat = is_numeric($bat) ? (float)$bat : null;
				$sat = is_numeric($sat) ? (int)$sat : null;
				$speed = is_numeric($speed) ? (float)$speed : null;
				$bearing = is_numeric($bearing) ? (float)$bearing : null;

				$dbPoint = new Point();
				$dbPoint->setDeviceid($device->getId());
				$dbPoint->setLat((float)$lat);
				$dbPoint->setLon((float)$lon);
				$dbPoint->setTimestamp($timestamp);
				$dbPoint->setAltitude($alt);
				$dbPoint->setAccuracy($acc);
				$dbPoint->setBatterylevel($bat);
				$dbPoint->setSatellites($sat);
				$dbPoint->setSpeed($speed);
				$dbPoint->setBearing($bearing);
				$dbPoint->setUseragent($useragent);

				$pointsToInsert[] = $dbPoint;

				// insert by bunch of 50 points
				if (count($pointsToInsert) % 50 === 0) {
					$this->db->beginTransaction();
					try {
						foreach ($pointsToInsert as $pointToInsert) {
							$this->pointMapper->insert($pointToInsert);
						}
						$this->db->commit();
					} catch (\Exception|\Throwable $e) {
						$this->db->rollBack();
					}
					$pointsToInsert = [];
				}
			}
		}
		// insert last bunch of points
		if (count($pointsToInsert) > 0) {
			$this->db->beginTransaction();
			try {
				foreach ($pointsToInsert as $pointToInsert) {
					$this->pointMapper->insert($pointToInsert);
				}
				$this->db->commit();
			} catch (\Exception|\Throwable $e) {
				$this->db->rollBack();
			}
		}

		$result['done'] = 1;

		if ($session->getPublic() === 1 && $useragent === self::LOG_OWNTRACKS) {
			$friendSQL = '
							SELECT p.`deviceid`, `nametoken`, `name`, `lat`, `lon`,
								`speed`, `altitude`, `batterylevel`, `accuracy`,
								`timestamp`
							FROM `*PREFIX*phonetrack_points` p
							JOIN (
								SELECT `deviceid`, `nametoken`, `name`,
									MAX(`timestamp`) `lastupdate`
								FROM `*PREFIX*phonetrack_points` po
								JOIN `*PREFIX*phonetrack_devices` d ON po.`deviceid` = d.`id`
								WHERE `session_token` = ?
								GROUP BY `deviceid`, `nametoken`, `name`
							) l ON p.`deviceid` = l.`deviceid`
							AND p.`timestamp` = l.`lastupdate`
						';
			$friendReq = $this->db->prepare($friendSQL);
			$res = $friendReq->execute([$session->getToken()]);
			$friends = [];
			while ($row = $res->fetch()) {
				// we don't store the tid, so we fall back to the last
				// two chars of the nametoken
				// TODO feels far from unique, currently 32 ids max
				$tid = substr($row['nametoken'], -2);
				$location = [
					'_type' => 'location',
					// Tracker ID used to display the initials of a user (iOS,Android/string/optional) required for http mode
					'tid' => $tid,
					// latitude (iOS,Android/float/meters/required)
					'lat' => (float)$row['lat'],
					// longitude (iOS,Android/float/meters/required)
					'lon' => (float)$row['lon'],
					// UNIX epoch timestamp in seconds of the location fix (iOS,Android/integer/epoch/required)
					'tst' => (int)$row['timestamp'],
				];

				if (isset($row['speed'])) {
					// velocity (iOS/integer/kmh/optional)
					$location['vel'] = (int)$row['speed'];
				}
				if (isset($row['altitude'])) {
					// Altitude measured above sea level (iOS/integer/meters/optional)
					$location['alt'] = (int)$row['altitude'];
				}
				if (isset($row['batterylevel'])) {
					// Device battery level (iOS,Android/integer/percent/optional)
					$location['batt'] = (int)$row['batterylevel'];
				}
				if (isset($row['accuracy'])) {
					// Accuracy of the reported location in meters without unit (iOS/integer/meters/optional)
					$location['acc'] = (int)$row['accuracy'];
				}
				$friends[] = $location;
				$friends[] = [
					'_type' => 'card',
					'tid' => $tid,
					//'face'=>'/9j/4AAQSkZJR...', // TODO lookup avatar?
					'name' => $row['name'],
				];
			}
			$result['friends'] = $friends;
		}
		return $result;
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function logGet(
		string $token, string $devicename, float $lat, float $lon, ?int $timestamp, ?float $bat = null,
		?int $sat = null, ?float $acc = null, ?float $alt = null,
		?float $speed = null, ?float $bearing = null, ?string $datetime = null,
		string $useragent = 'unknown GET logger',
	) {
		$dName = $this->chooseDeviceName($devicename);
		if ($bat !== null) {
			$bat = (int)$bat;
		}
		return $this->logPost($token, $dName, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, $useragent, $speed, $bearing, $datetime);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function logLocusmapGet(
		string $token, string $devicename, float $lat, float $lon, ?int $time = null,
		?float $battery = null, ?float $acc = null, ?float $alt = null,
		?float $speed = null, ?float $bearing = null,
	) {
		$dName = $this->chooseDeviceName($devicename);
		if ($battery !== null) {
			$battery = (int)$battery;
		}
		$this->logPost($token, $dName, $lat, $lon, $alt, $time, $acc, $battery, null, 'LocusMap', $speed, $bearing);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function logLocusmapPost(string $token, string $devicename, float $lat, float $lon, ?int $time = null,
		?float $battery = null, ?float $acc = null, ?float $alt = null,
		?float $speed = null, ?float $bearing = null) {
		$dName = $this->chooseDeviceName($devicename);
		if ($battery !== null) {
			$battery = (int)$battery;
		}
		$this->logPost($token, $dName, $lat, $lon, $alt, $time, $acc, $battery, null, 'LocusMap', $speed, $bearing);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function logOsmand(
		string $token, string $devicename, float $lat, float $lon, ?int $timestamp = null,
		?float $bat = null, ?int $sat = null, ?float $acc = null, ?float $alt = null,
		?float $speed = null, ?float $bearing = null,
	) {
		$dName = $this->chooseDeviceName($devicename);
		if ($bat !== null) {
			$bat = (int)$bat;
		}
		$this->logPost($token, $dName, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, 'OsmAnd', $speed, $bearing);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function logGpsloggerGet(
		string $token, string $devicename, float $lat, float $lon, ?int $timestamp = null,
		?float $bat = null, ?int $sat = null, ?float $acc = null, ?float $alt = null,
		?float $speed = null, ?float $bearing = null,
	) {
		$dName = $this->chooseDeviceName($devicename);
		if ($bat !== null) {
			$bat = (int)$bat;
		}
		$this->logPost($token, $dName, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, 'GpsLogger GET', $speed, $bearing);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function logGpsloggerPost(string $token, string $devicename, float $lat, float $lon, ?float $alt = null,
		?int $timestamp = null, ?float $acc = null, ?float $bat = null, $sat = null,
		?float $speed = null, ?float $bearing = null) {
		$dname = $this->chooseDeviceName($devicename);
		if ($bat !== null) {
			$bat = (int)$bat;
		}
		$this->logPost($token, $dname, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, 'GpsLogger POST', $speed, $bearing);
	}

	/**
	 * Owntracks IOS
	 *
	 * @param string $token
	 * @param float|null $lat
	 * @param float|null $lon
	 * @param string|null $devicename
	 * @param string|null $tid
	 * @param float|null $alt
	 * @param int|null $tst
	 * @param float|null $acc
	 * @param float|null $batt
	 * @return mixed
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function logOwntracks(
		string $token, ?float $lat, ?float $lon, ?string $devicename = null, ?string $tid = null,
		?float $alt = null, ?int $tst = null, ?float $acc = null, ?float $batt = null,
	) {
		if (is_null($lat) || is_null($lon)) {
			// empty message (control message?) - ignore
			return ['result' => 'ok'];
		}
		$dname = $this->chooseDeviceName($devicename, $tid);
		if ($batt !== null) {
			$batt = (int)$batt;
		}
		$res = $this->logPost($token, $dname, $lat, $lon, $alt, $tst, $acc, $batt, null, self::LOG_OWNTRACKS);
		return $res['friends'];
	}

	/**
	 * Overland Ios
	 **/
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function logOverland(string $token, array $locations, ?string $devicename = null) {
		foreach ($locations as $loc) {
			if ($loc['type'] === 'Feature' && $loc['geometry']['type'] === 'Point') {
				$dname = $this->chooseDeviceName($loc['properties']['device_id'] ?? '', $devicename);
				$lat = $loc['geometry']['coordinates'][1];
				$lon = $loc['geometry']['coordinates'][0];
				$datetime = new Datetime($loc['properties']['timestamp']);
				$timestamp = $datetime->getTimestamp();
				$acc = $loc['properties']['horizontal_accuracy'];
				$bat = ((float)$loc['properties']['battery_level']) * 100.0;
				$speed = $loc['properties']['speed'];
				$bearing = null;
				$sat = null;
				$alt = $loc['properties']['altitude'];
				$this->logPost($token, $dname, $lat, $lon, $alt, $timestamp, $acc, (int)$bat, $sat, 'Overland', $speed, $bearing);
			}
		}
		return ['result' => 'ok'];
	}

	/**
	 * Ulogger Android
	 **/
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function logUlogger(string $token, ?float $lat = null, ?float $lon = null, ?int $time = null, ?string $devicename = null,
		?float $accuracy = null, ?float $altitude = null,
		?string $action = null, ?float $speed = null, ?float $bearing = null) {
		if ($action === 'addpos' && $lat !== null && $lon !== null) {
			$dname = $this->chooseDeviceName($devicename);
			$this->logPost($token, $dname, $lat, $lon, $altitude, $time, $accuracy, null, null, 'Ulogger', $speed, $bearing);
		}
		return [
			'error' => false,
			'trackid' => 1,
		];
	}

	/**
	 * traccar Android/IOS
	 **/
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function logTraccar(
		string $token, ?float $lat = null, ?float $lon = null, ?int $timestamp = null,
		?string $deviceName = null, ?string $id = null, ?float $accuracy = null,
		?float $altitude = null, ?float $batt = null, ?float $speed = null, ?float $bearing = null,
	) {
		$input = json_decode(file_get_contents('php://input'), true);

		if (is_array($input) && isset($input['location']['coords']['latitude'], $input['location']['coords']['longitude'])) {
			$lat = $input['location']['coords']['latitude'];
			$lon = $input['location']['coords']['longitude'];

			$altitude = $input['location']['coords']['altitude'] ?? $altitude;
			$speed = $input['location']['coords']['speed'] ?? $speed;
			$bearing = $input['location']['coords']['heading'] ?? $bearing;
			$accuracy = $input['location']['coords']['accuracy'] ?? $accuracy;
			$timestamp = isset($input['location']['timestamp']) ? strtotime($input['location']['timestamp']) : $timestamp;
			$batt = isset($input['location']['battery']['level']) ? $input['location']['battery']['level'] * 100 : $batt;
		}

		if ($lat === null || $lon === null) {
			return new JSONResponse(['error' => 'Latitude or longitude missing'], Http::STATUS_BAD_REQUEST);
		}
		$dName = $this->chooseDeviceName($deviceName, $id);
		// according to traccar sources, speed is converted in knots...
		// convert back to meter/s
		$speedMS = $speed === null ? null : $speed / 1.943844;
		if ($batt !== null) {
			$batt = (int)$batt;
		}
		$this->logPost($token, $dName, $lat, $lon, $altitude, $timestamp, $accuracy, $batt, null, 'Traccar', $speedMS, $bearing);

		return new JSONResponse(['status' => 'success']);
	}

	/**
	 * Any OpenGTS-compliant app
	 **/
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function logOpengts(
		string $token, string $gprmc,
		?string $devicename = null, ?string $id = null, ?float $alt = null, ?float $batt = null,
	) {
		$dname = $this->chooseDeviceName($devicename, $id);
		$gprmca = explode(',', $gprmc);
		$time = sprintf('%06d', (int)$gprmca[1]);
		$date = sprintf('%06d', (int)$gprmca[9]);
		$datetime = DateTime::createFromFormat('dmy His', $date . ' ' . $time);
		$timestamp = $datetime->getTimestamp();
		$lat = ToolsService::DMStoDEC(sprintf('%010.4f', (float)$gprmca[3]), 'latitude');
		if ($gprmca[4] === 'S') {
			$lat = - $lat;
		}
		$lon = ToolsService::DMStoDEC(sprintf('%010.4f', (float)$gprmca[5]), 'longitude');
		if ($gprmca[6] === 'W') {
			$lon = - $lon;
		}
		if ($batt !== null) {
			$batt = (int)$batt;
		}
		$this->logPost($token, $dname, $lat, $lon, $alt, $timestamp, null, $batt, null, 'OpenGTS client');
		return true;
	}

	/**
	 * In case there is a POST request (like celltrackGTS does)
	 **/
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function logOpengtsPost($token, $devicename, $id, $dev, $acct, $alt, $batt, $gprmc) {
		return [];
	}
}
