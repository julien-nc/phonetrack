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
use OCA\PhoneTrack\AppInfo\Application;
use OCP\App\IAppManager;

use OCP\IConfig;
use OCP\IDBConnection;
use \OCP\IL10N;
use Psr\Log\LoggerInterface;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\PhoneTrack\Db\SessionMapper;
use OCA\PhoneTrack\Db\DeviceMapper;
use OCA\PhoneTrack\Activity\ActivityManager;
use Throwable;

function DMStoDEC($dms, $longlat) {
	if ($longlat === 'latitude') {
		$deg = intval(substr($dms, 0, 3));
		$min = floatval(substr($dms, 3, 8));
		$sec = 0;
	}
	if ($longlat === 'longitude') {
		$deg = intval(substr($dms, 0, 3));
		$min = floatval(substr($dms, 3, 8));
		$sec = 0;
	}
	return $deg + ((($min * 60) + ($sec)) / 3600);
}

function startsWith($haystack, $needle) {
	$length = strlen($needle);
	return (substr($haystack, 0, $length) === $needle);
}

function distance2($lat1, $long1, $lat2, $long2) {

	if ($lat1 === $lat2 && $long1 === $long2){
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
	private $appVersion;
	private $shareManager;
	private $dbconnection;
	private $dbtype;
	private $dbdblquotes;
	private $defaultDeviceName;
	private $trans;
	private $userManager;
	private $ncLogger;

	const LOG_OWNTRACKS = 'Owntracks';

	public function __construct(string $AppName,
								IRequest $request,
								IConfig $config,
								IManager $shareManager,
								IUserManager $userManager,
								IL10N $trans,
								LoggerInterface $ncLogger,
								ActivityManager $activityManager,
								SessionMapper $sessionMapper,
								DeviceMapper $deviceMapper,
								IDBConnection $dbconnection,
								?string $userId) {
		parent::__construct($AppName, $request);
		$this->appVersion = $config->getAppValue('phonetrack', 'installed_version');
		$this->userId = $userId;
		$this->activityManager = $activityManager;
		$this->sessionMapper = $sessionMapper;
		$this->deviceMapper = $deviceMapper;
		$this->trans = $trans;
		$this->ncLogger = $ncLogger;
		$this->userManager = $userManager;
		$this->dbtype = $config->getSystemValue('dbtype');
		// IConfig object
		$this->config = $config;

		if ($this->dbtype === 'pgsql'){
			$this->dbdblquotes = '"';
		}
		else{
			$this->dbdblquotes = '';
		}
		$this->dbconnection = $dbconnection;
		$this->shareManager = $shareManager;
		$this->defaultDeviceName = ['yourname', 'devicename', 'name'];
	}

	/*
	 * quote and choose string escape function depending on database used
	 */
	private function db_quote_escape_string($str){
		return $this->dbconnection->quote($str);
	}

	/**
	 * if devicename is not set to default value, we take it
	 * else
	 */
	private function chooseDeviceName($devicename, $tid) {
		if ( (!in_array($devicename, $this->defaultDeviceName))
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

	private function getLastDevicePoint($devid) {
		$therow = null;
		$sqlget = '
			SELECT lat, lon, timestamp,
				   batterylevel, satellites,
				   accuracy, altitude,
				   speed, bearing
			FROM *PREFIX*phonetrack_points
			WHERE deviceid='.$this->db_quote_escape_string($devid).'
			ORDER BY timestamp DESC LIMIT 1 ;';
		$req = $this->dbconnection->prepare($sqlget);
		$req->execute();
		while ($row = $req->fetch()){
			$therow = $row;
		}
		$req->closeCursor();
		return $therow;
	}

	private function getDeviceProxims($devid) {
		$proxims = array();
		$sqlget = '
			SELECT id, deviceid1, deviceid2, highlimit,
				   lowlimit, urlclose, urlfar,
				   urlclosepost, urlfarpost,
				   sendemail, emailaddr, sendnotif
			FROM *PREFIX*phonetrack_proxims
			WHERE deviceid1='.$this->db_quote_escape_string($devid).'
				  OR deviceid2='.$this->db_quote_escape_string($devid).' ;';
		$req = $this->dbconnection->prepare($sqlget);
		$req->execute();
		while ($row = $req->fetch()){
			array_push($proxims, $row);
		}
		$req->closeCursor();
		return $proxims;
	}

	private function checkProxims($lat, $lon, $devid, $userid, $devicename, $sessionname, $sessionid) {
		$lastPoint = $this->getLastDevicePoint($devid);
		$proxims = $this->getDeviceProxims($devid);
		foreach ($proxims as $proxim) {
			$this->checkProxim($lat, $lon, $devid, $proxim, $userid, $lastPoint, $devicename, $sessionid);
		}
	}

	private function getSessionOwnerOfDevice($devid) {
		$owner = null;
		$sqlget = '
			SELECT '.$this->dbdblquotes.'user'.$this->dbdblquotes.'
			FROM *PREFIX*phonetrack_devices
			INNER JOIN *PREFIX*phonetrack_sessions
				ON *PREFIX*phonetrack_devices.sessionid=*PREFIX*phonetrack_sessions.token
			WHERE *PREFIX*phonetrack_devices.id='.$this->db_quote_escape_string($devid).' ;';
		$req = $this->dbconnection->prepare($sqlget);
		$req->execute();
		while ($row = $req->fetch()){
			$owner = $row['user'];
		}
		$req->closeCursor();
		return $owner;
	}

	private function getDeviceAlias($devid) {
		$dbalias = null;
		$sqlget = '
			SELECT alias
			FROM *PREFIX*phonetrack_devices
			WHERE id='.$this->db_quote_escape_string($devid).' ;';
		$req = $this->dbconnection->prepare($sqlget);
		$req->execute();
		while ($row = $req->fetch()){
			$dbalias = $row['alias'];
		}
		$req->closeCursor();

		return $dbalias;
	}

	private function getDeviceName($devid) {
		$dbname = null;
		$sqlget = '
			SELECT name
			FROM *PREFIX*phonetrack_devices
			WHERE id='.$this->db_quote_escape_string($devid).' ;';
		$req = $this->dbconnection->prepare($sqlget);
		$req->execute();
		while ($row = $req->fetch()){
			$dbname = $row['name'];
		}
		$req->closeCursor();

		return $dbname;
	}

	private function checkProxim($newLat, $newLon, $movingDevid, $proxim, $userid, $lastPoint, $movingDeviceName, $sessionid) {
		$highlimit = intval($proxim['highlimit']);
		$lowlimit = intval($proxim['lowlimit']);
		$urlclose = $proxim['urlclose'];
		$urlfar = $proxim['urlfar'];
		$urlclosepost = intval($proxim['urlclosepost']);
		$urlfarpost = intval($proxim['urlfarpost']);
		$sendemail = intval($proxim['sendemail']);
		$sendnotif = intval($proxim['sendnotif']);
		$emailaddr = $proxim['emailaddr'];
		if ($emailaddr === null) {
			$emailaddr = '';
		}
		$proximid = $proxim['id'];

		$otherDeviceId = null;
		// get the deviceid of other device
		if (intval($movingDevid) === intval($proxim['deviceid1'])) {
			$otherDeviceId = intval($proxim['deviceid2']);
		}
		else {
			$otherDeviceId = intval($proxim['deviceid1']);
		}

		// get coords of other device
		$lastOtherPoint = $this->getLastDevicePoint($otherDeviceId);
		$latOther = floatval($lastOtherPoint['lat']);
		$lonOther = floatval($lastOtherPoint['lon']);

		if ($lastPoint !== null) {
			// previous coords of observed device
			$prevLat = floatval($lastPoint['lat']);
			$prevLon = floatval($lastPoint['lon']);

			$prevDist = distance2($prevLat, $prevLon, $latOther, $lonOther);
			$currDist = distance2($newLat, $newLon, $latOther, $lonOther);

			// if distance was not close and is now close
			if ($lowlimit !== 0 and $prevDist >= $lowlimit and $currDist < $lowlimit) {
				// devices are now close !

				// if the observed device is 'deviceid2', then we might have the wrong userId
				if (intval($movingDevid) === intval($proxim['deviceid2'])) {
					$userid = $this->getSessionOwnerOfDevice($proxim['deviceid1']);
				}
				$dev1name = $movingDeviceName;
				$dev2name = $this->getDeviceName($otherDeviceId);
				$dev2alias = $this->getDeviceAlias($otherDeviceId);
				if (!empty($dev2alias)) {
					$dev2name = $dev2alias.' ('.$dev2name.')';
				}

				// activity
				$deviceObj = $this->deviceMapper->find($movingDevid);
				$this->activityManager->triggerEvent(
					ActivityManager::PHONETRACK_OBJECT_DEVICE, $deviceObj,
					ActivityManager::SUBJECT_PROXIMITY_CLOSE,
					[
						'device2'=>['id'=>$otherDeviceId],
						'meters'=>['id'=>0,'name'=>$lowlimit],
					]
				);

				// NOTIFICATIONS
				if ($sendnotif !== 0) {
					$userIds = $this->getSessionSharedUserIdList($sessionid);
					$userIds[] = $userid;

					try {
						$manager = \OC::$server->getNotificationManager();
						foreach ($userIds as $aUserId) {
							$notification = $manager->createNotification();

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

							$manager->notify($notification);
						}
					}
					catch (\Exception $e) {
						$this->ncLogger->warning('Error sending PhoneTrack notification : '.$e, array('app' => $this->appName));
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
						(count($emailaddrArray) === 0
						 || (count($emailaddrArray) === 1 && $emailaddrArray[0] === ''))
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
								} catch (\Exception $e) {
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
						} catch (\Exception $e) {
							$this->ncLogger->warning('Error during PhoneTrack proxim URL query : '.$e, array('app' => $this->appName));
						}
					} else {
						// POST
						try {
							$parts = parse_url($urlclose);
							parse_str($parts['query'], $data);

							$url = $parts['scheme'].'://'.$parts['host'].$parts['path'];

							$options = [
								'http' => [
									'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
									'method'  => 'POST',
									'content' => http_build_query($data),
								]
							];
							$context  = stream_context_create($options);
							$result = file_get_contents($url, false, $context);
						}
						catch (\Exception $e) {
							$this->ncLogger->warning('Error during PhoneTrack proxim POST URL query : '.$e, ['app' => $this->appName]);
						}
					}
				}
			} elseif ($highlimit !== 0 && $prevDist <= $highlimit && $currDist > $highlimit) {
				// devices are now far !

				// if the observed device is 'deviceid2', then we might have the wrong userId
				if (intval($movingDevid) === intval($proxim['deviceid2'])) {
					$userid = $this->getSessionOwnerOfDevice($proxim['deviceid1']);
				}
				$dev1name = $movingDeviceName;
				$dev2name = $this->getDeviceName($otherDeviceId);
				$dev2alias = $this->getDeviceAlias($otherDeviceId);
				if (!empty($dev2alias)) {
					$dev2name = $dev2alias.' ('.$dev2name.')';
				}

				// activity
				$deviceObj = $this->deviceMapper->find($movingDevid);
				$this->activityManager->triggerEvent(
					ActivityManager::PHONETRACK_OBJECT_DEVICE, $deviceObj,
					ActivityManager::SUBJECT_PROXIMITY_FAR,
					[
						'device2'=>['id'=>$otherDeviceId],
						'meters'=>['id'=>0,'name'=>$highlimit],
					]
				);

				// NOTIFICATIONS
				if ($sendnotif !== 0) {
					$userIds = $this->getSessionSharedUserIdList($sessionid);
					array_push($userIds, $userid);

					try {
						$manager = \OC::$server->getNotificationManager();
						foreach ($userIds as $aUserId) {
							$notification = $manager->createNotification();

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

							$manager->notify($notification);
						}
					} catch (\Exception $e) {
						$this->ncLogger->warning('Error sending PhoneTrack notification : '.$e, array('app' => $this->appName));
					}
				}

				if ($sendemail !== 0) {

					$user = $this->userManager->get($userid);
					$userEmail = $user->getEMailAddress();
					$mailFromA = $this->config->getSystemValue('mail_from_address', 'phonetrack');
					$mailFromD = $this->config->getSystemValue('mail_domain', 'nextcloud.your');

					$emailaddrArray = explode(',', $emailaddr);
					if (
						(count($emailaddrArray) === 0
						 || (count($emailaddrArray) === 1 && $emailaddrArray[0] === ''))
						&& !empty($userEmail)
					) {
						array_push($emailaddrArray, $userEmail);
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
								} catch (\Exception $e) {
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
						} catch (\Exception $e) {
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
									'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
									'method'  => 'POST',
									'content' => http_build_query($data),
								]
							];
							$context  = stream_context_create($options);
							$result = file_get_contents($url, false, $context);
						} catch (\Exception $e) {
							$this->ncLogger->warning('Error during PhoneTrack proxim POST URL query : '.$e, ['app' => $this->appName]);
						}
					}
				}
			}
		}
	}

	private function getDeviceFences($devid) {
		$fences = [];
		$sqlget = '
			SELECT id, latmin, lonmin, latmax, lonmax,
				   name, urlenter, urlleave,
				   urlenterpost, urlleavepost,
				   sendemail, emailaddr, sendnotif
			FROM *PREFIX*phonetrack_geofences
			WHERE deviceid='.$this->db_quote_escape_string($devid).' ;';
		$req = $this->dbconnection->prepare($sqlget);
		$req->execute();
		while ($row = $req->fetch()) {
			$fences[] = $row;
		}
		$req->closeCursor();
		return $fences;
	}

	/**
	 * returns user ids the session is shared with
	 */
	private function getSessionSharedUserIdList($token) {
		$userids = [];
		$sqlget = '
			SELECT username
			FROM *PREFIX*phonetrack_shares
			WHERE sessionid='.$this->db_quote_escape_string($token).' ;';
		$req = $this->dbconnection->prepare($sqlget);
		$req->execute();
		while ($row = $req->fetch()){
			array_push($userids, $row['username']);
		}
		$req->closeCursor();
		return $userids;
	}

	private function checkGeoFences($lat, $lon, $devid, $userid, $devicename, $sessionname, $sessionid) {
		$lastPoint = $this->getLastDevicePoint($devid);
		$fences = $this->getDeviceFences($devid);
		foreach ($fences as $fence) {
			$this->checkGeoGence($lat, $lon, $lastPoint, $devid, $fence, $userid, $devicename, $sessionname, $sessionid);
		}
	}

	private function checkGeoGence($lat, $lon, $lastPoint, $devid, $fence, $userid, $devicename, $sessionname, $sessionid) {
		$latmin = floatval($fence['latmin']);
		$latmax = floatval($fence['latmax']);
		$lonmin = floatval($fence['lonmin']);
		$lonmax = floatval($fence['lonmax']);
		$urlenter = $fence['urlenter'];
		$urlleave = $fence['urlleave'];
		$urlenterpost = intval($fence['urlenterpost']);
		$urlleavepost = intval($fence['urlleavepost']);
		$sendemail = intval($fence['sendemail']);
		$sendnotif = intval($fence['sendnotif']);
		$emailaddr = $fence['emailaddr'];
		if ($emailaddr === null) {
			$emailaddr = '';
		}
		$fencename = $fence['name'];
		$fenceid = $fence['id'];

		// first point of this device
		if ($lastPoint === null) {
			if (    $lat > $latmin
				and $lat < $latmax
				and $lon > $lonmin
				and $lon < $lonmax
			) {
			}
		}
		// not the first point
		else {
			$lastLat = floatval($lastPoint['lat']);
			$lastLon = floatval($lastPoint['lon']);

			// if previous point not in fence
			if (!($lastLat > $latmin and $lastLat < $latmax and $lastLon > $lonmin and $lastLon < $lonmax)) {
				// and new point in fence
				if ($lat > $latmin and $lat < $latmax and $lon > $lonmin and $lon < $lonmax) {
					// device ENTERED the fence !
					$user = $this->userManager->get($userid);
					$userEmail = $user->getEMailAddress();
					$mailFromA = $this->config->getSystemValue('mail_from_address', 'phonetrack');
					$mailFromD = $this->config->getSystemValue('mail_domain', 'nextcloud.your');

					// activity
					$deviceObj = $this->deviceMapper->find($devid);
					$this->activityManager->triggerEvent(
						ActivityManager::PHONETRACK_OBJECT_DEVICE, $deviceObj,
						ActivityManager::SUBJECT_GEOFENCE_ENTER,
						['geofence'=>['id'=>$fenceid,'name'=>$fencename]]
					);

					// NOTIFICATIONS
					if ($sendnotif !== 0) {
						$userIds = $this->getSessionSharedUserIdList($sessionid);
						array_push($userIds, $userid);

						try {
							$manager = \OC::$server->getNotificationManager();
							foreach ($userIds as $aUserId) {
								$notification = $manager->createNotification();

								$acceptAction = $notification->createAction();
								$acceptAction->setLabel('accept')
									->setLink('/apps/phonetrack', 'GET');

								$declineAction = $notification->createAction();
								$declineAction->setLabel('decline')
									->setLink('/apps/phonetrack', 'GET');

								$notification->setApp('phonetrack')
									->setUser($aUserId)
									->setDateTime(new \DateTime())
									->setObject('entergeofence', $fenceid) // $type and $id
									->setSubject('enter_geofence', [$sessionname, $devicename, $fencename])
									->addAction($acceptAction)
									->addAction($declineAction)
									;

								$manager->notify($notification);
							}
						}
						catch (\Exception $e) {
							$this->ncLogger->warning('Error sending PhoneTrack notification : '.$e, array('app' => $this->appName));
						}
					}

					// EMAIL
					if ($sendemail !== 0) {
						$emailaddrArray = explode(',', $emailaddr);
						if (
							(count($emailaddrArray) === 0
							 || (count($emailaddrArray) === 1 and $emailaddrArray[0] === ''))
							and !empty($userEmail)
						) {
							array_push($emailaddrArray, $userEmail);
						}
						if (!empty($mailFromA) and !empty($mailFromD)) {
							$mailfrom = $mailFromA.'@'.$mailFromD;

							foreach ($emailaddrArray as $addrTo) {
								if ($addrTo !== null and $addrTo !== '' and filter_var($addrTo, FILTER_VALIDATE_EMAIL)) {
									try {
										$mailer = \OC::$server->getMailer();
										$message = $mailer->createMessage();
										$message->setSubject($this->trans->t('Geofencing alert'));
										$message->setFrom([$mailfrom => 'PhoneTrack']);
										$message->setTo([trim($addrTo) => '']);
										$message->setPlainBody(
											$this->trans->t('In PhoneTrack session %s, device %s has entered geofence %s.', [
												$sessionname,
												$devicename,
												$fencename
											])
										);
										$mailer->send($message);
									}
									catch (\Exception $e) {
										$this->ncLogger->warning('Error during PhoneTrack mail sending : '.$e, array('app' => $this->appName));
									}
								}
							}
						}
					}
					if ($urlenter !== '' and startsWith($urlenter, 'http')) {
						// GET
						$urlenter = str_replace(array('%loc'), $lat.':'.$lon,  $urlenter);
						if ($urlenterpost === 0) {
							try {
								$xml = file_get_contents($urlenter);
							}
							catch (\Exception $e) {
								$this->ncLogger->warning('Error during PhoneTrack geofence URL query : '.$e, array('app' => $this->appName));
							}
						}
						// POST
						else {
							try {
								$parts = parse_url($urlenter);
								parse_str($parts['query'], $data);

								$url = $parts['scheme'].'://'.$parts['host'].$parts['path'];

								$options = array(
									'http' => array(
										'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
										'method'  => 'POST',
										'content' => http_build_query($data)
									)
								);
								$context  = stream_context_create($options);
								$result = file_get_contents($url, false, $context);
							}
							catch (\Exception $e) {
								$this->ncLogger->warning('Error during PhoneTrack geofence POST URL query : '.$e, array('app' => $this->appName));
							}
						}
					}
				}
			}
			// previous point in fence
			else {
				// if new point NOT in fence
				if (!($lat > $latmin and $lat < $latmax and $lon > $lonmin and $lon < $lonmax)) {
					// device EXITED the fence !
					$user = $this->userManager->get($userid);
					$userEmail = $user->getEMailAddress();
					$mailFromA = $this->config->getSystemValue('mail_from_address', 'phonetrack');
					$mailFromD = $this->config->getSystemValue('mail_domain', 'nextcloud.your');

					// activity
					$deviceObj = $this->deviceMapper->find($devid);
					$this->activityManager->triggerEvent(
						ActivityManager::PHONETRACK_OBJECT_DEVICE, $deviceObj,
						ActivityManager::SUBJECT_GEOFENCE_EXIT,
						['geofence'=>['id'=>$fenceid,'name'=>$fencename]]
					);

					// NOTIFICATIONS
					if ($sendnotif !== 0) {
						$userIds = $this->getSessionSharedUserIdList($sessionid);
						array_push($userIds, $userid);

						try {
							$manager = \OC::$server->getNotificationManager();
							foreach ($userIds as $aUserId) {
								$notification = $manager->createNotification();

								$acceptAction = $notification->createAction();
								$acceptAction->setLabel('accept')
									->setLink('/apps/phonetrack', 'GET');

								$declineAction = $notification->createAction();
								$declineAction->setLabel('decline')
									->setLink('/apps/phonetrack', 'GET');

								$notification->setApp('phonetrack')
									->setUser($aUserId)
									->setDateTime(new \DateTime())
									->setObject('leavegeofence', $fenceid) // $type and $id
									->setSubject('leave_geofence', [$sessionname, $devicename, $fencename])
									->addAction($acceptAction)
									->addAction($declineAction)
									;

								$manager->notify($notification);
							}
						}
						catch (\Exception $e) {
							$this->ncLogger->warning('Error sending PhoneTrack notification : '.$e, array('app' => $this->appName));
						}
					}

					// EMAIL
					if ($sendemail !== 0) {
						$emailaddrArray = explode(',', $emailaddr);
						if (
							(count($emailaddrArray) === 0
							 || (count($emailaddrArray) === 1 and $emailaddrArray[0] === ''))
							and !empty($userEmail)
						) {
							array_push($emailaddrArray, $userEmail);
						}
						if (!empty($mailFromA) and !empty($mailFromD)) {
							$mailfrom = $mailFromA.'@'.$mailFromD;

							foreach ($emailaddrArray as $addrTo) {
								if ($addrTo !== null and $addrTo !== '' and filter_var($addrTo, FILTER_VALIDATE_EMAIL)) {
									try {
										$mailer = \OC::$server->getMailer();
										$message = $mailer->createMessage();
										$message->setSubject($this->trans->t('Geofencing alert'));
										$message->setFrom([$mailfrom => 'PhoneTrack']);
										$message->setTo([trim($addrTo) => '']);
										$message->setPlainBody(
											$this->trans->t('In PhoneTrack session %s, device %s exited geofence %s.', [
												$sessionname,
												$devicename,
												$fencename
											])
										);
										$mailer->send($message);
									}
									catch (\Exception $e) {
										$this->ncLogger->warning('Error during PhoneTrack mail sending : '.$e, array('app' => $this->appName));
									}
								}
							}
						}
					}
					if ($urlleave !== '' and startsWith($urlleave, 'http')) {
						// GET
						if ($urlleavepost === 0) {
							$urlleave = str_replace(array('%loc'), $lat.':'.$lon,  $urlleave);
							try {
								$xml = file_get_contents($urlleave);
							}
							catch (\Exception $e) {
								$this->ncLogger->warning('Error during PhoneTrack geofence URL query : '.$e, array('app' => $this->appName));
							}
						}
						// POST
						else {
							try {
								$parts = parse_url($urlleave);
								parse_str($parts['query'], $data);

								$url = $parts['scheme'].'://'.$parts['host'].$parts['path'];

								$options = array(
									'http' => array(
										'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
										'method'  => 'POST',
										'content' => http_build_query($data)
									)
								);
								$context  = stream_context_create($options);
								$result = file_get_contents($url, false, $context);
							}
							catch (\Exception $e) {
								$this->ncLogger->warning('Error during PhoneTrack geofence POST URL query : '.$e, array('app' => $this->appName));
							}
						}
					}
				}
			}
		}
	}

	private function checkQuota($deviceidToInsert, $userid, $devicename, $sessionname, $nbPointsToInsert=1) {
		$quota = intval($this->config->getAppValue('phonetrack', 'pointQuota'));
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
		$req->execute();
		while ($row = $req->fetch()){
			$nbPoints = intval($row['co']);
		}

		// if there is enough 'space'
		if ($nbPoints + $nbPointsToInsert <= $quota) {
			// if we just reached the quota : notify the user
			if ($nbPoints + $nbPointsToInsert === $quota) {
				$manager = \OC::$server->getNotificationManager();
				$notification = $manager->createNotification();

				$acceptAction = $notification->createAction();
				$acceptAction->setLabel('accept')
					->setLink('/apps/phonetrack', 'GET');

				$declineAction = $notification->createAction();
				$declineAction->setLabel('decline')
					->setLink('/apps/phonetrack', 'GET');

				$notification->setApp('phonetrack')
					->setUser($userid)
					->setDateTime(new \DateTime())
					->setObject('quotareached', $nbPoints)
					->setSubject('quota_reached', [$quota, $devicename, $sessionname])
					->addAction($acceptAction)
					->addAction($declineAction)
					;

				$manager->notify($notification);
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
				$req->execute();
				while ($row = $req->fetch()){
					$count = $row['co'];
				}

				// delete what we can
				if ($count >= $nbExceedingPoints) {
					$nbToDelete = $nbExceedingPoints;
				}
				else {
					$nbToDelete = $count;
				}
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
					}
					else {
						$sqldel = '
							 DELETE FROM *PREFIX*phonetrack_points
							 WHERE deviceid='.$this->db_quote_escape_string($deviceidToInsert).'
							 ORDER BY timestamp ASC LIMIT '.$nbToDelete.' ;';
					}
					$req = $this->dbconnection->prepare($sqldel);
					$req->execute();
					$req->closeCursor();
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
					$req->execute();
					$pids = [];
					while ($row = $req->fetch()){
						array_push($pids, $row['id']);
					}
					$req->closeCursor();

					foreach ($pids as $pid) {
						$sqldel = '
							DELETE FROM *PREFIX*phonetrack_points
							WHERE id='.$this->db_quote_escape_string($pid).' ;';
						$req = $this->dbconnection->prepare($sqldel);
						$req->execute();
						$req->closeCursor();
					}
				}
				else {
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
	 * @NoAdminRequired
	 */
	public function addPoint($token, $devicename, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, $useragent, $speed, $bearing) {
		$done = 0;
		$dbid = null;
		$dbdevid = null;
		if ($token !== '' and $devicename !== '') {
			$logres = $this->logPost($token, $devicename, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, $useragent, $speed, $bearing);
			if ($logres['done'] === 1) {
				$sqlchk = '
					SELECT id
					FROM *PREFIX*phonetrack_devices
					WHERE sessionid='.$this->db_quote_escape_string($token).'
						  AND name='.$this->db_quote_escape_string($devicename).' ;';
				$req = $this->dbconnection->prepare($sqlchk);
				$req->execute();
				while ($row = $req->fetch()){
					$dbdevid = $row['id'];
					break;
				}
				$req->closeCursor();

				// if it's reserved and a device token was given
				if ($dbdevid === null) {
					$sqlchk = '
						SELECT id
						FROM *PREFIX*phonetrack_devices
						WHERE sessionid='.$this->db_quote_escape_string($token).'
							  AND nametoken='.$this->db_quote_escape_string($devicename).' ;';
					$req = $this->dbconnection->prepare($sqlchk);
					$req->execute();
					while ($row = $req->fetch()){
						$dbdevid = $row['id'];
						break;
					}
					$req->closeCursor();
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
					$req->execute();
					while ($row = $req->fetch()){
						$dbid = $row['maxid'];
						break;
					}
					$req->closeCursor();
					$done = 1;
				}
				else {
					$done = 4;
				}
			}
			else {
				// logpost didn't work
				$done = 3;
				// because of quota
				if ($logres['done'] === 2) {
					$done = 5;
				}
			}
		}
		else {
			$done = 2;
		}

		$response = new DataResponse(
			[
				'done'=>$done,
				'pointid'=>$dbid,
				'deviceid'=>$dbdevid
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @return array;
	 *
	 **/
	public function logPost($token, $devicename, $lat, $lon, $alt, ?int $timestamp = null, $acc, $bat, $sat, $useragent,
							$speed = null, $bearing = null, ?string $datetime = null) {
		$res = ['done'=>0, 'friends'=>[]];
		// TODO insert speed and bearing in m/s and degrees
		if (!is_null($devicename) && $devicename !== '' &&
			!is_null($token) && $token !== '' &&
			!is_null($lat) && $lat !== '' && is_numeric($lat) &&
			!is_null($lon) && $lon !== '' && is_numeric($lon) &&
			(!is_null($timestamp) || !is_null($datetime))
		) {
			// check if session exists
			$sqlchk = '
				SELECT `name`, `user`, `public`, `locked`
				FROM `*PREFIX*phonetrack_sessions`
				WHERE `token`=?
			';
			$req = $this->dbconnection->prepare($sqlchk);
			$req->execute([$token]);
			$dbname = null;
			$userid = null;
			$locked = null;
			$isPublicSession = null;
			while ($row = $req->fetch()){
				$dbname = $row['name'];
				$userid = $row['user'];
				$locked = (intval($row['locked']) === 1);
				$isPublicSession = (bool)$row['public'];
				break;
			}
			$req->closeCursor();

			// is it a sharetoken?
			if ($dbname === null) {
				$dbtoken = null;
				// get real token from sharetoken
				$sqlget = '
					SELECT sessionid
					FROM *PREFIX*phonetrack_shares
					WHERE sharetoken='.$this->db_quote_escape_string($token).';';
				$req = $this->dbconnection->prepare($sqlget);
				$req->execute();
				while ($row = $req->fetch()){
					$dbtoken = $row['sessionid'];
				}
				$req->closeCursor();
				if ($dbtoken !== null) {
					$token = $dbtoken;
					// get session info
					$sqlchk = '
						SELECT `name`, `user`, `public`, `locked`
						FROM `*PREFIX*phonetrack_sessions`
						WHERE `token`=?
					';
					$req = $this->dbconnection->prepare($sqlchk);
					$req->execute([$token]);
					$dbname = null;
					$userid = null;
					$locked = null;
					$isPublicSession = null;
					while ($row = $req->fetch()){
						$dbname = $row['name'];
						$userid = $row['user'];
						$locked = (intval($row['locked']) === 1);
						$isPublicSession = (bool)$row['public'];
						break;
					}
					$req->closeCursor();
				}
			}

			if ($dbname !== null) {
				if (!$locked) {
					$humanReadableDeviceName = $devicename;
					// check if this devicename is reserved or exists
					$dbdevicename = null;
					$dbdevicealias = null;
					$dbdevicenametoken = null;
					$deviceidToInsert = null;
					$sqlgetres = '
						SELECT id, name, nametoken, alias
						FROM *PREFIX*phonetrack_devices
						WHERE sessionid='.$this->db_quote_escape_string($token).'
							  AND name='.$this->db_quote_escape_string($devicename).' ;';
					$req = $this->dbconnection->prepare($sqlgetres);
					$req->execute();
					while ($row = $req->fetch()){
						$dbdeviceid = $row['id'];
						$dbdevicename = $row['name'];
						$dbdevicealias = $row['alias'];
						$dbdevicenametoken = $row['nametoken'];
					}
					$req->closeCursor();

					// the device exists
					if ($dbdevicename !== null) {
						if (!empty($dbdevicealias)) {
							$humanReadableDeviceName = $dbdevicealias.' ('.$dbdevicename.')';
						}
						else {
							$humanReadableDeviceName = $dbdevicename;
						}
						// this device id reserved => logging refused if the request does not come from correct user
						if ($dbdevicenametoken !== null and $dbdevicenametoken !== '') {
							// here, we check if we're logged in as the session owner
							if ($this->userId !== '' and $this->userId !== null and $userid === $this->userId) {
								// if so, accept to (manually) log with name and not nametoken
								$deviceidToInsert = $dbdeviceid;
							}
							else {
								return;
							}
						}
						else {
							$deviceidToInsert = $dbdeviceid;
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
						while ($row = $req->fetch()){
							$dbdeviceid = $row['id'];
							$dbdevicename = $row['name'];
							$dbdevicealias = $row['alias'];
							$dbdevicenametoken = $row['nametoken'];
						}
						$req->closeCursor();

						// there is a device which has this nametoken => we log for this device
						if ($dbdevicenametoken !== null and $dbdevicenametoken !== '') {
							$deviceidToInsert = $dbdeviceid;
							if (!empty($dbdevicealias)) {
								$humanReadableDeviceName = $dbdevicealias.' ('.$dbdevicename.')';
							}
							else {
								$humanReadableDeviceName = $dbdevicename;
							}
						}
						else {
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
							$req->closeCursor();

							// get the newly created device id
							$sqlgetdid = '
								SELECT id
								FROM *PREFIX*phonetrack_devices
								WHERE sessionid='.$this->db_quote_escape_string($token).'
									  AND name='.$this->db_quote_escape_string($devicename).' ;';
							$req = $this->dbconnection->prepare($sqlgetdid);
							$req->execute();
							while ($row = $req->fetch()){
								$deviceidToInsert = $row['id'];
							}
							$req->closeCursor();
						}
					}

					if (!is_null($timestamp)) {
						// correct timestamp if needed
						$time = $timestamp;
						if (is_numeric($time)) {
							$time = floatval($time);
							if ($time > 10000000000.0) {
								$time = $time / 1000;
							}
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
								return $res;
							}
						}
					}

					if (is_numeric($acc)) {
						$acc = sprintf('%.2f', (float)$acc);
					}

					// geofences, proximity alerts, quota
					$this->checkGeoFences(floatval($lat), floatval($lon), $deviceidToInsert, $userid, $humanReadableDeviceName, $dbname, $token);
					$this->checkProxims(floatval($lat), floatval($lon), $deviceidToInsert, $userid, $humanReadableDeviceName, $dbname, $token);
					$quotaClearance = $this->checkQuota($deviceidToInsert, $userid, $humanReadableDeviceName, $dbname);

					if (!$quotaClearance) {
						$res['done'] = 2;
						return $res;
					}

					$lat        = $this->db_quote_escape_string(number_format($lat, 8, '.', ''));
					$lon        = $this->db_quote_escape_string(number_format($lon, 8, '.', ''));
					$time       = $this->db_quote_escape_string(number_format($time, 0, '.', ''));
					$alt        = is_numeric($alt) ? $this->db_quote_escape_string(number_format($alt, 2, '.', '')) : 'NULL';
					$acc        = is_numeric($acc) ? $this->db_quote_escape_string(number_format($acc, 2, '.', '')) : 'NULL';
					$bat        = is_numeric($bat) ? $this->db_quote_escape_string(number_format($bat, 2, '.', '')) : 'NULL';
					$sat        = is_numeric($sat) ? $this->db_quote_escape_string(number_format($sat, 0, '.', '')) : 'NULL';
					$speed      = is_numeric($speed) ? $this->db_quote_escape_string(number_format($speed, 3, '.', '')) : 'NULL';
					$bearing    = is_numeric($bearing) ? $this->db_quote_escape_string(number_format($bearing, 2, '.', '')) : 'NULL';

					$sql = '
						INSERT INTO *PREFIX*phonetrack_points
						(deviceid, lat, lon, timestamp, accuracy, satellites, altitude, batterylevel, useragent, speed, bearing)
						VALUES ('.
							$this->db_quote_escape_string($deviceidToInsert).','.
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
					$req->closeCursor();

					$res['done'] = 1;

					if ($isPublicSession && $useragent === self::LOG_OWNTRACKS) {
						$friendSQL  = '
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
						$friendReq->execute([$token]);
						$result = [];
						while ($row = $friendReq->fetch()){
							// we don't store the tid, so we fall back to the last
							// two chars of the nametoken
							// TODO feels far from unique, currently 32 ids max
							$tid = substr($row['nametoken'],-2);
							$location = [
								'_type'=>'location',

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
							$result[] = $location;
							$result[] = [
								'_type'=>'card',
								'tid' => $tid,
								//'face'=>'/9j/4AAQSkZJR...', // TODO lookup avatar?
								'name' => $row['name'],
							];
						}
						$res['friends'] = $result;
					}
				}
				else {
					$res['done'] = 3;
				}
			}
		}
		return $res;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @return array;
	 *
	 **/
	public function logPostMultiple($token, $devicename, $points) {
		$res = ['done'=>0, 'friends'=>[]];
		if (!is_null($devicename) and $devicename !== '' and
			!is_null($token) and $token !== '') {
			// check if session exists
			$sqlchk = '
				SELECT `name`, `user`, `public`, `locked`
				FROM `*PREFIX*phonetrack_sessions`
				WHERE `token`=?
			';
			$req = $this->dbconnection->prepare($sqlchk);
			$req->execute([$token]);
			$dbname = null;
			$userid = null;
			$locked = null;
			$isPublicSession = null;
			while ($row = $req->fetch()){
				$dbname = $row['name'];
				$userid = $row['user'];
				$locked = (intval($row['locked']) === 1);
				$isPublicSession = (bool)$row['public'];
				break;
			}
			$req->closeCursor();

			// is it a sharetoken?
			if ($dbname === null) {
				$dbtoken = null;
				// get real token from sharetoken
				$sqlget = '
					SELECT sessionid
					FROM *PREFIX*phonetrack_shares
					WHERE sharetoken='.$this->db_quote_escape_string($token).';';
				$req = $this->dbconnection->prepare($sqlget);
				$req->execute();
				while ($row = $req->fetch()){
					$dbtoken = $row['sessionid'];
				}
				$req->closeCursor();
				if ($dbtoken !== null) {
					$token = $dbtoken;
					// get session info
					$sqlchk = '
						SELECT `name`, `user`, `public`, `locked`
						FROM `*PREFIX*phonetrack_sessions`
						WHERE `token`=?
					';
					$req = $this->dbconnection->prepare($sqlchk);
					$req->execute([$token]);
					$dbname = null;
					$userid = null;
					$locked = null;
					$isPublicSession = null;
					while ($row = $req->fetch()){
						$dbname = $row['name'];
						$userid = $row['user'];
						$locked = (intval($row['locked']) === 1);
						$isPublicSession = (bool)$row['public'];
						break;
					}
					$req->closeCursor();
				}
			}

			if ($dbname !== null) {
				if (!$locked) {
					$humanReadableDeviceName = $devicename;
					// check if this devicename is reserved or exists
					$dbdevicename = null;
					$dbdevicealias = null;
					$dbdevicenametoken = null;
					$deviceidToInsert = null;
					$sqlgetres = '
						SELECT id, name, nametoken, alias
						FROM *PREFIX*phonetrack_devices
						WHERE sessionid='.$this->db_quote_escape_string($token).'
							  AND name='.$this->db_quote_escape_string($devicename).' ;';
					$req = $this->dbconnection->prepare($sqlgetres);
					$req->execute();
					while ($row = $req->fetch()){
						$dbdeviceid = $row['id'];
						$dbdevicename = $row['name'];
						$dbdevicealias = $row['alias'];
						$dbdevicenametoken = $row['nametoken'];
					}
					$req->closeCursor();

					// the device exists
					if ($dbdevicename !== null) {
						if (!empty($dbdevicealias)) {
							$humanReadableDeviceName = $dbdevicealias.' ('.$dbdevicename.')';
						}
						else {
							$humanReadableDeviceName = $dbdevicename;
						}
						// this device id reserved => logging refused if the request does not come from correct user
						if ($dbdevicenametoken !== null and $dbdevicenametoken !== '') {
							// here, we check if we're logged in as the session owner
							if ($this->userId !== '' and $this->userId !== null and $userid === $this->userId) {
								// if so, accept to (manually) log with name and not nametoken
								$deviceidToInsert = $dbdeviceid;
							}
							else {
								return;
							}
						}
						else {
							$deviceidToInsert = $dbdeviceid;
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
						while ($row = $req->fetch()){
							$dbdeviceid = $row['id'];
							$dbdevicename = $row['name'];
							$dbdevicealias = $row['alias'];
							$dbdevicenametoken = $row['nametoken'];
						}
						$req->closeCursor();

						// there is a device which has this nametoken => we log for this device
						if ($dbdevicenametoken !== null and $dbdevicenametoken !== '') {
							$deviceidToInsert = $dbdeviceid;
							if (!empty($dbdevicealias)) {
								$humanReadableDeviceName = $dbdevicealias.' ('.$dbdevicename.')';
							}
							else {
								$humanReadableDeviceName = $dbdevicename;
							}
						}
						else {
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
							$req->closeCursor();

							// get the newly created device id
							$sqlgetdid = '
								SELECT id
								FROM *PREFIX*phonetrack_devices
								WHERE sessionid='.$this->db_quote_escape_string($token).'
									  AND name='.$this->db_quote_escape_string($devicename).' ;';
							$req = $this->dbconnection->prepare($sqlgetdid);
							$req->execute();
							while ($row = $req->fetch()){
								$deviceidToInsert = $row['id'];
							}
							$req->closeCursor();
						}
					}

					// check quota once before inserting anything
					// it will delete points to make room if needed
					$quotaClearance = $this->checkQuota($deviceidToInsert, $userid, $humanReadableDeviceName, $dbname, count($points));

					if (!$quotaClearance) {
						$res['done'] = 2;
						return $res;
					}

					// check geofences and proxims only once with the last point
					if (count($points) > 0) {
						$lastPointToInsert = $points[count($points) - 1];
						$lat = $lastPointToInsert[0];
						$lon = $lastPointToInsert[1];
						$this->checkGeoFences(floatval($lat), floatval($lon), $deviceidToInsert, $userid, $humanReadableDeviceName, $dbname, $token);
						$this->checkProxims(floatval($lat), floatval($lon), $deviceidToInsert, $userid, $humanReadableDeviceName, $dbname, $token);
					}

					$valuesToInsert = [];
					$nbToInsert = 0;
					foreach ($points as $point) {
						$lat        = $point[0];
						$lon        = $point[1];
						$timestamp  = $point[2];
						$alt        = $point[3];
						$acc        = $point[4];
						$bat        = $point[5];
						$sat        = $point[6];
						$useragent  = $point[7];
						$speed      = $point[8];
						$bearing    = $point[9];

						if (!is_null($devicename) and $devicename !== '' and
							!is_null($token) and $token !== '' and
							!is_null($lat) and $lat !== '' and is_numeric($lat) and
							!is_null($lon) and $lon !== '' and is_numeric($lon) and
							!is_null($timestamp) and $timestamp !== '' and is_numeric($timestamp)
						) {
							// correct timestamp if needed
							$time = $timestamp;
							if (is_numeric($time)) {
								$time = floatval($time);
								if ($time > 10000000000.0) {
									$time = $time / 1000;
								}
							}

							$lat        = $this->db_quote_escape_string(number_format($lat, 8, '.', ''));
							$lon        = $this->db_quote_escape_string(number_format($lon, 8, '.', ''));
							$time       = $this->db_quote_escape_string(number_format($time, 0, '.', ''));
							$alt        = is_numeric($alt) ? $this->db_quote_escape_string(number_format($alt, 2, '.', '')) : 'NULL';
							$acc        = is_numeric($acc) ? $this->db_quote_escape_string(number_format($acc, 2, '.', '')) : 'NULL';
							$bat        = is_numeric($bat) ? $this->db_quote_escape_string(number_format($bat, 2, '.', '')) : 'NULL';
							$sat        = is_numeric($sat) ? $this->db_quote_escape_string(number_format($sat, 0, '.', '')) : 'NULL';
							$speed      = is_numeric($speed) ? $this->db_quote_escape_string(number_format($speed, 3, '.', '')) : 'NULL';
							$bearing    = is_numeric($bearing) ? $this->db_quote_escape_string(number_format($bearing, 2, '.', '')) : 'NULL';

							$value = '('.
									  $this->db_quote_escape_string($deviceidToInsert).','.
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
							array_push($valuesToInsert, $value);
							$nbToInsert++;

							// insert by bunch of 50 points
							if ($nbToInsert%50 === 0) {
								$sql = '
									INSERT INTO *PREFIX*phonetrack_points
									(deviceid, lat, lon, timestamp, accuracy, satellites, altitude, batterylevel, useragent, speed, bearing)
									VALUES '.implode(', ', $valuesToInsert).' ;';
								$req = $this->dbconnection->prepare($sql);
								$req->execute();
								$req->closeCursor();
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
						$req->closeCursor();
					}

					$res['done'] = 1;

					if ($isPublicSession && $useragent === self::LOG_OWNTRACKS) {
						$friendSQL  = '
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
						$friendReq->execute([$token]);
						$result = [];
						while ($row = $friendReq->fetch()){
							// we don't store the tid, so we fall back to the last
							// two chars of the nametoken
							// TODO feels far from unique, currently 32 ids max
							$tid = substr($row['nametoken'],-2);
							$location = [
								'_type'=>'location',

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
							$result[] = $location;
							$result[] = [
								'_type'=>'card',
								'tid' => $tid,
								//'face'=>'/9j/4AAQSkZJR...', // TODO lookup avatar?
								'name' => $row['name'],
							];
						}
						$res['friends'] = $result;
					}
				}
				else {
					$res['done'] = 3;
				}
			}
		}
		return $res;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 **/
	public function logGet($token, $devicename, $lat, $lon, ?int $timestamp = null, $bat, $sat, $acc, $alt,
						   $speed=null, $bearing=null, ?string $datetime = null) {
		$dname = $this->chooseDeviceName($devicename, null);
		return $this->logPost($token, $dname, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, 'unknown GET logger', $speed, $bearing, $datetime);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 **/
	public function logLocusmapGet($token, $devicename, $lat, $lon, $time, $battery, $acc, $alt, $speed, $bearing) {
		$dname = $this->chooseDeviceName($devicename, null);
		$this->logPost($token, $dname, $lat, $lon, $alt, $time, $acc, $battery, null, 'LocusMap', $speed, $bearing);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 **/
	public function logLocusmapPost($token, $devicename, $lat, $lon, $time, $battery, $acc, $alt, $speed, $bearing) {
		$dname = $this->chooseDeviceName($devicename, null);
		$this->logPost($token, $dname, $lat, $lon, $alt, $time, $acc, $battery, null, 'LocusMap', $speed, $bearing);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 **/
	public function logOsmand($token, $devicename, $lat, $lon, $timestamp, $bat, $sat, $acc, $alt, $speed=null, $bearing=null) {
		$dname = $this->chooseDeviceName($devicename, null);
		$this->logPost($token, $dname, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, 'OsmAnd', $speed, $bearing);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 **/
	public function logGpsloggerGet($token, $devicename, $lat, $lon, $timestamp, $bat, $sat, $acc, $alt, $speed=null, $bearing=null) {
		$dname = $this->chooseDeviceName($devicename, null);
		$this->logPost($token, $dname, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, 'GpsLogger GET', $speed, $bearing);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 **/
	public function logGpsloggerPost($token, $devicename, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, $speed=null, $bearing=null) {
		$dname = $this->chooseDeviceName($devicename, null);
		$this->logPost($token, $dname, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, 'GpsLogger POST', $speed, $bearing);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * Owntracks IOS
	 *
	 * @param string $token
	 * @param string $devicename
	 * @param string $tid
	 * @param float $lat
	 * @param float $lon
	 * @param float $alt
	 * @param int $tst
	 * @param float $acc
	 * @param float $batt
	 *
	 * @return array;
	 **/
	public function logOwntracks($token, $devicename = '', $tid, $lat, $lon, $alt, $tst, $acc, $batt) {
		$dname = $this->chooseDeviceName($devicename, $tid);
		$res = $this->logPost($token, $dname, $lat, $lon, $alt, $tst, $acc, $batt, null, self::LOG_OWNTRACKS);
		return $res['friends'];
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * Overland Ios
	 **/
	public function logOverland($token, $devicename, $locations) {
		foreach ($locations as $loc) {
			if ($loc['type'] === 'Feature' && $loc['geometry']['type'] === 'Point') {
				$dname = $this->chooseDeviceName($loc['properties']['device_id'] ?? '', $devicename);
				$lat = $loc['geometry']['coordinates'][1];
				$lon = $loc['geometry']['coordinates'][0];
				$datetime = new Datetime($loc['properties']['timestamp']);
				$timestamp = $datetime->getTimestamp();
				$alt = $loc['properties']['altitude'];
				$acc = $loc['properties']['horizontal_accuracy'];
				$bat = floatval($loc['properties']['battery_level']) * 100;
				$speed = $loc['properties']['speed'];
				$bearing = null;
				$sat = null;
				$alt = $loc['properties']['altitude'];
				$this->logPost($token, $dname, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat,'Overland', $speed, $bearing);
			}
		}
		return array('result' => 'ok');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * Ulogger Android
	 **/
	public function logUlogger($token, $devicename, $trackid, $lat, $lon, $time, $accuracy, $altitude,
							   $pass, $user, $action, $speed=null, $bearing=null) {
		if ($action === 'addpos') {
			$dname = $this->chooseDeviceName($devicename, null);
			$this->logPost($token, $dname, $lat, $lon, $altitude, $time, $accuracy, null, null,'Ulogger', $speed, $bearing);
		}
		return array("error" => false, "trackid" => 1);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * traccar Android/IOS
	 **/
	public function logTraccar($token, $devicename='', $id, $lat, $lon, $timestamp, $accuracy, $altitude, $batt, $speed, $bearing) {
		$dname = $this->chooseDeviceName($devicename, $id);
		$speedp = $speed;
		if (is_numeric($speed)) {
			// according to traccar sources, speed is converted in knots...
			// convert back to meter/s
			$speedp = floatval($speed) / 1.943844;
		}
		$this->logPost($token, $dname, $lat, $lon, $altitude, $timestamp, $accuracy, $batt, null, 'Traccar', $speedp, $bearing);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * any Opengts-compliant app
	 **/
	public function logOpengts($token, $devicename='', $id, $dev, $acct, $alt, $batt, $gprmc) {
		$dname = $this->chooseDeviceName($devicename, $id);
		$gprmca = explode(',', $gprmc);
		$time = sprintf("%06d", (int)$gprmca[1]);
		$date = sprintf("%06d", (int)$gprmca[9]);
		$datetime = \DateTime::createFromFormat('dmy His', $date.' '.$time);
		$timestamp = $datetime->getTimestamp();
		$lat = DMStoDEC(sprintf('%010.4f', (float)$gprmca[3]), 'latitude');
		if ($gprmca[4] === 'S') {
			$lat = - $lat;
		}
		$lon = DMStoDEC(sprintf('%010.4f', (float)$gprmca[5]), 'longitude');
		if ($gprmca[6] === 'W') {
			$lon = - $lon;
		}
		$this->logPost($token, $dname, $lat, $lon, $alt, $timestamp, null, $batt, null, 'OpenGTS client');
		return true;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * in case there is a POST request (like celltrackGTS does)
	 **/
	public function logOpengtsPost($token, $devicename, $id, $dev, $acct, $alt, $batt, $gprmc) {
		return [];
	}

}
