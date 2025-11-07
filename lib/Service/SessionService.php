<?php

/**
 * Nextcloud - phonetrack
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2019
 */

namespace OCA\PhoneTrack\Service;

use DateTime;
use OCA\PhoneTrack\AppInfo\Application;
use OCA\PhoneTrack\Db\DeviceMapper;
use OCA\PhoneTrack\Db\Session;
use OCA\PhoneTrack\Db\SessionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;

use OCP\IConfig;
use OCP\IDBConnection;

use OCP\IUserManager;

class SessionService {

	/**
	 * @var IDBConnection
	 */
	private $db;
	/**
	 * @var IRootFolder
	 */
	private $root;
	/**
	 * @var DeviceMapper
	 */
	private $deviceMapper;
	/**
	 * @var string
	 */
	private $appVersion;
	/**
	 * @var SessionMapper
	 */
	private $sessionMapper;
	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var IConfig
	 */
	private $config;

	public function __construct(
		SessionMapper $sessionMapper,
		DeviceMapper $deviceMapper,
		IUserManager $userManager,
		IDBConnection $db,
		IRootFolder $root,
		IConfig $config,
	) {
		$this->sessionMapper = $sessionMapper;
		$this->userManager = $userManager;
		$this->config = $config;
		$this->db = $db;
		$this->root = $root;
		$this->deviceMapper = $deviceMapper;

		$this->appVersion = $config->getAppValue(Application::APP_ID, 'installed_version');
	}

	private function db_quote_escape_string($str) {
		return $this->db->quote($str);
	}

	public function findUsers($id) {
		$userIds = [];
		// get owner with mapper
		$session = $this->sessionMapper->find($id);
		$userIds[] = $session->getUser();

		// get user shares from session token
		$token = $session->getToken();
		$qb = $this->db->getQueryBuilder();
		$qb->select('username')
			->from('phonetrack_shares', 's')
			->where(
				$qb->expr()->eq('sessionid', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			if (!in_array($row['username'], $userIds)) {
				$userIds[] = $row['username'];
			}
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		return $userIds;
	}

	private function getOrCreateExportDir(string $userId): Folder {
		$userFolder = $this->root->getUserFolder($userId);

		$dirPath = $this->config->getUserValue($userId, Application::APP_ID, 'autoexportpath', '/PhoneTrack_export');

		if (!$userFolder->nodeExists($dirPath)) {
			return $userFolder->newFolder($dirPath);
		}

		$dir = $userFolder->get($dirPath);
		if ($dir instanceof Folder && $dir->isCreatable()) {
			return $dir;
		}
		throw new \Exception('Impossible to create export directory');
	}

	private function cronAutoPurge() {
		date_default_timezone_set('UTC');
		foreach (['day' => '1', 'week' => '7', 'month' => '31'] as $s => $nbDays) {
			$now = new DateTime();
			$now->modify('-' . $nbDays . ' day');
			$ts = $now->getTimestamp();

			// get all sessions with this auto purge value
			$sessions = $this->sessionMapper->findByAutoPurge($s);

			$deviceIds = [];
			foreach ($sessions as $session) {
				$sessionDevices = $this->deviceMapper->findBySessionId($session->getToken());
				foreach ($sessionDevices as $device) {
					$deviceIds[] = $device->getId();
				}
			}

			foreach ($deviceIds as $deviceId) {
				$this->deviceMapper->deletePointsOlderThan($deviceId, $ts);
			}
		}
	}

	/**
	 * auto export
	 * triggered by NC cron job
	 *
	 * export sessions
	 */
	public function cronAutoExport() {
		$dtz = ini_get('date.timezone');
		if ($dtz === '') {
			$dtz = 'UTC';
		}
		date_default_timezone_set($dtz);
		$userNames = [];

		// last day
		$now = new DateTime();
		$y = $now->format('Y');
		$m = $now->format('m');
		$d = $now->format('d');

		// get beginning of today
		$dateMaxDay = new DateTime($y . '-' . $m . '-' . $d);
		$maxDayTimestamp = $dateMaxDay->getTimestamp();
		$minDayTimestamp = $maxDayTimestamp - (24 * 60 * 60);

		$dateMaxDay->modify('-1 day');
		$dailySuffix = '_daily_' . $dateMaxDay->format('Y-m-d');

		// last week
		$now = new DateTime();
		while (intval($now->format('N')) !== 1) {
			$now->modify('-1 day');
		}
		$y = $now->format('Y');
		$m = $now->format('m');
		$d = $now->format('d');
		$dateWeekMax = new DateTime($y . '-' . $m . '-' . $d);
		$maxWeekTimestamp = $dateWeekMax->getTimestamp();
		$minWeekTimestamp = $maxWeekTimestamp - (7 * 24 * 60 * 60);
		$dateWeekMin = new DateTime($y . '-' . $m . '-' . $d);
		$dateWeekMin->modify('-7 day');
		$weeklySuffix = '_weekly_' . $dateWeekMin->format('Y-m-d');

		// last month
		$now = new DateTime();
		while (intval($now->format('d')) !== 1) {
			$now->modify('-1 day');
		}
		$y = $now->format('Y');
		$m = $now->format('m');
		$d = $now->format('d');
		$dateMonthMax = new DateTime($y . '-' . $m . '-' . $d);
		$maxMonthTimestamp = $dateMonthMax->getTimestamp();
		$now->modify('-1 day');
		while (intval($now->format('d')) !== 1) {
			$now->modify('-1 day');
		}
		$y = (int)$now->format('Y');
		$m = (int)$now->format('m');
		$d = (int)$now->format('d');
		$dateMonthMin = new DateTime($y . '-' . $m . '-' . $d);
		$minMonthTimestamp = $dateMonthMin->getTimestamp();
		$monthlySuffix = '_monthly_' . $dateMonthMin->format('Y-m');

		$weekFilters = [
			'timestamp' => [
				'min' => $minWeekTimestamp,
				'max' => $maxWeekTimestamp,
			],
		];
		$dayFilters = [
			'timestamp' => [
				'min' => $minDayTimestamp,
				'max' => $maxDayTimestamp,
			],
		];
		$monthFilters = [
			'timestamp' => [
				'min' => $minMonthTimestamp,
				'max' => $maxMonthTimestamp,
			],
		];

		date_default_timezone_set('UTC');

		foreach ($this->userManager->search('') as $u) {
			$userId = $u->getUID();
			$userFolder = $this->root->getUserFolder($userId);

			/** @var Session[] $sessions */
			$sessions = $this->sessionMapper->findByUser($userId);

			foreach ($sessions as $session) {
				$dbname = $session->getName();
				$dbtoken = $session->getToken();
				$dbexportType = $session->getAutoexport();
				// export if autoexport is set
				if ($dbexportType !== 'no') {
					$suffix = $dailySuffix;
					$filters = $dayFilters;
					if ($dbexportType === 'weekly') {
						$suffix = $weeklySuffix;
						$filters = $weekFilters;
					} elseif ($dbexportType === 'monthly') {
						$suffix = $monthlySuffix;
						$filters = $monthFilters;
					}
					$dir = $this->getOrCreateExportDir($userId);
					// check if file already exists
					$exportName = $dbname . $suffix . '.gpx';

					$rel_path = str_replace($userFolder->getPath(), '', $dir->getPath());
					$exportPath = $rel_path . '/' . $exportName;
					if (!$dir->nodeExists($exportName)) {
						$this->export($dbname, $dbtoken, $exportPath, $userId, $filters);
					}
				}
			}
		}
		// we run the auto purge method AFTER the auto export
		// to avoid deleting data before it has been eventually exported
		$this->cronAutoPurge();
	}

	public function export(string $name, string $token, string $target, string $username = '', ?array $filters = null) {
		date_default_timezone_set('UTC');
		$done = false;
		$warning = 0;
		$userFolder = null;
		if ($username !== '') {
			$userFolder = $this->root->getUserFolder($username);
			$userId = $username;
		} else {
			return [false, 0];
		}
		// get options to know if we should export one file per device
		$ofpd = $this->config->getUserValue($userId, Application::APP_ID, 'exportoneperdev', 'false');
		$oneFilePerDevice = ($ofpd === 'true');

		$path = $target;
		$cleanPath = str_replace(['../', '..\\'], '', $path);

		if ($userFolder !== null) {
			$file = null;
			$filePossible = false;
			$dirPath = dirname($cleanPath);
			$newFileName = basename($cleanPath);
			if ($oneFilePerDevice) {
				if ($userFolder->nodeExists($dirPath)) {
					$dir = $userFolder->get($dirPath);
					if ($dir instanceof Folder && $dir->isCreatable()) {
						$filePossible = true;
					}
				}
			} else {
				if ($userFolder->nodeExists($cleanPath)) {
					$dir = $userFolder->get($dirPath);
					$file = $userFolder->get($cleanPath);
					if ($file instanceof File && $file->isUpdateable()) {
						$filePossible = true;
					}
				} else {
					if ($userFolder->nodeExists($dirPath)) {
						$dir = $userFolder->get($dirPath);
						if ($dir instanceof Folder && $dir->isCreatable()) {
							$filePossible = true;
						}
					}
				}
			}

			if ($filePossible) {
				// check if session exists
				$sessionToken = null;
				try {
					$dbSession = $this->sessionMapper->findByToken($token);
					$sessionToken = $token;
				} catch (DoesNotExistException $e) {
				}

				// if not, check it is a shared session
				if ($sessionToken === null) {
					$sessionToken = $this->sessionMapper->isSharedWith($token, $userId);
				}

				// session exists
				if ($sessionToken !== null) {
					// indexed by track name
					$coords = [];
					// get list of all devices which have points in this session (without filters)
					$devices = [];
					$sqldev = '
						SELECT dev.id AS id, dev.name AS name
						FROM *PREFIX*phonetrack_devices AS dev, *PREFIX*phonetrack_points AS po
						WHERE dev.sessionid=' . $this->db_quote_escape_string($sessionToken) . ' AND dev.id = po.deviceid GROUP BY dev.id;';
					$req = $this->db->prepare($sqldev);
					$res = $req->execute();
					while ($row = $res->fetch()) {
						$devices[] = [$row['id'], $row['name']];
					}
					$res->closeCursor();

					// get the coords for each device
					$result[$name] = [];

					// get filters
					if ($filters === null) {
						$filters = $this->getCurrentFilters2($userId);
					}

					// check if there are points in this session (with filters)
					$sessionPointNumber = $this->sessionMapper->countPointsPerSession($sessionToken, $filters);
					if ($sessionPointNumber > 0) {
						// check if all devices of this session (not filtered) have points
						if ($this->deviceMapper->countDevicesPerSession($sessionToken) > count($devices)) {
							$warning = 2;
						}
						// one file for the whole session
						if (!$oneFilePerDevice) {
							$gpxHeader = $this->generateGpxHeader($name, count($devices));
							if (!$dir->nodeExists($newFileName)) {
								$dir->newFile($newFileName);
							}
							$file = $dir->get($newFileName);
							$fd = $file->fopen('w');
							fwrite($fd, $gpxHeader);
						}
						foreach ($devices as $d) {
							$devid = $d[0];
							$devname = $d[1];

							// check if there are coords for this device (with filters)
							$nbPoints = $this->deviceMapper->countPointsPerDevice($devid, $filters);
							if ($nbPoints > 0) {
								// generate a file for this device if needed
								if ($oneFilePerDevice) {
									$gpxHeader = $this->generateGpxHeader($name);
									// generate file name for this device
									$devFileName = str_replace(['.gpx', '.GPX'], '_' . $devname . '.gpx', $newFileName);
									if (!$dir->nodeExists($devFileName)) {
										$dir->newFile($devFileName);
									}
									$file = $dir->get($devFileName);
									$fd = $file->fopen('w');
									fwrite($fd, $gpxHeader);
								}

								$this->getAndWriteDevicePoints($devid, $devname, $filters, $fd, $nbPoints);

								if ($oneFilePerDevice) {
									fwrite($fd, '</gpx>');
									fclose($fd);
									$file->touch();
								}
							} else {
								$warning = 2;
							}
						}
						if (!$oneFilePerDevice) {
							fwrite($fd, '</gpx>');
							fclose($fd);
							$file->touch();
						}
					} else {
						$warning = 1;
					}
					$done = true;
				}
			}
		}

		return [$done, $warning];
	}

	public function getCurrentFilters($userId) {
		$filters = null;
		$options = [];
		$keys = $this->config->getUserKeys($userId, Application::APP_ID);
		foreach ($keys as $key) {
			$value = $this->config->getUserValue($userId, Application::APP_ID, $key);
			$options[$key] = $value;
		}
		if (array_key_exists('applyfilters', $options) && $options['applyfilters'] === 'true') {
			$filters = [];
			if (array_key_exists('datemin', $options) && $options['datemin'] !== '') {
				$hourmin = (array_key_exists('hourmin', $options) && $options['hourmin'] !== '') ? (int)$options['hourmin'] : 0;
				$minutemin = (array_key_exists('minutemin', $options) && $options['minutemin'] !== '') ? (int)$options['minutemin'] : 0;
				$secondmin = (array_key_exists('secondmin', $options) && $options['secondmin'] !== '') ? (int)$options['secondmin'] : 0;
				$filters['tsmin'] = ((int)$options['datemin']) + (3600 * $hourmin) + (60 * $minutemin) + $secondmin;
			} else {
				if (array_key_exists('hourmin', $options) && $options['hourmin'] !== ''
					&& array_key_exists('minutemin', $options) && $options['minutemin'] !== ''
					&& array_key_exists('secondmin', $options) && $options['secondmin'] !== ''
				) {
					$dtz = ini_get('date.timezone');
					if ($dtz === '') {
						$dtz = 'UTC';
					}
					date_default_timezone_set($dtz);
					$now = new DateTime();
					$y = $now->format('Y');
					$m = $now->format('m');
					$d = $now->format('d');
					$h = (int)$options['hourmin'];
					$mi = (int)$options['minutemin'];
					$s = (int)$options['secondmin'];
					$dmin = new DateTime($y . '-' . $m . '-' . $d . ' ' . $h . ':' . $mi . ':' . $s);
					$filters['tsmin'] = $dmin->getTimestamp();
				}
			}
			if (array_key_exists('datemax', $options) && $options['datemax'] !== '') {
				$hourmax = (array_key_exists('hourmax', $options) && $options['hourmax'] !== '')   ? (int)$options['hourmax'] : 23;
				$minutemax = (array_key_exists('minutemax', $options) && $options['minutemax'] !== '') ? (int)$options['minutemax'] : 59;
				$secondmax = (array_key_exists('secondmax', $options) && $options['secondmax'] !== '') ? (int)$options['secondmax'] : 59;
				$filters['tsmax'] = ((int)$options['datemax']) + (3600 * $hourmax) + (60 * $minutemax) + $secondmax;
			} else {
				if (array_key_exists('hourmax', $options) && $options['hourmax'] !== ''
					&& array_key_exists('minutemax', $options) && $options['minutemax'] !== ''
					&& array_key_exists('secondmax', $options) && $options['secondmax'] !== ''
				) {
					$dtz = ini_get('date.timezone');
					if ($dtz === '') {
						$dtz = 'UTC';
					}
					date_default_timezone_set($dtz);
					$now = new DateTime();
					$y = $now->format('Y');
					$m = $now->format('m');
					$d = $now->format('d');
					$h = (int)$options['hourmax'];
					$mi = (int)$options['minutemax'];
					$s = (int)$options['secondmax'];
					$dmax = new DateTime($y . '-' . $m . '-' . $d . ' ' . $h . ':' . $mi . ':' . $s);
					$filters['tsmax'] = $dmax->getTimestamp();
				}
			}
			date_default_timezone_set('UTC');
			$lastTS = new DateTime();
			$lastTS = $lastTS->getTimestamp();
			$lastTSset = false;
			if (array_key_exists('lastdays', $options) && $options['lastdays'] !== '') {
				$lastTS = $lastTS - (24 * 3600 * (int)$options['lastdays']);
				$lastTSset = true;
			}
			if (array_key_exists('lasthours', $options) && $options['lasthours'] !== '') {
				$lastTS = $lastTS - (3600 * (int)$options['lasthours']);
				$lastTSset = true;
			}
			if (array_key_exists('lastmins', $options) && $options['lastmins'] !== '') {
				$lastTS = $lastTS - (60 * (int)$options['lastmins']);
				$lastTSset = true;
			}
			if ($lastTSset && (!array_key_exists('tsmin', $filters) || $lastTS > $filters['tsmin'])) {
				$filters['tsmin'] = $lastTS;
			}
			foreach ([
				'elevationmin', 'elevationmax', 'accuracymin', 'accuracymax', 'satellitesmin', 'satellitesmax',
				'batterymin', 'batterymax', 'speedmax', 'speedmin', 'bearingmax', 'bearingmin', 'lastdays',
				'lasthours', 'lastmins',
			] as $k) {
				if (array_key_exists($k, $options) && $options[$k] !== '') {
					$filters[$k] = (int)$options[$k];
				}
			}
		}

		return $filters;
	}

	/**
	 * @param string $userId
	 * @return array|null
	 * @throws \Exception
	 */
	public function getCurrentFilters2(string $userId): ?array {
		$filters = null;
		$options = [];
		$keys = $this->config->getUserKeys($userId, Application::APP_ID);
		foreach ($keys as $key) {
			$value = $this->config->getUserValue($userId, Application::APP_ID, $key);
			$options[$key] = $value;
		}
		if (isset($options['applyfilters']) && $options['applyfilters'] === 'true') {
			$filters = [];
			$filters['timestamp'] = [];
			if (isset($options['datemin']) && $options['datemin'] !== '') {
				$hourmin = (isset($options['hourmin']) && $options['hourmin'] !== '') ? (int)$options['hourmin'] : 0;
				$minutemin = (isset($options['minutemin']) && $options['minutemin'] !== '') ? (int)$options['minutemin'] : 0;
				$secondmin = (isset($options['secondmin']) && $options['secondmin'] !== '') ? (int)$options['secondmin'] : 0;
				$filters['timestamp']['min'] = ((int)$options['datemin']) + (3600 * $hourmin) + (60 * $minutemin) + $secondmin;
			} else {
				if (isset($options['hourmin']) && $options['hourmin'] !== ''
					&& isset($options['minutemin']) && $options['minutemin'] !== ''
					&& isset($options['secondmin']) && $options['secondmin'] !== ''
				) {
					$dtz = ini_get('date.timezone');
					if ($dtz === '') {
						$dtz = 'UTC';
					}
					date_default_timezone_set($dtz);
					$now = new DateTime();
					$y = $now->format('Y');
					$m = $now->format('m');
					$d = $now->format('d');
					$h = (int)$options['hourmin'];
					$mi = (int)$options['minutemin'];
					$s = (int)$options['secondmin'];
					$dmin = new DateTime($y . '-' . $m . '-' . $d . ' ' . $h . ':' . $mi . ':' . $s);
					$filters['timestamp']['min'] = $dmin->getTimestamp();
				}
			}
			if (isset($options['datemax']) && $options['datemax'] !== '') {
				$hourmax = (isset($options['hourmax']) && $options['hourmax'] !== '')   ? (int)$options['hourmax'] : 23;
				$minutemax = (isset($options['minutemax']) && $options['minutemax'] !== '') ? (int)$options['minutemax'] : 59;
				$secondmax = (isset($options['secondmax']) && $options['secondmax'] !== '') ? (int)$options['secondmax'] : 59;
				$filters['timestamp']['max'] = ((int)$options['datemax']) + (3600 * $hourmax) + (60 * $minutemax) + $secondmax;
			} else {
				if (isset($options['hourmax']) && $options['hourmax'] !== ''
					&& isset($options['minutemax']) && $options['minutemax'] !== ''
					&& isset($options['secondmax']) && $options['secondmax'] !== ''
				) {
					$dtz = ini_get('date.timezone');
					if ($dtz === '') {
						$dtz = 'UTC';
					}
					date_default_timezone_set($dtz);
					$now = new DateTime();
					$y = $now->format('Y');
					$m = $now->format('m');
					$d = $now->format('d');
					$h = (int)$options['hourmax'];
					$mi = (int)$options['minutemax'];
					$s = (int)$options['secondmax'];
					$dmax = new DateTime($y . '-' . $m . '-' . $d . ' ' . $h . ':' . $mi . ':' . $s);
					$filters['timestamp']['max'] = $dmax->getTimestamp();
				}
			}
			date_default_timezone_set('UTC');
			$lastTS = new DateTime();
			$lastTS = $lastTS->getTimestamp();
			$lastTSset = false;
			if (isset($options['lastdays']) && $options['lastdays'] !== '') {
				$lastTS = $lastTS - (24 * 3600 * (int)$options['lastdays']);
				$lastTSset = true;
			}
			if (isset($options['lasthours']) && $options['lasthours'] !== '') {
				$lastTS = $lastTS - (3600 * (int)$options['lasthours']);
				$lastTSset = true;
			}
			if (isset($options['lastmins']) && $options['lastmins'] !== '') {
				$lastTS = $lastTS - (60 * (int)$options['lastmins']);
				$lastTSset = true;
			}
			if ($lastTSset && (!isset($filters['timestamp']['min']) || $lastTS > $filters['timestamp']['min'])) {
				$filters['timestamp']['min'] = $lastTS;
			}
			foreach (['accuracy', 'satellites', 'speed', 'bearing'] as $k) {
				$minKey = $k . 'min';
				if (isset($options[$minKey]) && $options[$minKey] !== '') {
					$filters[$k]['min'] = (int)$options[$minKey];
				}
				$maxKey = $k . 'max';
				if (isset($options[$maxKey]) && $options[$maxKey] !== '') {
					$filters[$k]['max'] = (int)$options[$maxKey];
				}
			}
			if (isset($options['elevationmin']) && $options['elevationmin'] !== '') {
				$filters['altitude']['min'] = (int)$options['elevationmin'];
			}
			if (isset($options['elevationmax']) && $options['elevationmax'] !== '') {
				$filters['altitude']['max'] = (int)$options['elevationmax'];
			}
			if (isset($options['batterymin']) && $options['batterymin'] !== '') {
				$filters['batterylevel']['min'] = (int)$options['batterymin'];
			}
			if (isset($options['batterymax']) && $options['batterymax'] !== '') {
				$filters['batterylevel']['max'] = (int)$options['batterymax'];
			}
		}

		return $filters;
	}

	/**
	 * @param string $name
	 * @param int $nbdev
	 * @return string
	 */
	private function generateGpxHeader(string $name, int $nbdev = 0): string {
		date_default_timezone_set('UTC');
		$dt = new DateTime();
		$date = $dt->format('Y-m-d\TH:i:s\Z');
		$gpxText = '<?xml version="1.0" encoding="UTF-8" standalone="no" ?>' . "\n";
		$gpxText .= '<gpx xmlns="http://www.topografix.com/GPX/1/1"' .
			' xmlns:gpxx="http://www.garmin.com/xmlschemas/GpxExtensions/v3"' .
			' xmlns:wptx1="http://www.garmin.com/xmlschemas/WaypointExtension/v1"' .
			' xmlns:gpxtpx="http://www.garmin.com/xmlschemas/TrackPointExtension/v1"' .
			' creator="PhoneTrack Nextcloud app ' .
			$this->appVersion . '" version="1.1"' .
			' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' .
			' xsi:schemaLocation="http://www.topografix.com/GPX/1/1' .
			' http://www.topografix.com/GPX/1/1/gpx.xsd' .
			' http://www.garmin.com/xmlschemas/GpxExtensions/v3' .
			' http://www8.garmin.com/xmlschemas/GpxExtensionsv3.xsd' .
			' http://www.garmin.com/xmlschemas/WaypointExtension/v1' .
			' http://www8.garmin.com/xmlschemas/WaypointExtensionv1.xsd' .
			' http://www.garmin.com/xmlschemas/TrackPointExtension/v1' .
			' http://www.garmin.com/xmlschemas/TrackPointExtensionv1.xsd">' . "\n";
		$gpxText .= '<metadata>' . "\n" . ' <time>' . $date . '</time>' . "\n";
		$gpxText .= ' <name>' . $name . '</name>' . "\n";
		if ($nbdev > 0) {
			$gpxText .= ' <desc>' . $nbdev . ' device' . ($nbdev > 1 ? 's' : '') . '</desc>' . "\n";
		}
		$gpxText .= '</metadata>' . "\n";
		return $gpxText;
	}

	private function countPointsPerDevice($devid, $filterSql) {
		$sqlget = '
			SELECT count(*) AS co
			FROM *PREFIX*phonetrack_points
			WHERE deviceid=' . $this->db_quote_escape_string($devid) . ' ';
		if ($filterSql !== '') {
			$sqlget .= 'AND ' . $filterSql;
		}
		$sqlget .= ' ;';
		$req = $this->db->prepare($sqlget);
		$req->execute();
		$nbPoints = 0;
		while ($row = $req->fetch()) {
			$nbPoints = intval($row['co']);
		}
		return $nbPoints;
	}

	/**
	 * @param int $devid
	 * @param string $devname
	 * @param array|null $filters
	 * @param $fd
	 * @param int $nbPoints
	 * @return int
	 * @throws Exception
	 */
	private function getAndWriteDevicePoints(int $devid, string $devname, ?array $filters, $fd, int $nbPoints): int {
		$done = 0;

		$gpxText = '<trk>' . "\n" . ' <name>' . $devname . '</name>' . "\n";
		$gpxText .= ' <trkseg>' . "\n";
		fwrite($fd, $gpxText);

		$chunkSize = 10000;
		$pointIndex = 0;

		while ($pointIndex < $nbPoints) {
			$gpxText = '';

			$points = $this->deviceMapper->getDevicePoints($devid, $filters, $chunkSize, $pointIndex);

			foreach ($points as $point) {
				$epoch = $point['timestamp'];
				$date = '';
				if (is_numeric($epoch)) {
					$epoch = (int)$epoch;
					$dt = new DateTime('@' . $epoch);
					$date = $dt->format('Y-m-d\TH:i:s\Z');
				}
				$lat = $point['lat'];
				$lon = $point['lon'];
				$alt = $point['altitude'];
				$acc = $point['accuracy'];
				$bat = $point['batterylevel'];
				$ua = $point['useragent'];
				$sat = $point['satellites'];
				$speed = $point['speed'];
				$bearing = $point['bearing'];

				$gpxExtension = '';
				$gpxText .= '  <trkpt lat="' . $lat . '" lon="' . $lon . '">' . "\n";
				$gpxText .= '   <time>' . $date . '</time>' . "\n";
				if (is_numeric($alt)) {
					$gpxText .= '   <ele>' . sprintf('%.2f', floatval($alt)) . '</ele>' . "\n";
				}
				if (is_numeric($speed) && floatval($speed) >= 0) {
					$gpxExtension .= '     <speed>' . sprintf('%.3f', floatval($speed)) . '</speed>' . "\n";
				}
				if (is_numeric($bearing) && floatval($bearing) >= 0 && floatval($bearing) <= 360) {
					$gpxExtension .= '     <course>' . sprintf('%.3f', floatval($bearing)) . '</course>' . "\n";
				}
				if (is_numeric($sat) && intval($sat) >= 0) {
					$gpxText .= '   <sat>' . intval($sat) . '</sat>' . "\n";
				}
				if (is_numeric($acc) && intval($acc) >= 0) {
					$gpxExtension .= '     <accuracy>' . sprintf('%.2f', floatval($acc)) . '</accuracy>' . "\n";
				}
				if (is_numeric($bat) && intval($bat) >= 0) {
					$gpxExtension .= '     <batterylevel>' . sprintf('%.2f', floatval($bat)) . '</batterylevel>' . "\n";
				}
				if ($ua !== '') {
					$gpxExtension .= '     <useragent>' . $ua . '</useragent>' . "\n";
				}
				if ($gpxExtension !== '') {
					$gpxText .= '   <extensions>' . "\n" . $gpxExtension;
					$gpxText .= '   </extensions>' . "\n";
				}
				$gpxText .= '  </trkpt>' . "\n";
			}
			// write the chunk !
			fwrite($fd, $gpxText);
			$pointIndex = $pointIndex + $chunkSize;
			//$this->logger->info('EXPORT MEM USAGE '.memory_get_usage(), ['app' => $this->appName]);
		}
		$gpxText = ' </trkseg>' . "\n";
		$gpxText .= '</trk>' . "\n";
		fwrite($fd, $gpxText);

		return $done;
	}

}
