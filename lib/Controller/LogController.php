<?php
/**
 * Nextcloud - phonetrack
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2017
 */

namespace OCA\PhoneTrack\Controller;

use DateTime;
use DateTimeZone;
use Exception;

use OCA\PhoneTrack\Activity\ActivityManager;
use OCA\PhoneTrack\Db\DeviceMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataResponse;

use OCP\IConfig;

use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;

use OCP\Notification\IManager;
use Psr\Log\LoggerInterface;
use Throwable;

function DMStoDEC(string $dms, string $longlat): float {
	if ($longlat === 'latitude') {
		$deg = (int)substr($dms, 0, 3);
		$min = (float)substr($dms, 3, 8);
		$sec = 0;
	}
	if ($longlat === 'longitude') {
		$deg = (int)substr($dms, 0, 3);
		$min = (float)substr($dms, 3, 8);
		$sec = 0;
	}
	return $deg + ((($min * 60) + ($sec)) / 3600);
}

function startsWith(string $haystack, string $needle): bool {
	$length = strlen($needle);
	return (substr($haystack, 0, $length) === $needle);
}

function distance2(float $lat1, float $long1, float $lat2, float $long2): float {

	if ($lat1 === $lat2 && $long1 === $long2) {
		return 0;
	}

	// Convert latitude and longitude to
	// spherical coordinates in radians.
	$degrees_to_radians = pi() / 180.0;

	// phi = 90 - latitude
	$phi1 = (90.0 - $lat1) * $degrees_to_radians;
	$phi2 = (90.0 - $lat2) * $degrees_to_radians;

	// theta = longitude
	$theta1 = $long1 * $degrees_to_radians;
	$theta2 = $long2 * $degrees_to_radians;

	$cos = sin($phi1) * sin($phi2) * cos($theta1 - $theta2)
		+ cos($phi1) * cos($phi2);
	// why some cosinus are > than 1 ?
	if ($cos > 1.0) {
		$cos = 1.0;
	}
	$arc = acos($cos);

	// Remember to multiply arc by the radius of the earth
	// in your favorite set of units to get length.
	return $arc * 6371000;
}

class LogController extends Controller {

	private $userId;
	private $config;
	private $dbconnection;
	private $dbtype;
	private $dbdblquotes;
	private $defaultDeviceName;
	private $trans;
	private $userManager;
	private $ncLogger;

	public const LOG_OWNTRACKS = 'Owntracks';
	/**
	 * @var ActivityManager
	 */
	private $activityManager;
	/**
	 * @var DeviceMapper
	 */
	private $deviceMapper;
	/**
	 * @var IManager
	 */
	private $notificationManager;

	public function __construct(string $AppName,
		IRequest $request,
		IConfig $config,
		IManager $notificationManager,
		IUserManager $userManager,
		IL10N $trans,
		LoggerInterface $ncLogger,
		ActivityManager $activityManager,
		DeviceMapper $deviceMapper,
		IDBConnection $dbconnection,
		?string $userId) {
		parent::__construct($AppName, $request);
		$this->userId = $userId;
		$this->activityManager = $activityManager;
		$this->deviceMapper = $deviceMapper;
		$this->trans = $trans;
		$this->ncLogger = $ncLogger;
		$this->userManager = $userManager;
		$this->dbtype = $config->getSystemValue('dbtype');
		$this->config = $config;

		if ($this->dbtype === 'pgsql') {
			$this->dbdblquotes = '"';
		} else {
			$this->dbdblquotes = '';
		}
		$this->dbconnection = $dbconnection;
		$this->defaultDeviceName = ['yourname', 'devicename', 'name'];
		$this->notificationManager = $notificationManager;
	}

	/*
	 * quote and choose string escape function depending on database used
	 */
	private function db_quote_escape_string($str) {
		return $this->dbconnection->quote($str);
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

	private function getLastDevicePoint(int $deviceId) {
		$theRow = null;
		$sqlGet = '
			SELECT lat, lon, timestamp,
				   batterylevel, satellites,
				   accuracy, altitude,
				   speed, bearing
			FROM *PREFIX*phonetrack_points
			WHERE deviceid='.$this->db_quote_escape_string($deviceId).'
			ORDER BY timestamp DESC LIMIT 1 ;';
		$req = $this->dbconnection->prepare($sqlGet);
		$res = $req->execute();
		while ($row = $res->fetch()) {
			$theRow = $row;
		}
		$res->closeCursor();
		return $theRow;
	}

	private function getDeviceProxims(int $deviceId) {
		$proxims = [];
		$sqlGet = '
			SELECT id, deviceid1, deviceid2, highlimit,
				   lowlimit, urlclose, urlfar,
				   urlclosepost, urlfarpost,
				   sendemail, emailaddr, sendnotif
			FROM *PREFIX*phonetrack_proxims
			WHERE deviceid1='.$this->db_quote_escape_string($deviceId).'
				  OR deviceid2='.$this->db_quote_escape_string($deviceId).' ;';
		$req = $this->dbconnection->prepare($sqlGet);
		$res = $req->execute();
		while ($row = $res->fetch()) {
			$proxims[] = $row;
		}
		$res->closeCursor();
		return $proxims;
	}

	private function checkProxims(float $lat, float $lon, int $deviceId, string $userid, string $deviceName, string $sessionName, $sessionId) {
		$lastPoint = $this->getLastDevicePoint($deviceId);
		$proxims = $this->getDeviceProxims($deviceId);
		foreach ($proxims as $proxim) {
			$this->checkProxim($lat, $lon, $deviceId, $proxim, $userid, $lastPoint, $deviceName, $sessionId);
		}
	}

	private function getSessionOwnerOfDevice(int $deviceId) {
		$owner = null;
		$sqlGet = '
			SELECT '.$this->dbdblquotes.'user'.$this->dbdblquotes.'
			FROM *PREFIX*phonetrack_devices
			INNER JOIN *PREFIX*phonetrack_sessions
				ON *PREFIX*phonetrack_devices.sessionid=*PREFIX*phonetrack_sessions.token
			WHERE *PREFIX*phonetrack_devices.id='.$this->db_quote_escape_string($deviceId).' ;';
		$req = $this->dbconnection->prepare($sqlGet);
		$res = $req->execute();
		while ($row = $res->fetch()) {
			$owner = $row['user'];
		}
		$res->closeCursor();
		return $owner;
	}

	private function getDeviceAlias(int $deviceId) {
		$dbAlias = null;
		$sqlGet = '
			SELECT alias
			FROM *PREFIX*phonetrack_devices
			WHERE id='.$this->db_quote_escape_string($deviceId).' ;';
		$req = $this->dbconnection->prepare($sqlGet);
		$res = $req->execute();
		while ($row = $res->fetch()) {
			$dbAlias = $row['alias'];
		}
		$res->closeCursor();

		return $dbAlias;
	}

	private function getDeviceName(int $deviceId) {
		$dbname = null;
		$sqlGet = '
			SELECT name
			FROM *PREFIX*phonetrack_devices
			WHERE id='.$this->db_quote_escape_string($deviceId).' ;';
		$req = $this->dbconnection->prepare($sqlGet);
		$res = $req->execute();
		while ($row = $res->fetch()) {
			$dbname = $row['name'];
		}
		$res->closeCursor();

		return $dbname;
	}

	private function checkProxim(float  $newLat, float $newLon, int $movingDeviceId, array $proxim, string $userid,
		?array $lastPoint, string $movingDeviceName, string $sessionToken) {
		$highlimit = (int)$proxim['highlimit'];
		$lowlimit = (int)$proxim['lowlimit'];
		$urlclose = $proxim['urlclose'];
		$urlfar = $proxim['urlfar'];
		$urlclosepost = (int)$proxim['urlclosepost'];
		$urlfarpost = (int)$proxim['urlfarpost'];
		$sendemail = (int)$proxim['sendemail'];
		$sendnotif = (int)$proxim['sendnotif'];
		$emailaddr = $proxim['emailaddr'];
		if ($emailaddr === null) {
			$emailaddr = '';
		}
		$proximid = $proxim['id'];

		// get the deviceid of other device
		if (($movingDeviceId) === ((int)$proxim['deviceid1'])) {
			$otherDeviceId = (int)$proxim['deviceid2'];
		} else {
			$otherDeviceId = (int)$proxim['deviceid1'];
		}

		// get coords of other device
		$lastOtherPoint = $this->getLastDevicePoint($otherDeviceId);
		$latOther = (float)$lastOtherPoint['lat'];
		$lonOther = (float)$lastOtherPoint['lon'];

		if ($lastPoint !== null) {
			// previous coords of observed device
			$prevLat = (float)$lastPoint['lat'];
			$prevLon = (float)$lastPoint['lon'];

			$prevDist = distance2($prevLat, $prevLon, $latOther, $lonOther);
			$currDist = distance2($newLat, $newLon, $latOther, $lonOther);

			// if distance was not close and is now close
			if ($lowlimit !== 0 && $prevDist >= $lowlimit && $currDist < $lowlimit) {
				// devices are now close !

				// if the observed device is 'deviceid2', then we might have the wrong userId
				if (($movingDeviceId) === ((int)$proxim['deviceid2'])) {
					$userid = $this->getSessionOwnerOfDevice($proxim['deviceid1']);
				}
				$dev1name = $movingDeviceName;
				$dev2name = $this->getDeviceName($otherDeviceId);
				$dev2alias = $this->getDeviceAlias($otherDeviceId);
				if (!empty($dev2alias)) {
					$dev2name = $dev2alias.' ('.$dev2name.')';
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
							'name' => $lowlimit,
						],
					]
				);

				// NOTIFICATIONS
				if ($sendnotif !== 0) {
					$userIds = $this->getSessionSharedUserIdList($sessionToken);
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
								->setObject('closeproxim', $proximid)
								->setSubject('close_proxim', [$dev1name, $lowlimit, $dev2name])
								->addAction($acceptAction)
								->addAction($declineAction)
							;

							$this->notificationManager->notify($notification);
						}
					} catch (Exception $e) {
						$this->ncLogger->warning('Error sending PhoneTrack notification : '.$e, ['app' => $this->appName]);
					}
				}

				if ($sendemail !== 0) {

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
						$mailfrom = $mailFromA.'@'.$mailFromD;

						foreach ($emailaddrArray as $addrTo) {
							if ($addrTo !== null && $addrTo !== '' && filter_var($addrTo, FILTER_VALIDATE_EMAIL)) {
								try {
									$mailer = \OC::$server->getMailer();
									$message = $mailer->createMessage();
									$message->setSubject($this->trans->t('PhoneTrack proximity alert (%s and %s)', [$dev1name, $dev2name]));
									$message->setFrom([$mailfrom => 'PhoneTrack']);
									$message->setTo([trim($addrTo) => '']);
									$message->setPlainBody(
										$this->trans->t('PhoneTrack device %s is now closer than %s m to %s.', [
											$dev1name,
											$lowlimit,
											$dev2name
										])
									);
									$mailer->send($message);
								} catch (Exception $e) {
									$this->ncLogger->warning('Error during PhoneTrack mail sending : '.$e, ['app' => $this->appName]);
								}
							}
						}
					}
				}
				if ($urlclose !== '' && startsWith($urlclose, 'http')) {
					// GET
					if ($urlclosepost === 0) {
						try {
							$xml = file_get_contents($urlclose);
						} catch (Exception $e) {
							$this->ncLogger->warning('Error during PhoneTrack proxim URL query : '.$e, ['app' => $this->appName]);
						}
					} else {
						// POST
						try {
							$parts = parse_url($urlclose);
							parse_str($parts['query'], $data);

							$url = $parts['scheme'].'://'.$parts['host'].$parts['path'];

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
							$this->ncLogger->warning('Error during PhoneTrack proxim POST URL query : '.$e, ['app' => $this->appName]);
						}
					}
				}
			} elseif ($highlimit !== 0 && $prevDist <= $highlimit && $currDist > $highlimit) {
				// devices are now far !

				// if the observed device is 'deviceid2', then we might have the wrong userId
				if (($movingDeviceId) === ((int)$proxim['deviceid2'])) {
					$userid = $this->getSessionOwnerOfDevice($proxim['deviceid1']);
				}
				$dev1name = $movingDeviceName;
				$dev2name = $this->getDeviceName($otherDeviceId);
				$dev2alias = $this->getDeviceAlias($otherDeviceId);
				if (!empty($dev2alias)) {
					$dev2name = $dev2alias.' ('.$dev2name.')';
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
							'name' => $highlimit,
						],
					]
				);

				// NOTIFICATIONS
				if ($sendnotif !== 0) {
					$userIds = $this->getSessionSharedUserIdList($sessionToken);
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
								->setObject('farproxim', $proximid)
								->setSubject('far_proxim', [$dev1name, $highlimit, $dev2name])
								->addAction($acceptAction)
								->addAction($declineAction)
							;

							$this->notificationManager->notify($notification);
						}
					} catch (Exception $e) {
						$this->ncLogger->warning('Error sending PhoneTrack notification : '.$e, ['app' => $this->appName]);
					}
				}

				if ($sendemail !== 0) {

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
						$mailfrom = $mailFromA.'@'.$mailFromD;

						foreach ($emailaddrArray as $addrTo) {
							if ($addrTo !== null && $addrTo !== '' && filter_var($addrTo, FILTER_VALIDATE_EMAIL)) {
								try {
									$mailer = \OC::$server->getMailer();
									$message = $mailer->createMessage();
									$message->setSubject($this->trans->t('PhoneTrack proximity alert (%s and %s)', [$dev1name, $dev2name]));
									$message->setFrom([$mailfrom => 'PhoneTrack']);
									$message->setTo([trim($addrTo) => '']);
									$message->setPlainBody(
										$this->trans->t('PhoneTrack device %s is now farther than %s m from %s.', [
											$dev1name,
											$highlimit,
											$dev2name
										])
									);
									$mailer->send($message);
								} catch (Exception $e) {
									$this->ncLogger->warning('Error during PhoneTrack mail sending : '.$e, ['app' => $this->appName]);
								}
							}
						}
					}
				}
				if ($urlfar !== '' && startsWith($urlfar, 'http')) {
					// GET
					if ($urlfarpost === 0) {
						try {
							$xml = file_get_contents($urlfar);
						} catch (Exception $e) {
							$this->ncLogger->warning('Error during PhoneTrack proxim URL query : ' . $e, ['app' => $this->appName]);
						}
					} else {
						// POST
						try {
							$parts = parse_url($urlfar);
							parse_str($parts['query'], $data);

							$url = $parts['scheme'].'://'.$parts['host'].$parts['path'];

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
							$this->ncLogger->warning('Error during PhoneTrack proxim POST URL query : '.$e, ['app' => $this->appName]);
						}
					}
				}
			}
		}
	}

	private function getDeviceFences(int $devid) {
		$fences = [];
		$sqlGet = '
			SELECT id, latmin, lonmin, latmax, lonmax,
				   name, urlenter, urlleave,
				   urlenterpost, urlleavepost,
				   sendemail, emailaddr, sendnotif
			FROM *PREFIX*phonetrack_geofences
			WHERE deviceid='.$this->db_quote_escape_string($devid).' ;';
		$req = $this->dbconnection->prepare($sqlGet);
		$res = $req->execute();
		while ($row = $res->fetch()) {
			$fences[] = $row;
		}
		$res->closeCursor();
		return $fences;
	}

	/**
	 * returns user ids the session is shared with
	 */
	private function getSessionSharedUserIdList(string $token) {
		$userIds = [];
		$sqlGet = '
			SELECT username
			FROM *PREFIX*phonetrack_shares
			WHERE sessionid='.$this->db_quote_escape_string($token).' ;';
		$req = $this->dbconnection->prepare($sqlGet);
		$res = $req->execute();
		while ($row = $res->fetch()) {
			$userIds[] = $row['username'];
		}
		$res->closeCursor();
		return $userIds;
	}

	private function checkGeoFences(float  $lat, float $lon, int $deviceId, string $userid, string $deviceName,
		string $sessionname, string $sessionToken) {
		$lastPoint = $this->getLastDevicePoint($deviceId);
		$fences = $this->getDeviceFences($deviceId);
		foreach ($fences as $fence) {
			$this->checkGeoGence($lat, $lon, $lastPoint, $deviceId, $fence, $userid, $deviceName, $sessionname, $sessionToken);
		}
	}

	private function checkGeoGence(float $lat, float $lon, ?array $lastPoint, int $devid, array $fence,
		string $userid, string $devicename, string $sessionname, string $sessionToken) {
		$latmin = (float)$fence['latmin'];
		$latmax = (float)$fence['latmax'];
		$lonmin = (float)$fence['lonmin'];
		$lonmax = (float)$fence['lonmax'];
		$urlenter = $fence['urlenter'];
		$urlleave = $fence['urlleave'];
		$urlenterpost = (int)$fence['urlenterpost'];
		$urlleavepost = (int)$fence['urlleavepost'];
		$sendemail = (int)$fence['sendemail'];
		$sendnotif = (int)$fence['sendnotif'];
		$emailaddr = $fence['emailaddr'];
		if ($emailaddr === null) {
			$emailaddr = '';
		}
		$fencename = $fence['name'];
		$fenceid = $fence['id'];

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
		// not the first point
		if ($lastPoint !== null) {
			$lastLat = (float)$lastPoint['lat'];
			$lastLon = (float)$lastPoint['lon'];

			// if previous point not in fence
			if (!($lastLat > $latmin && $lastLat < $latmax && $lastLon > $lonmin && $lastLon < $lonmax)) {
				// and new point in fence
				if ($lat > $latmin && $lat < $latmax && $lon > $lonmin && $lon < $lonmax) {
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
								'id' => $fenceid,
								'name' => $fencename,
							],
						]
					);

					// NOTIFICATIONS
					if ($sendnotif !== 0) {
						$userIds = $this->getSessionSharedUserIdList($sessionToken);
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
									->setObject('entergeofence', $fenceid) // $type and $id
									->setSubject('enter_geofence', [$sessionname, $devicename, $fencename])
									->addAction($acceptAction)
									->addAction($declineAction)
								;

								$this->notificationManager->notify($notification);
							}
						} catch (Exception $e) {
							$this->ncLogger->warning('Error sending PhoneTrack notification : '.$e, ['app' => $this->appName]);
						}
					}

					// EMAIL
					if ($sendemail !== 0) {
						$emailAddrArray = explode(',', $emailaddr);
						if (
							(count($emailAddrArray) === 1 && $emailAddrArray[0] === '')
							&& !empty($userEmail)
						) {
							$emailAddrArray[] = $userEmail;
						}
						if (!empty($mailFromA) && !empty($mailFromD)) {
							$mailFrom = $mailFromA.'@'.$mailFromD;

							foreach ($emailAddrArray as $addrTo) {
								if ($addrTo !== null && $addrTo !== '' && filter_var($addrTo, FILTER_VALIDATE_EMAIL)) {
									try {
										$mailer = \OC::$server->getMailer();
										$message = $mailer->createMessage();
										$message->setSubject($this->trans->t('Geofencing alert'));
										$message->setFrom([$mailFrom => 'PhoneTrack']);
										$message->setTo([trim($addrTo) => '']);
										$message->setPlainBody(
											$this->trans->t('In PhoneTrack session %s, device %s has entered geofence %s.', [
												$sessionname,
												$devicename,
												$fencename
											])
										);
										$mailer->send($message);
									} catch (Exception $e) {
										$this->ncLogger->warning('Error during PhoneTrack mail sending : '.$e, ['app' => $this->appName]);
									}
								}
							}
						}
					}
					if ($urlenter !== '' && startsWith($urlenter, 'http')) {
						// GET
						$urlenter = str_replace(['%loc'], $lat.':'.$lon, $urlenter);
						if ($urlenterpost === 0) {
							try {
								$xml = file_get_contents($urlenter);
							} catch (Exception $e) {
								$this->ncLogger->warning('Error during PhoneTrack geofence URL query : '.$e, ['app' => $this->appName]);
							}
						}
						// POST
						else {
							try {
								$parts = parse_url($urlenter);
								parse_str($parts['query'], $data);

								$url = $parts['scheme'].'://'.$parts['host'].$parts['path'];

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
								$this->ncLogger->warning('Error during PhoneTrack geofence POST URL query : '.$e, ['app' => $this->appName]);
							}
						}
					}
				}
			}
			// previous point in fence
			else {
				// if new point NOT in fence
				if (!($lat > $latmin && $lat < $latmax && $lon > $lonmin && $lon < $lonmax)) {
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
								'id' => $fenceid,
								'name' => $fencename,
							],
						]
					);

					// NOTIFICATIONS
					if ($sendnotif !== 0) {
						$userIds = $this->getSessionSharedUserIdList($sessionToken);
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
									->setObject('leavegeofence', $fenceid) // $type and $id
									->setSubject('leave_geofence', [$sessionname, $devicename, $fencename])
									->addAction($acceptAction)
									->addAction($declineAction)
								;

								$this->notificationManager->notify($notification);
							}
						} catch (Exception $e) {
							$this->ncLogger->warning('Error sending PhoneTrack notification : '.$e, ['app' => $this->appName]);
						}
					}

					// EMAIL
					if ($sendemail !== 0) {
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
										$mailer = \OC::$server->getMailer();
										$message = $mailer->createMessage();
										$message->setSubject($this->trans->t('Geofencing alert'));
										$message->setFrom([$mailFrom => 'PhoneTrack']);
										$message->setTo([trim($addrTo) => '']);
										$message->setPlainBody(
											$this->trans->t('In PhoneTrack session %s, device %s exited geofence %s.', [
												$sessionname,
												$devicename,
												$fencename
											])
										);
										$mailer->send($message);
									} catch (Exception $e) {
										$this->ncLogger->warning('Error during PhoneTrack mail sending : '.$e, ['app' => $this->appName]);
									}
								}
							}
						}
					}
					if ($urlleave !== '' && startsWith($urlleave, 'http')) {
						// GET
						if ($urlleavepost === 0) {
							$urlleave = str_replace(['%loc'], $lat.':'.$lon, $urlleave);
							try {
								$xml = file_get_contents($urlleave);
							} catch (Exception $e) {
								$this->ncLogger->warning('Error during PhoneTrack geofence URL query : '.$e, ['app' => $this->appName]);
							}
						} else {
							// POST
							try {
								$parts = parse_url($urlleave);
								parse_str($parts['query'], $data);

								$url = $parts['scheme'].'://'.$parts['host'].$parts['path'];

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
								$this->ncLogger->warning('Error during PhoneTrack geofence POST URL query : '.$e, ['app' => $this->appName]);
							}
						}
					}
				}
			}
		}
	}

	private function checkQuota(int $deviceidToInsert, string $userid, string $devicename, string $sessionname,
		int $nbPointsToInsert = 1) {
		$quota = (int)$this->config->getAppValue('phonetrack', 'pointQuota', '0');
		if ($quota === 0) {
			return true;
		}

		$nbPoints = 0;
		// does the user have more points than allowed ?
		$sqlget = '
			SELECT count(*) as co
			FROM *PREFIX*phonetrack_points AS p
			INNER JOIN *PREFIX*phonetrack_devices AS d ON p.deviceid=d.id
			INNER JOIN *PREFIX*phonetrack_sessions AS s ON d.sessionid=s.token
			WHERE s.'.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($userid).' ;';
		$req = $this->dbconnection->prepare($sqlget);
		$res = $req->execute();
		while ($row = $res->fetch()) {
			$nbPoints = (int)$row['co'];
		}

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
					->setObject('quotareached', (string) $nbPoints)
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
				$count = 0;
				$sqlget = '
					SELECT count(id) as co
					FROM *PREFIX*phonetrack_points
					WHERE deviceid='.$this->db_quote_escape_string($deviceidToInsert).'
					;';
				$req = $this->dbconnection->prepare($sqlget);
				$res = $req->execute();
				while ($row = $res->fetch()) {
					$count = $row['co'];
				}

				// delete what we can
				$nbToDelete = min($count, $nbExceedingPoints);
				if ($nbToDelete > 0) {
					if ($this->dbtype === 'pgsql') {
						$sqldel = '
							DELETE FROM *PREFIX*phonetrack_points
							WHERE id IN (
								SELECT id
								FROM *PREFIX*phonetrack_points
								WHERE deviceid='.$this->db_quote_escape_string($deviceidToInsert).'
								ORDER BY timestamp ASC LIMIT '.$nbToDelete.'
							);';
					} else {
						$sqldel = '
							 DELETE FROM *PREFIX*phonetrack_points
							 WHERE deviceid='.$this->db_quote_escape_string($deviceidToInsert).'
							 ORDER BY timestamp ASC LIMIT '.$nbToDelete.' ;';
					}
					$req = $this->dbconnection->prepare($sqldel);
					$req->execute();
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
						INNER JOIN *PREFIX*phonetrack_sessions AS s ON d.sessionid=s.token
						WHERE s.'.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($userid).'
						ORDER BY timestamp ASC LIMIT '.$nbExceedingPoints.' ;';
					$req = $this->dbconnection->prepare($sqldel);
					$res = $req->execute();
					$pids = [];
					while ($row = $res->fetch()) {
						$pids[] = $row['id'];
					}
					$res->closeCursor();

					foreach ($pids as $pid) {
						$sqldel = '
							DELETE FROM *PREFIX*phonetrack_points
							WHERE id='.$this->db_quote_escape_string($pid).' ;';
						$req = $this->dbconnection->prepare($sqldel);
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
							INNER JOIN *PREFIX*phonetrack_sessions AS s ON d.sessionid=s.token
							WHERE s.'.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($userid).'
							ORDER BY timestamp ASC LIMIT '.$nbExceedingPoints.')
						 ;';
					$req = $this->dbconnection->prepare($sqldel);
					$req->execute();
					$req->closeCursor();
				}
			}
		}

		return ($userChoice !== 'block');
	}

	/**
	 */
	#[NoAdminRequired]
	public function addPoint(
		string $token, string $devicename, float $lat, float $lon, ?float $alt, ?int $timestamp,
		?float $acc, ?float $bat, ?int $sat, ?string $useragent, ?float $speed, ?float $bearing
	) {
		$done = 0;
		$dbid = null;
		$dbdevid = null;
		if ($token !== '' && $devicename !== '') {
			if ($bat !== null) {
				$bat = (int) $bat;
			}
			$logres = $this->logPost($token, $devicename, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, $useragent, $speed, $bearing);
			if ($logres['done'] === 1) {
				$sqlchk = '
					SELECT id
					FROM *PREFIX*phonetrack_devices
					WHERE sessionid='.$this->db_quote_escape_string($token).'
						  AND name='.$this->db_quote_escape_string($devicename).' ;';
				$req = $this->dbconnection->prepare($sqlchk);
				$res = $req->execute();
				while ($row = $res->fetch()) {
					$dbdevid = $row['id'];
					break;
				}
				$res->closeCursor();

				// if it's reserved and a device token was given
				if ($dbdevid === null) {
					$sqlchk = '
						SELECT id
						FROM *PREFIX*phonetrack_devices
						WHERE sessionid='.$this->db_quote_escape_string($token).'
							  AND nametoken='.$this->db_quote_escape_string($devicename).' ;';
					$req = $this->dbconnection->prepare($sqlchk);
					$res = $req->execute();
					while ($row = $res->fetch()) {
						$dbdevid = $row['id'];
						break;
					}
					$res->closeCursor();
				}

				if ($dbdevid !== null) {
					$sqlchk = '
						SELECT MAX(id) as maxid
						FROM *PREFIX*phonetrack_points
						WHERE deviceid='.$this->db_quote_escape_string($dbdevid).'
							  AND lat='.$this->db_quote_escape_string($lat).'
							  AND lon='.$this->db_quote_escape_string($lon).'
							  AND timestamp='.$this->db_quote_escape_string($timestamp).' ;';
					$req = $this->dbconnection->prepare($sqlchk);
					$res = $req->execute();
					while ($row = $res->fetch()) {
						$dbid = $row['maxid'];
						break;
					}
					$res->closeCursor();
					$done = 1;
				} else {
					$done = 4;
				}
			} else {
				// logpost didn't work
				$done = 3;
				// because of quota
				if ($logres['done'] === 2) {
					$done = 5;
				}
			}
		} else {
			$done = 2;
		}

		$response = new DataResponse(
			[
				'done' => $done,
				'pointid' => $dbid,
				'deviceid' => $dbdevid
			]
		);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*')
			->addAllowedMediaDomain('*')
			->addAllowedConnectDomain('*');
		$response->setContentSecurityPolicy($csp);
		return $response;
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
		?string $datetime = null
	): array {
		$result = ['done' => 0, 'friends' => []];
		// TODO insert speed and bearing in m/s and degrees
		if ($devicename !== '' &&
			$token !== '' &&
			(!is_null($timestamp) || !is_null($datetime))
		) {
			// check if session exists
			$sqlCheck = '
				SELECT `name`, `user`, `public`, `locked`
				FROM `*PREFIX*phonetrack_sessions`
				WHERE `token`=?
			';
			$req = $this->dbconnection->prepare($sqlCheck);
			$res = $req->execute([$token]);
			$dbname = null;
			$userid = null;
			$locked = null;
			$isPublicSession = null;
			while ($row = $res->fetch()) {
				$dbname = $row['name'];
				$userid = $row['user'];
				$locked = (((int)$row['locked']) === 1);
				$isPublicSession = (bool)$row['public'];
				break;
			}
			$res->closeCursor();

			// is it a share token?
			if ($dbname === null) {
				$dbtoken = null;
				// get real token from sharetoken
				$sqlGet = '
					SELECT sessionid
					FROM *PREFIX*phonetrack_shares
					WHERE sharetoken='.$this->db_quote_escape_string($token).';';
				$req = $this->dbconnection->prepare($sqlGet);
				$res = $req->execute();
				while ($row = $res->fetch()) {
					$dbtoken = $row['sessionid'];
				}
				$res->closeCursor();
				if ($dbtoken !== null) {
					$token = $dbtoken;
					// get session info
					$sqlCheck = '
						SELECT `name`, `user`, `public`, `locked`
						FROM `*PREFIX*phonetrack_sessions`
						WHERE `token`=?
					';
					$req = $this->dbconnection->prepare($sqlCheck);
					$res = $req->execute([$token]);
					$dbname = null;
					$userid = null;
					$locked = null;
					$isPublicSession = null;
					while ($row = $res->fetch()) {
						$dbname = $row['name'];
						$userid = $row['user'];
						$locked = (((int)$row['locked']) === 1);
						$isPublicSession = (bool)$row['public'];
						break;
					}
					$res->closeCursor();
				}
			}

			if ($dbname !== null) {
				if (!$locked) {
					$humanReadableDeviceName = $devicename;
					// check if this devicename is reserved or exists
					$dbdevicename = null;
					$dbdevicealias = null;
					$dbdevicenametoken = null;
					$deviceIdToInsert = null;
					$sqlGetRes = '
						SELECT id, name, nametoken, alias
						FROM *PREFIX*phonetrack_devices
						WHERE sessionid='.$this->db_quote_escape_string($token).'
							  AND name='.$this->db_quote_escape_string($devicename).' ;';
					$req = $this->dbconnection->prepare($sqlGetRes);
					$res = $req->execute();
					while ($row = $res->fetch()) {
						$dbdeviceId = (int)$row['id'];
						$dbdevicename = $row['name'];
						$dbdevicealias = $row['alias'];
						$dbdevicenametoken = $row['nametoken'];
					}
					$res->closeCursor();

					// the device exists
					if ($dbdevicename !== null) {
						if (!empty($dbdevicealias)) {
							$humanReadableDeviceName = $dbdevicealias.' ('.$dbdevicename.')';
						} else {
							$humanReadableDeviceName = $dbdevicename;
						}
						// this device id reserved => logging refused if the request does not come from correct user
						if ($dbdevicenametoken !== null && $dbdevicenametoken !== '') {
							// here, we check if we're logged in as the session owner
							if ($this->userId !== '' && $this->userId !== null && $userid === $this->userId) {
								// if so, accept to (manually) log with name and not nametoken
								$deviceIdToInsert = $dbdeviceId;
							} else {
								return $result;
							}
						} else {
							$deviceIdToInsert = $dbdeviceId;
						}
					}
					// the device with this device name does not exist
					else {
						// check if the device name corresponds to a name token
						$dbdevicenametoken = null;
						$dbdevicename = null;
						$dbdevicealias = null;
						$sqlGetRes = '
							SELECT id, name, nametoken, alias
							FROM *PREFIX*phonetrack_devices
							WHERE sessionid='.$this->db_quote_escape_string($token).'
								  AND nametoken='.$this->db_quote_escape_string($devicename).' ;';
						$req = $this->dbconnection->prepare($sqlGetRes);
						$res = $req->execute();
						while ($row = $res->fetch()) {
							$dbdeviceId = (int)$row['id'];
							$dbdevicename = $row['name'];
							$dbdevicealias = $row['alias'];
							$dbdevicenametoken = $row['nametoken'];
						}
						$res->closeCursor();

						// there is a device which has this nametoken => we log for this device
						if ($dbdevicenametoken !== null && $dbdevicenametoken !== '') {
							$deviceIdToInsert = $dbdeviceId;
							if (!empty($dbdevicealias)) {
								$humanReadableDeviceName = $dbdevicealias.' ('.$dbdevicename.')';
							} else {
								$humanReadableDeviceName = $dbdevicename;
							}
						} else {
							// device does not exist and there is no reservation corresponding
							// => we create it
							$sql = '
								INSERT INTO *PREFIX*phonetrack_devices
								(name, sessionid)
								VALUES ('.
									$this->db_quote_escape_string($devicename).','.
									$this->db_quote_escape_string($token).
								') ;';
							$req = $this->dbconnection->prepare($sql);
							$req->execute();

							// get the newly created device id
							$sqlGetdeviceId = '
								SELECT id
								FROM *PREFIX*phonetrack_devices
								WHERE sessionid='.$this->db_quote_escape_string($token).'
									  AND name='.$this->db_quote_escape_string($devicename).' ;';
							$req = $this->dbconnection->prepare($sqlGetdeviceId);
							$res = $req->execute();
							while ($row = $res->fetch()) {
								$deviceIdToInsert = (int)$row['id'];
							}
							$res->closeCursor();
						}
					}

					if ($timestamp !== null) {
						// correct timestamp if needed
						$time = (float) $timestamp;
						if ($time > 10000000000.0) {
							$time = $time / 1000;
						}
					} else {
						// we have a datetime
						try {
							$d = new DateTime($datetime);
							$time = $d->getTimestamp();
						} catch (Exception | Throwable $e) {
							try {
								$dateTimeZone = null;
								if (($userid ?? '') !== '') {
									$timezone = $this->config->getUserValue($userid, 'core', 'timezone');
									if ($timezone !== '') {
										$dateTimeZone = new DateTimeZone($timezone);
									}
								}
								$d = DateTime::createFromFormat('F d, Y \a\t h:iA', $datetime, $dateTimeZone);
								$time = $d->getTimestamp();
							} catch (Exception | Throwable $e) {
								return $result;
							}
						}
					}

					if ($acc !== null) {
						$acc = (float) sprintf('%.2f', $acc);
					}

					// geofences, proximity alerts, quota
					$this->checkGeoFences($lat, $lon, $deviceIdToInsert, $userid, $humanReadableDeviceName, $dbname, $token);
					$this->checkProxims($lat, $lon, $deviceIdToInsert, $userid, $humanReadableDeviceName, $dbname, $token);
					$quotaClearance = $this->checkQuota($deviceIdToInsert, $userid, $humanReadableDeviceName, $dbname);

					if (!$quotaClearance) {
						$result['done'] = 2;
						return $result;
					}

					$lat = $this->db_quote_escape_string(number_format($lat, 8, '.', ''));
					$lon = $this->db_quote_escape_string(number_format($lon, 8, '.', ''));
					$time = $this->db_quote_escape_string(number_format($time, 0, '.', ''));
					$alt = is_numeric($alt) ? $this->db_quote_escape_string(number_format($alt, 2, '.', '')) : 'NULL';
					$acc = $acc !== null ? $this->db_quote_escape_string(number_format($acc, 2, '.', '')) : 'NULL';
					$bat = is_numeric($bat) ? $this->db_quote_escape_string(number_format($bat, 2, '.', '')) : 'NULL';
					$sat = is_numeric($sat) ? $this->db_quote_escape_string(number_format($sat, 0, '.', '')) : 'NULL';
					$speed = is_numeric($speed) ? $this->db_quote_escape_string(number_format($speed, 3, '.', '')) : 'NULL';
					$bearing = is_numeric($bearing) ? $this->db_quote_escape_string(number_format($bearing, 2, '.', '')) : 'NULL';

					$sql = '
						INSERT INTO *PREFIX*phonetrack_points
						(deviceid, lat, lon, timestamp, accuracy, satellites, altitude, batterylevel, useragent, speed, bearing)
						VALUES ('.
							$this->db_quote_escape_string($deviceIdToInsert).','.
							$lat.','.
							$lon.','.
							$time.','.
							$acc.','.
							$sat.','.
							$alt.','.
							$bat.','.
							$this->db_quote_escape_string($useragent).','.
							$speed.','.
							$bearing.'
						) ;';
					$req = $this->dbconnection->prepare($sql);
					$req->execute();

					$result['done'] = 1;

					if ($isPublicSession && $useragent === self::LOG_OWNTRACKS) {
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
							WHERE `sessionid` = ?
						';
						$friendRequest = $this->dbconnection->prepare($friendSQL);
						$res = $friendRequest->execute([$token]);
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
				} else {
					$result['done'] = 3;
				}
			}
		}
		return $result;
	}

	/**
	 * @param string $token
	 * @param string $devicename
	 * @param array $points
	 * @return array
	 * @throws \Doctrine\DBAL\Exception
	 * @throws \OCP\DB\Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function logPostMultiple(string $token, string $devicename, array $points) {
		$result = [
			'done' => 0,
			'friends' => [],
		];
		if ($devicename !== '' && $token !== '') {
			// check if session exists
			$sqlCheck = '
				SELECT `name`, `user`, `public`, `locked`
				FROM `*PREFIX*phonetrack_sessions`
				WHERE `token`=?
			';
			$req = $this->dbconnection->prepare($sqlCheck);
			$res = $req->execute([$token]);
			$dbname = null;
			$userid = null;
			$locked = null;
			$isPublicSession = null;
			while ($row = $res->fetch()) {
				$dbname = $row['name'];
				$userid = $row['user'];
				$locked = (((int)$row['locked']) === 1);
				$isPublicSession = (bool)$row['public'];
				break;
			}
			$res->closeCursor();

			// is it a share token?
			if ($dbname === null) {
				$dbToken = null;
				// get real token from sharetoken
				$sqlGet = '
					SELECT sessionid
					FROM *PREFIX*phonetrack_shares
					WHERE sharetoken='.$this->db_quote_escape_string($token).';';
				$req = $this->dbconnection->prepare($sqlGet);
				$res = $req->execute();
				while ($row = $res->fetch()) {
					$dbToken = $row['sessionid'];
				}
				$res->closeCursor();
				if ($dbToken !== null) {
					$token = $dbToken;
					// get session info
					$sqlCheck = '
						SELECT `name`, `user`, `public`, `locked`
						FROM `*PREFIX*phonetrack_sessions`
						WHERE `token`=?
					';
					$req = $this->dbconnection->prepare($sqlCheck);
					$res = $req->execute([$token]);
					$dbname = null;
					$userid = null;
					$locked = null;
					$isPublicSession = null;
					while ($row = $res->fetch()) {
						$dbname = $row['name'];
						$userid = $row['user'];
						$locked = (((int)$row['locked']) === 1);
						$isPublicSession = (bool)$row['public'];
						break;
					}
					$res->closeCursor();
				}
			}

			if ($dbname !== null) {
				if (!$locked) {
					$humanReadableDeviceName = $devicename;
					// check if this device name is reserved or exists
					$dbdevicename = null;
					$dbdevicealias = null;
					$dbdevicenametoken = null;
					$deviceIdToInsert = null;
					$sqlgetres = '
						SELECT id, name, nametoken, alias
						FROM *PREFIX*phonetrack_devices
						WHERE sessionid='.$this->db_quote_escape_string($token).'
							  AND name='.$this->db_quote_escape_string($devicename).' ;';
					$req = $this->dbconnection->prepare($sqlgetres);
					$res = $req->execute();
					while ($row = $res->fetch()) {
						$dbDeviceId = $row['id'];
						$dbdevicename = $row['name'];
						$dbdevicealias = $row['alias'];
						$dbdevicenametoken = $row['nametoken'];
					}
					$res->closeCursor();

					// the device exists
					if ($dbdevicename !== null) {
						if (!empty($dbdevicealias)) {
							$humanReadableDeviceName = $dbdevicealias.' ('.$dbdevicename.')';
						} else {
							$humanReadableDeviceName = $dbdevicename;
						}
						// this device id reserved => logging refused if the request does not come from correct user
						if ($dbdevicenametoken !== null && $dbdevicenametoken !== '') {
							// here, we check if we're logged in as the session owner
							if ($this->userId !== '' && $this->userId !== null && $userid === $this->userId) {
								// if so, accept to (manually) log with name and not nametoken
								$deviceIdToInsert = $dbDeviceId;
							} else {
								return $result;
							}
						} else {
							$deviceIdToInsert = $dbDeviceId;
						}
					}
					// the device with this device name does not exist
					else {
						// check if the device name corresponds to a nametoken
						$dbdevicenametoken = null;
						$dbdevicename = null;
						$dbdevicealias = null;
						$sqlgetres = '
							SELECT id, name, nametoken, alias
							FROM *PREFIX*phonetrack_devices
							WHERE sessionid='.$this->db_quote_escape_string($token).'
								  AND nametoken='.$this->db_quote_escape_string($devicename).' ;';
						$req = $this->dbconnection->prepare($sqlgetres);
						$req->execute();
						while ($row = $req->fetch()) {
							$dbDeviceId = (int)$row['id'];
							$dbdevicename = $row['name'];
							$dbdevicealias = $row['alias'];
							$dbdevicenametoken = $row['nametoken'];
						}
						$req->closeCursor();

						// there is a device which has this nametoken => we log for this device
						if ($dbdevicenametoken !== null && $dbdevicenametoken !== '') {
							$deviceIdToInsert = $dbDeviceId;
							if (!empty($dbdevicealias)) {
								$humanReadableDeviceName = $dbdevicealias . ' (' . $dbdevicename . ')';
							} else {
								$humanReadableDeviceName = $dbdevicename;
							}
						} else {
							// device does not exist and there is no reservation corresponding
							// => we create it
							$sql = '
								INSERT INTO *PREFIX*phonetrack_devices
								(name, sessionid)
								VALUES ('.
									$this->db_quote_escape_string($devicename).','.
									$this->db_quote_escape_string($token).
								') ;';
							$req = $this->dbconnection->prepare($sql);
							$req->execute();

							// get the newly created device id
							$sqlGetDeviceId = '
								SELECT id
								FROM *PREFIX*phonetrack_devices
								WHERE sessionid='.$this->db_quote_escape_string($token).'
									  AND name='.$this->db_quote_escape_string($devicename).' ;';
							$req = $this->dbconnection->prepare($sqlGetDeviceId);
							$res = $req->execute();
							while ($row = $res->fetch()) {
								$deviceIdToInsert = (int)$row['id'];
							}
							$res->closeCursor();
						}
					}

					// check quota once before inserting anything
					// it will delete points to make room if needed
					$quotaClearance = $this->checkQuota($deviceIdToInsert, $userid, $humanReadableDeviceName, $dbname, count($points));

					if (!$quotaClearance) {
						$result['done'] = 2;
						return $result;
					}

					// check geofences and proxims only once with the last point
					if (count($points) > 0) {
						$lastPointToInsert = $points[count($points) - 1];
						$lat = $lastPointToInsert[0];
						$lon = $lastPointToInsert[1];
						$this->checkGeoFences((float)$lat, (float)$lon, $deviceIdToInsert, $userid, $humanReadableDeviceName, $dbname, $token);
						$this->checkProxims((float)$lat, (float)$lon, $deviceIdToInsert, $userid, $humanReadableDeviceName, $dbname, $token);
					}

					$valuesToInsert = [];
					$nbToInsert = 0;
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

						if ($token !== ''
							&& $lat !== '' && is_numeric($lat)
							&& $lon !== '' && is_numeric($lon)
							&& $timestamp !== '' && is_numeric($timestamp)
						) {
							// correct timestamp if needed
							$time = (float)$timestamp;
							if ($time > 10000000000.0) {
								$time = $time / 1000;
							}

							$lat = $this->db_quote_escape_string(number_format((float) $lat, 8, '.', ''));
							$lon = $this->db_quote_escape_string(number_format((float) $lon, 8, '.', ''));
							$time = $this->db_quote_escape_string(number_format($time, 0, '.', ''));
							$alt = is_numeric($alt) ? $this->db_quote_escape_string(number_format((float) $alt, 2, '.', '')) : 'NULL';
							$acc = is_numeric($acc) ? $this->db_quote_escape_string(number_format((float) $acc, 2, '.', '')) : 'NULL';
							$bat = is_numeric($bat) ? $this->db_quote_escape_string(number_format((float) $bat, 2, '.', '')) : 'NULL';
							$sat = is_numeric($sat) ? $this->db_quote_escape_string(number_format((float) $sat, 0, '.', '')) : 'NULL';
							$speed = is_numeric($speed) ? $this->db_quote_escape_string(number_format((float) $speed, 3, '.', '')) : 'NULL';
							$bearing = is_numeric($bearing) ? $this->db_quote_escape_string(number_format((float) $bearing, 2, '.', '')) : 'NULL';

							$value = '('.
									  $this->db_quote_escape_string($deviceIdToInsert).','.
									  $lat.','.
									  $lon.','.
									  $time.','.
									  $acc.','.
									  $sat.','.
									  $alt.','.
									  $bat.','.
									  $this->db_quote_escape_string($useragent).','.
									  $speed.','.
									  $bearing.'
							  )';
							$valuesToInsert[] = $value;
							$nbToInsert++;

							// insert by bunch of 50 points
							if ($nbToInsert % 50 === 0) {
								$sql = '
									INSERT INTO *PREFIX*phonetrack_points
									(deviceid, lat, lon, timestamp, accuracy, satellites, altitude, batterylevel, useragent, speed, bearing)
									VALUES '.implode(', ', $valuesToInsert).' ;';
								$req = $this->dbconnection->prepare($sql);
								$req->execute();
								$valuesToInsert = [];
							}
						}
					}
					// insert last bunch of points
					if (count($valuesToInsert) > 0) {
						$sql = '
							INSERT INTO *PREFIX*phonetrack_points
							(deviceid, lat, lon, timestamp, accuracy, satellites, altitude, batterylevel, useragent, speed, bearing)
							VALUES '.implode(', ', $valuesToInsert).' ;';
						$req = $this->dbconnection->prepare($sql);
						$req->execute();
					}

					$result['done'] = 1;

					if ($isPublicSession && $useragent === self::LOG_OWNTRACKS) {
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
								WHERE `sessionid` = ?
								GROUP BY `deviceid`, `nametoken`, `name`
							) l ON p.`deviceid` = l.`deviceid`
							AND p.`timestamp` = l.`lastupdate`
						';
						$friendReq = $this->dbconnection->prepare($friendSQL);
						$res = $friendReq->execute([$token]);
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
				} else {
					$result['done'] = 3;
				}
			}
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
		string $useragent = 'unknown GET logger'
	) {
		$dName = $this->chooseDeviceName($devicename);
		if ($bat !== null) {
			$bat = (int) $bat;
		}
		return $this->logPost($token, $dName, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, $useragent, $speed, $bearing, $datetime);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function logLocusmapGet(
		string $token, string $devicename, float $lat, float $lon, ?int $time = null,
		?float $battery = null, ?float $acc = null, ?float $alt = null,
		?float $speed = null, ?float $bearing = null
	) {
		$dName = $this->chooseDeviceName($devicename);
		if ($battery !== null) {
			$battery = (int) $battery;
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
			$battery = (int) $battery;
		}
		$this->logPost($token, $dName, $lat, $lon, $alt, $time, $acc, $battery, null, 'LocusMap', $speed, $bearing);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function logOsmand(
		string $token, string $devicename, float $lat, float $lon, ?int $timestamp = null,
		?float $bat = null, ?int $sat = null, ?float $acc = null, ?float $alt = null,
		?float $speed = null, ?float $bearing = null
	) {
		$dName = $this->chooseDeviceName($devicename);
		if ($bat !== null) {
			$bat = (int) $bat;
		}
		$this->logPost($token, $dName, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, 'OsmAnd', $speed, $bearing);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function logGpsloggerGet(
		string $token, string $devicename, float $lat, float $lon, ?int $timestamp = null,
		?float $bat = null, ?int $sat = null, ?float $acc = null, ?float $alt = null,
		?float $speed = null, ?float $bearing = null
	) {
		$dName = $this->chooseDeviceName($devicename);
		if ($bat !== null) {
			$bat = (int) $bat;
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
			$bat = (int) $bat;
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
		?float $alt = null, ?int $tst = null, ?float $acc = null, ?float $batt = null
	) {
		if (is_null($lat) || is_null($lon)) {
			// empty message (control message?) - ignore
			return ['result' => 'ok'];
		}
		$dname = $this->chooseDeviceName($devicename, $tid);
		if ($batt !== null) {
			$batt = (int) $batt;
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
				$bat = ((float)$loc['properties']['battery_level']) * 100;
				$speed = $loc['properties']['speed'];
				$bearing = null;
				$sat = null;
				$alt = $loc['properties']['altitude'];
				$this->logPost($token, $dname, $lat, $lon, $alt, $timestamp, $acc, (int) $bat, $sat, 'Overland', $speed, $bearing);
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
		string $token, float $lat, float $lon, ?int $timestamp = null,
		?string $devicename = null, ?string $id = null, ?float $accuracy = null,
		?float $altitude = null, ?float $batt = null, ?float $speed = null, ?float $bearing = null
	) {
		$dname = $this->chooseDeviceName($devicename, $id);
		$speedp = $speed;
		if ($speed !== null) {
			// according to traccar sources, speed is converted in knots...
			// convert back to meter/s
			$speedp = $speed / 1.943844;
		}
		if ($batt !== null) {
			$batt = (int) $batt;
		}
		$this->logPost($token, $dname, $lat, $lon, $altitude, $timestamp, $accuracy, $batt, null, 'Traccar', $speedp, $bearing);
	}

	/**
	 * Any OpenGTS-compliant app
	 **/
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function logOpengts(
		string $token, string $gprmc,
		?string $devicename = null, ?string $id = null, ?float $alt = null, ?float $batt = null
	) {
		$dname = $this->chooseDeviceName($devicename, $id);
		$gprmca = explode(',', $gprmc);
		$time = sprintf('%06d', (int)$gprmca[1]);
		$date = sprintf('%06d', (int)$gprmca[9]);
		$datetime = DateTime::createFromFormat('dmy His', $date.' '.$time);
		$timestamp = $datetime->getTimestamp();
		$lat = DMStoDEC(sprintf('%010.4f', (float)$gprmca[3]), 'latitude');
		if ($gprmca[4] === 'S') {
			$lat = - $lat;
		}
		$lon = DMStoDEC(sprintf('%010.4f', (float)$gprmca[5]), 'longitude');
		if ($gprmca[6] === 'W') {
			$lon = - $lon;
		}
		if ($batt !== null) {
			$batt = (int) $batt;
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
