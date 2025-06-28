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
use OCA\PhoneTrack\Activity\ActivityManager;
use OCA\PhoneTrack\AppInfo\Application;
use OCA\PhoneTrack\Db\Session;
use OCA\PhoneTrack\Db\SessionMapper;
use OCA\PhoneTrack\Service\SessionService;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;

use OCP\AppFramework\Http\Attribute\PublicPage;

use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;

use OCP\IRequest;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use XMLParser;

function distance(float $lat1, float $long1, float $lat2, float $long2): float {
	if ($lat1 === $lat2 && $long1 === $long2) {
		return 0;
	}

	// Convert latitude and longitude to spherical coordinates in radians
	$degrees_to_radians = pi() / 180.0;

	// phi = 90 - latitude
	$phi1 = (90.0 - $lat1) * $degrees_to_radians;
	$phi2 = (90.0 - $lat2) * $degrees_to_radians;

	// theta = longitude
	$theta1 = $long1 * $degrees_to_radians;
	$theta2 = $long2 * $degrees_to_radians;

	$cos = (sin($phi1) * sin($phi2) * cos($theta1 - $theta2) +
		   cos($phi1) * cos($phi2));
	// why are some cosinuses > than 1?
	if ($cos > 1.0) {
		$cos = 1.0;
	}
	$arc = acos($cos);

	// Remember to multiply arc by the radius of the earth in your favorite set of units to get length
	return $arc * 6371000.0;
}

class PageController extends Controller {

	private string $dbdblquotes;
	private $currentXmlTag;
	private $importToken;
	private $importDevName;
	private $currentPoint;
	private $currentPointList;
	private $trackIndex;
	private $pointIndex;

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IUserManager $userManager,
		private LoggerInterface $logger,
		private IL10N $trans,
		private ActivityManager $activityManager,
		private SessionMapper $sessionMapper,
		private SessionService $sessionService,
		private IDBConnection $dbConnection,
		private IRootFolder $root,
		private IAppManager $appManager,
		private IInitialState $initialStateService,
		private IAppConfig $appConfig,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
		$dbType = $config->getSystemValue('dbtype');
		if ($dbType === 'pgsql') {
			$this->dbdblquotes = '"';
		} else {
			$this->dbdblquotes = '';
		}
	}

	/*
	 * quote and choose string escape function depending on database used
	 */
	private function db_quote_escape_string($str) {
		return $this->dbConnection->quote($str);
	}

	private function getUserTileServers($type) {
		$tss = [];
		// custom tile servers management
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('servername', 'type', 'url', 'layers', 'token',
			'version', 'format', 'opacity', 'transparent',
			'minzoom', 'maxzoom', 'attribution')
			->from('phonetrack_tileserver', 'ts')
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($this->userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('type', $qb->createNamedParameter($type, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			$tss[$row['servername']] = [];
			foreach (['servername', 'type', 'url', 'token', 'layers', 'version', 'format',
				'opacity', 'transparent', 'minzoom', 'maxzoom', 'attribution'] as $field) {
				$tss[$row['servername']][$field] = $row[$field];
			}
		}
		$req->closeCursor();
		return $tss;
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function indexVue() {
		$settings = $this->getOptionsValues();
		$adminMaptilerApiKey = $this->appConfig->getValueString(Application::APP_ID, 'maptiler_api_key', Application::DEFAULT_MAPTILER_API_KEY) ?: Application::DEFAULT_MAPTILER_API_KEY;
		$maptilerApiKey = $this->config->getUserValue($this->userId, Application::APP_ID, 'maptiler_api_key') ?: $adminMaptilerApiKey;
		$settings['maptiler_api_key'] = $maptilerApiKey;
		$settings['proxy_osm'] = false;

		$sessions = $this->getSessions2();

		$state = [
			'sessions' => count($sessions) === 0 ? new \stdClass() : $sessions,
			'settings' => $settings,
		];
		$this->initialStateService->provideInitialState(
			'phonetrack-state',
			$state
		);
		$response = new TemplateResponse(Application::APP_ID, 'mainVue');
		$csp = new ContentSecurityPolicy();
		$this->addPageCsp($csp);
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	private function getOptionsValues() {
		$ov = [];
		$keys = $this->config->getUserKeys($this->userId, Application::APP_ID);
		foreach ($keys as $key) {
			$value = $this->config->getUserValue($this->userId, Application::APP_ID, $key);
			$ov[$key] = $value;
		}
		return $ov;
	}

	public function addPageCsp(ContentSecurityPolicy $csp, array $extraTileServers = []): void {
		$csp
			// raster tiles
			->addAllowedConnectDomain('https://*.openstreetmap.org')
			->addAllowedConnectDomain('https://server.arcgisonline.com')
			->addAllowedConnectDomain('https://*.tile.thunderforest.com')
			->addAllowedConnectDomain('https://stamen-tiles.a.ssl.fastly.net')
			->addAllowedConnectDomain('https://tiles.stadiamaps.com')
			// vector tiles
			->addAllowedConnectDomain('https://api.maptiler.com')
			// for https://api.maptiler.com/resources/logo.svg
			->addAllowedImageDomain('https://api.maptiler.com')
			// nominatim (not needed, we proxy requests through the server)
			//->addAllowedConnectDomain('https://nominatim.openstreetmap.org')
			// maplibre-gl
			->addAllowedWorkerSrcDomain('blob:');

		foreach ($extraTileServers as $ts) {
			$type = $ts->getType();
			$url = $ts->getUrl();
			$domain = parse_url($url, PHP_URL_HOST);
			$scheme = parse_url($url, PHP_URL_SCHEME);
			// extra raster tile servers
			if ($type === Application::TILE_SERVER_RASTER) {
				$domain = str_replace('{s}', '*', $domain);
				if ($scheme === 'http') {
					$csp->addAllowedConnectDomain('http://' . $domain);
				} else {
					$csp->addAllowedConnectDomain('https://' . $domain);
				}
			} else {
				// extra vector tile servers
				if ($scheme === 'http') {
					$csp->addAllowedConnectDomain('http://' . $domain);
				} else {
					$csp->addAllowedConnectDomain('https://' . $domain);
				}
			}
		};
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index() {
		//date_default_timezone_set('Europe/Paris');
		//phpinfo();
		$tss = $this->getUserTileServers('tile');
		$mbtss = $this->getUserTileServers('mapboxtile');
		$oss = $this->getUserTileServers('overlay');
		$tssw = $this->getUserTileServers('tilewms');
		$ossw = $this->getUserTileServers('overlaywms');

		// PARAMS to view
		require_once('tileservers.php');
		$params = [
			'username' => $this->userId,
			'basetileservers' => $baseTileServers,
			'usertileservers' => $tss,
			'usermapboxtileservers' => $mbtss,
			'useroverlayservers' => $oss,
			'usertileserverswms' => $tssw,
			'useroverlayserverswms' => $ossw,
			'publicsessionname' => '',
			'lastposonly' => '',
			'sharefilters' => '',
			'filtersBookmarks' => $this->getFiltersBookmarks(),
			'phonetrack_version' => $this->appManager->getAppVersion(Application::APP_ID)
		];
		$response = new TemplateResponse(Application::APP_ID, 'main', $params);
		$response->addHeader('Access-Control-Allow-Origin', '*');
		$csp = new ContentSecurityPolicy();
		//		$csp
		//			->allowInlineStyle(true)
		//			->addAllowedScriptDomain('*')
		//			->addAllowedStyleDomain('*')
		//			->addAllowedFontDomain('*')
		//			->addAllowedImageDomain('*')
		//			->addAllowedConnectDomain('*')
		//			->addAllowedMediaDomain('*')
		//			->addAllowedObjectDomain('*')
		//			->addAllowedFrameDomain('*')
		//			->addAllowedWorkerSrcDomain('* blob:')
		//		;
		$tsUrls = array_map(static function (array $ts) {
			return $ts['url'];
		}, array_merge($baseTileServers, $mbtss, $oss, $tssw, $ossw));
		$this->addCspForTiles($csp, $tsUrls);
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	private function addCspForTiles(ContentSecurityPolicy $csp, array $tsUrls): void {
		foreach ($tsUrls as $url) {
			$domain = parse_url($url, PHP_URL_HOST);
			$domain = str_replace('{s}', '*', $domain);
			$scheme = parse_url($url, PHP_URL_SCHEME);
			if ($scheme === 'http') {
				$csp->addAllowedImageDomain('http://' . $domain);
			} else {
				$csp->addAllowedImageDomain('https://' . $domain);
			}
		};
	}

	private function getReservedNames($token) {
		$result = [];

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('name', 'nametoken')
			->from('phonetrack_devices', 'd')
			->where(
				$qb->expr()->eq('sessionid', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			$dbdevicename = $row['name'];
			$dbnametoken = $row['nametoken'];
			if ($dbnametoken !== '' && $dbnametoken !== null) {
				$result[] = [
					'token' => $dbnametoken,
					'name' => $dbdevicename,
				];
			}
		}
		$req->closeCursor();

		return $result;
	}

	public function getSessions2(): array {
		$sessions = $this->sessionMapper->findByUser($this->userId);
		$sessions = array_map(function (Session $session) {
			$json = $session->jsonSerialize();
			$json['shared_with'] = $this->getUserShares($session->getToken());
			$json['reserved_names'] = $this->getReservedNames($session->getToken());
			$json['public_shares'] = $this->getPublicShares($session->getToken());
			$json['devices'] = $this->getDevices($session->getToken());
			return $json;
		}, $sessions);

		// sessions shared with current user
		$sidToShareToken = [];
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('sessionid', 'sharetoken')
			->from('phonetrack_shares')
			->where(
				$qb->expr()->eq('username', $qb->createNamedParameter($this->userId, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			$sidToShareToken[$row['sessionid']] = $row['sharetoken'];
		}
		$req->closeCursor();

		$sharedSessions = $this->sessionMapper->getSessionsById(array_keys($sidToShareToken));
		$sharedSessions = array_map(function (Session $session) use ($sidToShareToken) {
			$json = $session->jsonSerialize();
			$json['shared_with'] = $this->getUserShares($session->getToken());
			$json['reserved_names'] = $this->getReservedNames($session->getToken());
			$json['public_shares'] = $this->getPublicShares($session->getToken());
			$json['devices'] = $this->getDevices($session->getToken());
			$json['token'] = $sidToShareToken[$json['id']];
			return $json;
		}, $sharedSessions);

		return array_merge($sessions, $sharedSessions);
	}

	/**
	 * get sessions owned by and shared with current user
	 */
	#[NoAdminRequired]
	public function getSessions() {
		$sessions = [];
		// sessions owned by current user
		$sqlget = '
			SELECT name, token, publicviewtoken, public, autoexport, autopurge, locked
			FROM *PREFIX*phonetrack_sessions
			WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
			ORDER BY LOWER(name) ASC ;';
		$req = $this->dbConnection->prepare($sqlget);
		$res = $req->execute();
		while ($row = $res->fetch()) {
			$dbname = $row['name'];
			$dbtoken = $row['token'];
			$sharedWith = $this->getUserShares($dbtoken);
			$dbpublicviewtoken = $row['publicviewtoken'];
			$dbpublic = $row['public'];
			$dbautoexport = $row['autoexport'];
			$dbautopurge = $row['autopurge'];
			$dblocked = intval($row['locked']);
			$reservedNames = $this->getReservedNames($dbtoken);
			$publicShares = $this->getPublicShares($dbtoken);
			$devices = $this->getDevices($dbtoken);
			$sessions[] = [
				$dbname, $dbtoken, $dbpublicviewtoken, $devices, $dbpublic,
				$sharedWith, $reservedNames, $publicShares, $dbautoexport, $dbautopurge, $dblocked
			];
		}

		$ncUserList = $this->getUserList()->getData()['users'];
		// sessions shared with current user
		$sqlgetshares = '
			SELECT sessionid, sharetoken
			FROM *PREFIX*phonetrack_shares
			WHERE username=' . $this->db_quote_escape_string($this->userId) . ' ;';
		$req = $this->dbConnection->prepare($sqlgetshares);
		$res = $req->execute();
		while ($row = $res->fetch()) {
			$dbsessionid = $row['sessionid'];
			$dbsharetoken = $row['sharetoken'];
			$sessionInfo = $this->getSessionInfo($dbsessionid);
			$dbname = $sessionInfo['name'];
			$dbuserId = $sessionInfo['user'];
			$userNameDisplay = $dbuserId;
			if (array_key_exists($dbuserId, $ncUserList)) {
				$userNameDisplay = $ncUserList[$dbuserId];
			}
			$devices = $this->getDevices($dbsessionid);
			$sessions[] = [$dbname, $dbsharetoken, $userNameDisplay, $devices];
		}

		return new DataResponse([
			'sessions' => $sessions,
		]);
	}

	/**
	 * get sessions owned by and shared with current user
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function APIgetSessions() {
		$sessions = [];
		// sessions owned by current user
		$sqlGet = '
			SELECT name, token, publicviewtoken, public, autoexport, autopurge
			FROM *PREFIX*phonetrack_sessions
			WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
			ORDER BY LOWER(name) ASC ;';
		$req = $this->dbConnection->prepare($sqlGet);
		$res = $req->execute();
		while ($row = $res->fetch()) {
			$dbName = $row['name'];
			$dbToken = $row['token'];
			$sharedWith = $this->getUserShares($dbToken);
			$dbPublicViewToken = $row['publicviewtoken'];
			$dbPublic = $row['public'];
			$dbAutoExport = $row['autoexport'];
			$dbAutoPurge = $row['autopurge'];
			$reservedNames = $this->getReservedNames($dbToken);
			$publicShares = $this->getPublicShares($dbToken);
			$devices = $this->getDevices($dbToken);
			$sessions[] = [
				$dbName, $dbToken, $dbPublicViewToken, $devices, $dbPublic, $sharedWith,
				$reservedNames, $publicShares, $dbAutoExport, $dbAutoPurge
			];
		}
		$res->closeCursor();

		// sessions shared with current user
		$sqlGetShares = '
			SELECT sessionid, sharetoken,
				   *PREFIX*phonetrack_sessions.publicviewtoken AS publicviewtoken,
				   *PREFIX*phonetrack_sessions.public AS public
			FROM *PREFIX*phonetrack_shares
			INNER JOIN *PREFIX*phonetrack_sessions ON *PREFIX*phonetrack_shares.sessionid=*PREFIX*phonetrack_sessions.token
			WHERE username=' . $this->db_quote_escape_string($this->userId) . ' ;';
		$req = $this->dbConnection->prepare($sqlGetShares);
		$res = $req->execute();
		while ($row = $res->fetch()) {
			$dbSessionId = $row['sessionid'];
			$dbShareToken = $row['sharetoken'];
			$sessionInfo = $this->getSessionInfo($dbSessionId);
			$dbName = $sessionInfo['name'];
			$dbUser = $sessionInfo['user'];
			$dbPublic = is_numeric($row['public']) ? intval($row['public']) : 0;
			$dbPublicViewToken = $row['publicviewtoken'];
			$devices = $this->getDevices($dbSessionId);
			$sessions[] = [$dbName, $dbShareToken, $dbPublicViewToken, $devices, $dbPublic, $dbUser];
		}
		$res->closeCursor();

		return new DataResponse($sessions);
	}

	private function getDevices($sessionid) {
		$devices = [];
		$sqlGet = '
			SELECT id, name, alias, color, nametoken, shape
			FROM *PREFIX*phonetrack_devices
			WHERE sessionid=' . $this->db_quote_escape_string($sessionid) . '
			ORDER BY LOWER(name) ASC ;';
		$req = $this->dbConnection->prepare($sqlGet);
		$res = $req->execute();
		while ($row = $res->fetch()) {
			$dbId = $row['id'];
			$dbName = $row['name'];
			$dbAlias = $row['alias'];
			$dbColor = $row['color'];
			$dbNameToken = $row['nametoken'];
			$dbShape = $row['shape'];
			$geofences = $this->getGeofences($dbId);
			$proxims = $this->getProxims($dbId);
			$oneDev = [$dbId, $dbName, $dbAlias, $dbColor, $dbNameToken, $geofences, $proxims, $dbShape];
			$devices[] = $oneDev;
		}
		$res->closeCursor();

		return $devices;
	}

	private function getSessionInfo(string $sessionId): array {
		$dbName = null;
		$dbUser = null;
		$sqlGet = '
			SELECT name, ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '
			FROM *PREFIX*phonetrack_sessions
			WHERE token=' . $this->db_quote_escape_string($sessionId) . ';';
		$req = $this->dbConnection->prepare($sqlGet);
		$res = $req->execute();
		while ($row = $res->fetch()) {
			$dbName = $row['name'];
			$dbUser = $row['user'];
		}
		$res->closeCursor();

		return ['user' => $dbUser, 'name' => $dbName];
	}

	/**
	 * with whom is this session shared ?
	 */
	private function getUserShares(string $sessionId): array {
		$ncUserList = $this->getUserList()->getData()['users'];
		$sharesToDelete = [];
		$sharedWith = [];
		$sqlchk = '
			SELECT username
			FROM *PREFIX*phonetrack_shares
			WHERE sessionid=' . $this->db_quote_escape_string($sessionId) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$res = $req->execute();
		while ($row = $res->fetch()) {
			$userId = $row['username'];
			if (array_key_exists($userId, $ncUserList)) {
				$userName = $ncUserList[$userId];
				$sharedWith[$userId] = $userName;
			} else {
				$sharesToDelete[] = $userId;
			}
		}
		$res->closeCursor();

		// delete useless shares (with unexisting users)
		foreach ($sharesToDelete as $uid) {
			$sqlDel = '
				DELETE FROM *PREFIX*phonetrack_shares
				WHERE sessionid=' . $this->db_quote_escape_string($sessionId) . '
					AND username=' . $this->db_quote_escape_string($uid) . ' ;';
			$req = $this->dbConnection->prepare($sqlDel);
			$res = $req->execute();
			$res->closeCursor();
		}

		return $sharedWith;
	}

	/**
	 * get the public shares for a session
	 */
	private function getPublicShares(string $sessionId): array {
		$shares = [];
		$sqlGet = '
			SELECT *
			FROM *PREFIX*phonetrack_pubshares
			WHERE sessionid=' . $this->db_quote_escape_string($sessionId) . ' ;';
		$req = $this->dbConnection->prepare($sqlGet);
		$res = $req->execute();
		while ($row = $res->fetch()) {
			$shares[] = [
				'token' => $row['sharetoken'],
				'filters' => $row['filters'],
				'devicename' => $row['devicename'],
				'lastposonly' => $row['lastposonly'],
				'geofencify' => $row['geofencify'],
			];
		}
		$res->closeCursor();

		return $shares;
	}

	#[NoAdminRequired]
	public function setPublicShareDevice(string $token, string $sharetoken, string $devicename): DataResponse {
		$done = 0;
		// check if sessions exists
		$sqlchk = '
			SELECT name
			FROM *PREFIX*phonetrack_sessions
			WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
				  AND token=' . $this->db_quote_escape_string($token) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$res = $req->execute();
		$dbname = null;
		while ($row = $res->fetch()) {
			$dbname = $row['name'];
			break;
		}
		$res->closeCursor();

		if ($dbname !== null) {
			// check if sharetoken exists
			$sqlchk = '
				SELECT *
				FROM *PREFIX*phonetrack_pubshares
				WHERE sessionid=' . $this->db_quote_escape_string($token) . '
				AND sharetoken=' . $this->db_quote_escape_string($sharetoken) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$res = $req->execute();
			$dbShareId = null;
			while ($row = $res->fetch()) {
				$dbShareId = $row['id'];
			}
			$res->closeCursor();

			if ($dbShareId !== null) {
				// set device name
				$sqlUpd = '
					UPDATE *PREFIX*phonetrack_pubshares
					SET devicename=' . $this->db_quote_escape_string($devicename) . '
					WHERE id=' . $this->db_quote_escape_string($dbShareId) . ' ;';
				$req = $this->dbConnection->prepare($sqlUpd);
				$res = $req->execute();
				$res->closeCursor();

				$done = 1;
			} else {
				$done = 3;
			}
		} else {
			$done = 2;
		}

		return new DataResponse([
			'done' => $done,
		]);
	}

	#[NoAdminRequired]
	public function setPublicShareGeofencify(string $token, string $sharetoken, int $geofencify): DataResponse {
		$done = 0;
		// check if sessions exists
		$sqlCheck = '
			SELECT name
			FROM *PREFIX*phonetrack_sessions
			WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
				  AND token=' . $this->db_quote_escape_string($token) . ' ;';
		$req = $this->dbConnection->prepare($sqlCheck);
		$res = $req->execute();
		$dbname = null;
		while ($row = $res->fetch()) {
			$dbname = $row['name'];
			break;
		}
		$res->closeCursor();

		if ($dbname !== null) {
			// check if sharetoken exists
			$sqlCheck = '
				SELECT *
				FROM *PREFIX*phonetrack_pubshares
				WHERE sessionid=' . $this->db_quote_escape_string($token) . '
					  AND sharetoken=' . $this->db_quote_escape_string($sharetoken) . ' ;';
			$req = $this->dbConnection->prepare($sqlCheck);
			$res = $req->execute();
			$dbShareId = null;
			while ($row = $res->fetch()) {
				$dbShareId = $row['id'];
			}
			$res->closeCursor();

			if ($dbShareId !== null) {
				// set device name
				$sqlUpd = '
					UPDATE *PREFIX*phonetrack_pubshares
					SET geofencify=' . $this->db_quote_escape_string($geofencify) . '
					WHERE id=' . $this->db_quote_escape_string($dbShareId) . ' ;';
				$req = $this->dbConnection->prepare($sqlUpd);
				$res = $req->execute();
				$res->closeCursor();

				$done = 1;
			} else {
				$done = 3;
			}
		} else {
			$done = 2;
		}

		return new DataResponse(['done' => $done]);
	}

	#[NoAdminRequired]
	public function setPublicShareLastOnly($token, $sharetoken, $lastposonly) {
		$done = 0;
		// check if sessions exists
		$sqlchk = '
			SELECT name
			FROM *PREFIX*phonetrack_sessions
			WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
				  AND token=' . $this->db_quote_escape_string($token) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$req->execute();
		$dbname = null;
		while ($row = $req->fetch()) {
			$dbname = $row['name'];
			break;
		}
		$req->closeCursor();

		if ($dbname !== null) {
			// check if sharetoken exists
			$sqlchk = '
				SELECT *
				FROM *PREFIX*phonetrack_pubshares
				WHERE sessionid=' . $this->db_quote_escape_string($token) . '
					  AND sharetoken=' . $this->db_quote_escape_string($sharetoken) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$req->execute();
			$dbshareid = null;
			while ($row = $req->fetch()) {
				$dbshareid = $row['id'];
			}
			$req->closeCursor();

			if ($dbshareid !== null) {
				// set device name
				$sqlupd = '
					UPDATE *PREFIX*phonetrack_pubshares
					SET lastposonly=' . $this->db_quote_escape_string($lastposonly) . '
					WHERE id=' . $this->db_quote_escape_string($dbshareid) . ' ;';
				$req = $this->dbConnection->prepare($sqlupd);
				$req->execute();
				$req->closeCursor();

				$done = 1;
			} else {
				$done = 3;
			}
		} else {
			$done = 2;
		}

		return new DataResponse([
			'done' => $done,
		]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function APIcreateSession($sessionname) {
		return $this->createSession($sessionname);
	}

	#[NoAdminRequired]
	public function createSession($name) {
		$token = '';
		$publicviewtoken = '';
		// check if session name is not already used
		$sqlchk = '
			SELECT name
			FROM *PREFIX*phonetrack_sessions
			WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
			AND name=' . $this->db_quote_escape_string($name) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$req->execute();
		$dbname = null;
		while ($row = $req->fetch()) {
			$dbname = $row['name'];
			break;
		}
		$req->closeCursor();

		if ($dbname === null && $name !== '') {
			// determine token
			$token = md5($this->userId . $name . rand());
			$publicviewtoken = md5($this->userId . $name . rand());

			// insert
			$sql = '
				INSERT INTO *PREFIX*phonetrack_sessions
				(' . $this->dbdblquotes . 'user' . $this->dbdblquotes . ', name, token, publicviewtoken, public, creationversion)
				VALUES (' . $this->db_quote_escape_string($this->userId) . ',' .
						  $this->db_quote_escape_string($name) . ',' .
						  $this->db_quote_escape_string($token) . ',' .
						  $this->db_quote_escape_string($publicviewtoken) . ',' .
						  $this->db_quote_escape_string('1') . ',' .
						  $this->db_quote_escape_string($this->appManager->getAppVersion(Application::APP_ID)) . '
				);';
			$req = $this->dbConnection->prepare($sql);
			$req->execute();
			$req->closeCursor();

			$ok = 1;
		} else {
			$ok = 2;
		}

		return new DataResponse([
			'done' => $ok,
			'token' => $token,
			'publicviewtoken' => $publicviewtoken,
		]);
	}

	#[NoAdminRequired]
	public function deleteSession($token) {
		$ok = 0;
		// check if session exists
		$sqlchk = '
			SELECT name
			FROM *PREFIX*phonetrack_sessions
			WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
				  AND token=' . $this->db_quote_escape_string($token) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$req->execute();
		$dbname = null;
		while ($row = $req->fetch()) {
			$dbname = $row['name'];
			break;
		}
		$req->closeCursor();

		if ($dbname !== null) {
			// get all devices
			$dids = [];
			$sqlchk = '
				SELECT id
				FROM *PREFIX*phonetrack_devices
				WHERE sessionid=' . $this->db_quote_escape_string($token) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$req->execute();
			$dbdevid = null;
			while ($row = $req->fetch()) {
				array_push($dids, $row['id']);
			}
			$req->closeCursor();

			foreach ($dids as $did) {
				$this->deleteDevice($token, $did);
			}

			$sqldel = '
				DELETE FROM *PREFIX*phonetrack_shares
				WHERE sessionid=' . $this->db_quote_escape_string($token) . ' ;';
			$req = $this->dbConnection->prepare($sqldel);
			$req->execute();
			$req->closeCursor();

			$sqldel = '
				DELETE FROM *PREFIX*phonetrack_pubshares
				WHERE sessionid=' . $this->db_quote_escape_string($token) . ' ;';
			$req = $this->dbConnection->prepare($sqldel);
			$req->execute();
			$req->closeCursor();

			$sqldel = '
				DELETE FROM *PREFIX*phonetrack_sessions
				WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
					  AND token=' . $this->db_quote_escape_string($token) . ' ;';
			$req = $this->dbConnection->prepare($sqldel);
			$req->execute();
			$req->closeCursor();

			$ok = 1;
		} else {
			$ok = 2;
		}

		return new DataResponse([
			'done' => $ok,
		]);
	}

	#[NoAdminRequired]
	public function deletePoints($token, $deviceid, $pointids) {
		$ok = 0;
		// check if session exists
		$sqlchk = '
			SELECT name
			FROM *PREFIX*phonetrack_sessions
			WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
				  AND token=' . $this->db_quote_escape_string($token) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$req->execute();
		$dbname = null;
		while ($row = $req->fetch()) {
			$dbname = $row['name'];
			break;
		}
		$req->closeCursor();

		if ($dbname !== null) {
			// check if device exists
			$dbdid = null;
			$sqldev = '
				SELECT id
				FROM *PREFIX*phonetrack_devices
				WHERE sessionid=' . $this->db_quote_escape_string($token) . '
					  AND id=' . $this->db_quote_escape_string($deviceid) . ' ;';
			$req = $this->dbConnection->prepare($sqldev);
			$req->execute();
			while ($row = $req->fetch()) {
				$dbdid = $row['id'];
			}
			$req->closeCursor();

			if ($dbdid !== null) {
				if (count($pointids) > 0) {
					$escapedPointIds = [];
					foreach ($pointids as $pid) {
						array_push($escapedPointIds, $this->db_quote_escape_string($pid));
					}
					$sqldel = '
						DELETE FROM *PREFIX*phonetrack_points
						WHERE deviceid=' . $this->db_quote_escape_string($dbdid) . '
							  AND (id=' . implode(' OR id=', $escapedPointIds) . ');';
					$req = $this->dbConnection->prepare($sqldel);
					$req->execute();
					$req->closeCursor();

					$ok = 1;
				} else {
					$ok = 2;
				}
			} else {
				$ok = 3;
			}
		} else {
			$ok = 4;
		}

		return new DataResponse([
			'done' => $ok,
		]);
	}

	#[NoAdminRequired]
	public function updatePoint($token, $deviceid, $pointid,
		$lat, $lon, $alt, $timestamp, $acc, $bat, $sat, $useragent, $speed, $bearing) {
		// check if session exists
		$sqlchk = '
			SELECT name
			FROM *PREFIX*phonetrack_sessions
			WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
				   AND token=' . $this->db_quote_escape_string($token) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$req->execute();
		$dbname = null;
		while ($row = $req->fetch()) {
			$dbname = $row['name'];
			break;
		}
		$req->closeCursor();

		if ($dbname !== null) {
			// check if device exists
			$dbdid = null;
			$sqldev = '
				SELECT id
				FROM *PREFIX*phonetrack_devices
				WHERE sessionid=' . $this->db_quote_escape_string($token) . '
					  AND id=' . $this->db_quote_escape_string($deviceid) . ' ;';
			$req = $this->dbConnection->prepare($sqldev);
			$req->execute();
			while ($row = $req->fetch()) {
				$dbdid = $row['id'];
			}
			$req->closeCursor();

			if ($dbdid !== null) {
				// check if point exists
				$sqlchk = '
					SELECT id
					FROM *PREFIX*phonetrack_points
					WHERE deviceid=' . $this->db_quote_escape_string($dbdid) . '
						  AND id=' . $this->db_quote_escape_string($pointid) . ' ;';
				$req = $this->dbConnection->prepare($sqlchk);
				$req->execute();
				$dbpid = null;
				while ($row = $req->fetch()) {
					$dbpid = $row['id'];
					break;
				}
				$req->closeCursor();

				if ($dbpid !== null) {
					$sqlupd = '
						UPDATE *PREFIX*phonetrack_points
						SET
							 lat=' . $this->db_quote_escape_string($lat) . ',
							 lon=' . $this->db_quote_escape_string($lon) . ',
							 altitude=' . (is_numeric($alt) ? $this->db_quote_escape_string(floatval($alt)) : 'NULL') . ',
							 timestamp=' . $this->db_quote_escape_string($timestamp) . ',
							 accuracy=' . (is_numeric($acc) ? $this->db_quote_escape_string(floatval($acc)) : 'NULL') . ',
							 batterylevel=' . (is_numeric($bat) ? $this->db_quote_escape_string(floatval($bat)) : 'NULL') . ',
							 satellites=' . (is_numeric($sat) ? $this->db_quote_escape_string(intval($sat)) : 'NULL') . ',
							 useragent=' . $this->db_quote_escape_string($useragent) . ',
							 speed=' . (is_numeric($speed) ? $this->db_quote_escape_string(floatval($speed)) : 'NULL') . ',
							 bearing=' . (is_numeric($bearing) ? $this->db_quote_escape_string(floatval($bearing)) : 'NULL') . '
						WHERE deviceid=' . $this->db_quote_escape_string($dbdid) . '
							  AND id=' . $this->db_quote_escape_string($dbpid) . ' ;';
					$req = $this->dbConnection->prepare($sqlupd);
					$req->execute();
					$req->closeCursor();

					$ok = 1;
				} else {
					$ok = 2;
				}
			} else {
				$ok = 3;
			}
		} else {
			$ok = 4;
		}

		return new DataResponse([
			'done' => $ok,
		]);
	}

	#[NoAdminRequired]
	public function setSessionPublic($token, $public) {
		$ok = 0;
		if (intval($public) === 1 || intval($public) === 0) {
			// check if session exists
			$sqlchk = '
				SELECT name
				FROM *PREFIX*phonetrack_sessions
				WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
					  AND token=' . $this->db_quote_escape_string($token) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$req->execute();
			$dbname = null;
			while ($row = $req->fetch()) {
				$dbname = $row['name'];
				break;
			}
			$req->closeCursor();

			if ($dbname !== null) {
				$sqlren = '
					UPDATE *PREFIX*phonetrack_sessions
					SET public=' . $this->db_quote_escape_string($public) . '
					WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
						  AND token=' . $this->db_quote_escape_string($token) . ' ;';
				$req = $this->dbConnection->prepare($sqlren);
				$req->execute();
				$req->closeCursor();

				$ok = 1;
			} else {
				$ok = 2;
			}
		} else {
			$ok = 3;
		}

		return new DataResponse([
			'done' => $ok,
		]);
	}

	#[NoAdminRequired]
	public function setSessionLocked($token, $locked) {
		$ilocked = intval($locked);
		if ($ilocked === 1 || $ilocked === 0) {
			// check if session exists
			$qb = $this->dbConnection->getQueryBuilder();
			// is the project shared with the user ?
			$qb->select('name')
				->from('phonetrack_sessions', 's')
				->where(
					$qb->expr()->eq('user', $qb->createNamedParameter($this->userId, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR))
				);
			$req = $qb->execute();
			$dbname = null;
			while ($row = $req->fetch()) {
				$dbname = $row['name'];
				break;
			}
			$req->closeCursor();
			$qb = $qb->resetQueryParts();

			if ($dbname !== null) {
				$qb->update('phonetrack_sessions');
				$qb->set('locked', $qb->createNamedParameter($ilocked, IQueryBuilder::PARAM_INT))
					->where(
						$qb->expr()->eq('user', $qb->createNamedParameter($this->userId, IQueryBuilder::PARAM_STR))
					)
					->andWhere(
						$qb->expr()->eq('token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR))
					);
				$req = $qb->execute();
				$qb = $qb->resetQueryParts();

				return new DataResponse(['done' => 1]);
			} else {
				return new DataResponse(['done' => 2], Http::STATUS_BAD_REQUEST);
			}
		} else {
			return new DataResponse(['done' => 3], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function setSessionAutoExport($token, $value) {
		// check if session exists
		$sqlchk = '
			SELECT name
			FROM *PREFIX*phonetrack_sessions
			WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
				  AND token=' . $this->db_quote_escape_string($token) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$req->execute();
		$dbname = null;
		while ($row = $req->fetch()) {
			$dbname = $row['name'];
			break;
		}
		$req->closeCursor();

		if ($dbname !== null) {
			$sqlren = '
				UPDATE *PREFIX*phonetrack_sessions
				SET autoexport=' . $this->db_quote_escape_string($value) . '
				WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
					  AND token=' . $this->db_quote_escape_string($token) . ' ;';
			$req = $this->dbConnection->prepare($sqlren);
			$req->execute();
			$req->closeCursor();

			$ok = 1;
		} else {
			$ok = 2;
		}

		return new DataResponse([
			'done' => $ok,
		]);
	}

	#[NoAdminRequired]
	public function setSessionAutoPurge($token, $value) {
		// check if session exists
		$sqlchk = '
			SELECT name
			FROM *PREFIX*phonetrack_sessions
			WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
				  AND token=' . $this->db_quote_escape_string($token) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$req->execute();
		$dbname = null;
		while ($row = $req->fetch()) {
			$dbname = $row['name'];
			break;
		}
		$req->closeCursor();

		if ($dbname !== null) {
			$sqlren = '
				UPDATE *PREFIX*phonetrack_sessions
				SET autopurge=' . $this->db_quote_escape_string($value) . '
				WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
					  AND token=' . $this->db_quote_escape_string($token) . ' ;';
			$req = $this->dbConnection->prepare($sqlren);
			$req->execute();
			$req->closeCursor();

			$ok = 1;
		} else {
			$ok = 2;
		}

		return new DataResponse([
			'done' => $ok,
		]);
	}

	#[NoAdminRequired]
	public function setDeviceColor($session, $device, $color) {
		$ok = 0;
		// check if session exists
		$sqlchk = '
			SELECT name
			FROM *PREFIX*phonetrack_sessions
			WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
				  AND token=' . $this->db_quote_escape_string($session) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$req->execute();
		$dbname = null;
		while ($row = $req->fetch()) {
			$dbname = $row['name'];
			break;
		}
		$req->closeCursor();

		if ($dbname !== null) {
			// check if device exists
			$sqlchk = '
				SELECT id
				FROM *PREFIX*phonetrack_devices
				WHERE sessionid=' . $this->db_quote_escape_string($session) . '
					  AND id=' . $this->db_quote_escape_string($device) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$req->execute();
			$dbdevid = null;
			while ($row = $req->fetch()) {
				$dbdevid = $row['id'];
				break;
			}
			$req->closeCursor();

			if ($dbdevid !== null) {
				$sqlupd = '
					UPDATE *PREFIX*phonetrack_devices
					SET color=' . $this->db_quote_escape_string($color) . '
					WHERE id=' . $this->db_quote_escape_string($device) . '
						  AND sessionid=' . $this->db_quote_escape_string($session) . ' ;';
				$req = $this->dbConnection->prepare($sqlupd);
				$req->execute();
				$req->closeCursor();
				$ok = 1;
			} else {
				$ok = 3;
			}
		} else {
			$ok = 2;
		}

		return new DataResponse([
			'done' => $ok,
		]);
	}

	#[NoAdminRequired]
	public function setDeviceShape($session, $device, $shape) {
		$ok = 0;
		// check if session exists
		$sqlchk = '
			SELECT name
			FROM *PREFIX*phonetrack_sessions
			WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
				  AND token=' . $this->db_quote_escape_string($session) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$req->execute();
		$dbname = null;
		while ($row = $req->fetch()) {
			$dbname = $row['name'];
			break;
		}
		$req->closeCursor();

		if ($dbname !== null) {
			// check if device exists
			$sqlchk = '
				SELECT id
				FROM *PREFIX*phonetrack_devices
				WHERE sessionid=' . $this->db_quote_escape_string($session) . '
					  AND id=' . $this->db_quote_escape_string($device) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$req->execute();
			$dbdevid = null;
			while ($row = $req->fetch()) {
				$dbdevid = $row['id'];
				break;
			}
			$req->closeCursor();

			if ($dbdevid !== null) {
				$sqlupd = '
					UPDATE *PREFIX*phonetrack_devices
					SET shape=' . $this->db_quote_escape_string($shape) . '
					WHERE id=' . $this->db_quote_escape_string($device) . '
						  AND sessionid=' . $this->db_quote_escape_string($session) . ' ;';
				$req = $this->dbConnection->prepare($sqlupd);
				$req->execute();
				$req->closeCursor();
				$ok = 1;
			} else {
				$ok = 3;
			}
		} else {
			$ok = 2;
		}

		return new DataResponse([
			'done' => $ok,
		]);
	}

	#[NoAdminRequired]
	public function renameSession($token, $newname) {
		$ok = 0;
		if ($newname !== '' && $newname !== null) {
			// check if session exists
			$sqlchk = '
				SELECT name
				FROM *PREFIX*phonetrack_sessions
				WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
					  AND token=' . $this->db_quote_escape_string($token) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$req->execute();
			$dbname = null;
			while ($row = $req->fetch()) {
				$dbname = $row['name'];
				break;
			}
			$req->closeCursor();

			if ($dbname !== null) {
				$sqlren = '
					UPDATE *PREFIX*phonetrack_sessions
					SET name=' . $this->db_quote_escape_string($newname) . '
					WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
						  AND token=' . $this->db_quote_escape_string($token) . ' ;';
				$req = $this->dbConnection->prepare($sqlren);
				$req->execute();
				$req->closeCursor();

				$ok = 1;
			} else {
				$ok = 2;
			}
		} else {
			$ok = 3;
		}

		return new DataResponse([
			'done' => $ok,
		]);
	}

	#[NoAdminRequired]
	public function renameDevice($token, $deviceid, $newname) {
		$ok = 0;
		if ($newname !== '' && $newname !== null) {
			// check if session exists
			$sqlchk = '
				SELECT name, token
				FROM *PREFIX*phonetrack_sessions
				WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
					  AND token=' . $this->db_quote_escape_string($token) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$req->execute();
			$dbtoken = null;
			while ($row = $req->fetch()) {
				$dbtoken = $row['token'];
				break;
			}
			$req->closeCursor();

			if ($dbtoken !== null) {
				// check if device exists
				$sqlchk = '
					SELECT id
					FROM *PREFIX*phonetrack_devices
					WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
						  AND id=' . $this->db_quote_escape_string($deviceid) . ' ;';
				$req = $this->dbConnection->prepare($sqlchk);
				$req->execute();
				$dbdeviceid = null;
				while ($row = $req->fetch()) {
					$dbdeviceid = $row['id'];
				}
				$req->closeCursor();

				if ($dbdeviceid !== null) {
					$sqlren = '
						UPDATE *PREFIX*phonetrack_devices
						SET name=' . $this->db_quote_escape_string($newname) . '
						WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
							  AND id=' . $this->db_quote_escape_string($dbdeviceid) . ' ;';
					$req = $this->dbConnection->prepare($sqlren);
					$req->execute();
					$req->closeCursor();

					$ok = 1;
				} else {
					$ok = 2;
				}
			} else {
				$ok = 3;
			}
		} else {
			$ok = 4;
		}

		return new DataResponse([
			'done' => $ok,
		]);
	}

	#[NoAdminRequired]
	public function setDeviceAlias($token, $deviceid, $newalias) {
		$ok = 0;
		if ($newalias !== null) {
			// check if session exists
			$sqlchk = '
				SELECT name, token
				FROM *PREFIX*phonetrack_sessions
				WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
					  AND token=' . $this->db_quote_escape_string($token) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$req->execute();
			$dbtoken = null;
			while ($row = $req->fetch()) {
				$dbtoken = $row['token'];
				break;
			}
			$req->closeCursor();

			if ($dbtoken !== null) {
				// check if device exists
				$sqlchk = '
					SELECT id
					FROM *PREFIX*phonetrack_devices
					WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
						  AND id=' . $this->db_quote_escape_string($deviceid) . ' ;';
				$req = $this->dbConnection->prepare($sqlchk);
				$req->execute();
				$dbdeviceid = null;
				while ($row = $req->fetch()) {
					$dbdeviceid = $row['id'];
				}
				$req->closeCursor();

				if ($dbdeviceid !== null) {
					$sqlren = '
						UPDATE *PREFIX*phonetrack_devices
						SET alias=' . $this->db_quote_escape_string($newalias) . '
						WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
							  AND id=' . $this->db_quote_escape_string($dbdeviceid) . ' ;';
					$req = $this->dbConnection->prepare($sqlren);
					$req->execute();
					$req->closeCursor();

					$ok = 1;
				} else {
					$ok = 2;
				}
			} else {
				$ok = 3;
			}
		} else {
			$ok = 4;
		}

		return new DataResponse([
			'done' => $ok,
		]);
	}

	#[NoAdminRequired]
	public function reaffectDevice($token, $deviceid, $newSessionId) {
		$ok = 0;
		// check if session exists
		$sqlchk = '
			SELECT name, token
			FROM *PREFIX*phonetrack_sessions
			WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
				  AND token=' . $this->db_quote_escape_string($token) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$req->execute();
		$dbtoken = null;
		while ($row = $req->fetch()) {
			$dbtoken = $row['token'];
			break;
		}
		$req->closeCursor();

		if ($dbtoken !== null) {
			// check if destination session exists
			$sqlchk = '
				SELECT name, token
				FROM *PREFIX*phonetrack_sessions
				WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
					  AND token=' . $this->db_quote_escape_string($newSessionId) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$req->execute();
			$dbdesttoken = null;
			while ($row = $req->fetch()) {
				$dbdesttoken = $row['token'];
				break;
			}
			$req->closeCursor();

			if ($dbdesttoken !== null) {
				// check if device exists
				$sqlchk = '
					SELECT id, name FROM *PREFIX*phonetrack_devices
					WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
						  AND id=' . $this->db_quote_escape_string($deviceid) . ' ;';
				$req = $this->dbConnection->prepare($sqlchk);
				$req->execute();
				$dbdeviceid = null;
				$dbdevicename = null;
				while ($row = $req->fetch()) {
					$dbdeviceid = $row['id'];
					$dbdevicename = $row['name'];
				}
				$req->closeCursor();

				if ($dbdeviceid !== null) {
					// check if there is a device with same name in destination session
					$sqlchk = '
						SELECT id, name
						FROM *PREFIX*phonetrack_devices
						WHERE sessionid=' . $this->db_quote_escape_string($dbdesttoken) . '
							  AND name=' . $this->db_quote_escape_string($dbdevicename) . ' ;';
					$req = $this->dbConnection->prepare($sqlchk);
					$req->execute();
					$dbdestname = null;
					while ($row = $req->fetch()) {
						$dbdestname = $row['name'];
					}
					$req->closeCursor();

					if ($dbdestname === null) {
						$sqlreaff = '
							UPDATE *PREFIX*phonetrack_devices
							SET sessionid=' . $this->db_quote_escape_string($dbdesttoken) . '
							WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
								  AND id=' . $this->db_quote_escape_string($dbdeviceid) . ' ;';
						$req = $this->dbConnection->prepare($sqlreaff);
						$req->execute();
						$req->closeCursor();

						$ok = 1;
					} else {
						$ok = 3;
					}
				} else {
					$ok = 4;
				}
			} else {
				$ok = 5;
			}
		} else {
			$ok = 2;
		}

		return new DataResponse([
			'done' => $ok,
		]);
	}

	#[NoAdminRequired]
	public function deleteDevice($token, $deviceid) {
		$ok = 0;
		// check if session exists
		$sqlchk = '
			SELECT name, token
			FROM *PREFIX*phonetrack_sessions
			WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
				  AND token=' . $this->db_quote_escape_string($token) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$req->execute();
		$dbname = null;
		while ($row = $req->fetch()) {
			$dbname = $row['name'];
			break;
		}
		$req->closeCursor();

		if ($dbname !== null) {
			// check if device exists
			$sqlchk = '
				SELECT id
				FROM *PREFIX*phonetrack_devices
				WHERE sessionid=' . $this->db_quote_escape_string($token) . '
					  AND id=' . $this->db_quote_escape_string($deviceid) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$req->execute();
			$dbdeviceid = null;
			while ($row = $req->fetch()) {
				$dbdeviceid = $row['id'];
			}
			$req->closeCursor();

			if ($dbdeviceid !== null) {
				$sqldel = '
					DELETE FROM *PREFIX*phonetrack_points
					WHERE deviceid=' . $this->db_quote_escape_string($dbdeviceid) . ' ;';
				$req = $this->dbConnection->prepare($sqldel);
				$req->execute();
				$req->closeCursor();

				$sqldel = '
					DELETE FROM *PREFIX*phonetrack_geofences
					WHERE deviceid=' . $this->db_quote_escape_string($dbdeviceid) . ' ;';
				$req = $this->dbConnection->prepare($sqldel);
				$req->execute();
				$req->closeCursor();

				$sqldel = '
					DELETE FROM *PREFIX*phonetrack_proxims
					WHERE deviceid1=' . $this->db_quote_escape_string($dbdeviceid) . '
						  OR deviceid2=' . $this->db_quote_escape_string($dbdeviceid) . ' ;';
				$req = $this->dbConnection->prepare($sqldel);
				$req->execute();
				$req->closeCursor();

				$sqldel = '
					DELETE FROM *PREFIX*phonetrack_devices
					WHERE id=' . $this->db_quote_escape_string($dbdeviceid) . ' ;';
				$req = $this->dbConnection->prepare($sqldel);
				$req->execute();
				$req->closeCursor();
				$ok = 1;
			} else {
				$ok = 3;
			}
		} else {
			$ok = 2;
		}

		return new DataResponse([
			'done' => $ok,
		]);
	}

	/**
	 * called by normal (logged) page
	 */
	#[NoAdminRequired]
	public function track($sessions) {
		$result = [];
		$colors = [];
		$shapes = [];
		$names = [];
		$aliases = [];
		$geofences = [];
		$proxims = [];
		// manage sql optim filters (time only)
		$filters = $this->sessionService->getCurrentFilters2($this->userId);
		$settingsTimeFilterSQL = '';
		if (isset($filters['timestamp'])) {
			if (isset($filters['timestamp']['min'])) {
				$settingsTimeFilterSQL .= 'AND timestamp >= ' . $this->db_quote_escape_string($filters['timestamp']['min']) . ' ';
			}
			if (isset($filters['timestamp']['max'])) {
				$settingsTimeFilterSQL .= 'AND timestamp <= ' . $this->db_quote_escape_string($filters['timestamp']['max']) . ' ';
			}
		}
		// get option value
		$nbpointsload = $this->config->getUserValue($this->userId, Application::APP_ID, 'nbpointsload', '10000');

		if (is_array($sessions)) {
			foreach ($sessions as $session) {
				if (is_array($session) && count($session) === 3) {
					$token = $session[0];
					$lastTime = $session[1];
					$firstTime = $session[2];

					// check if session exists
					$dbtoken = null;
					$sqlget = '
						SELECT token
						FROM *PREFIX*phonetrack_sessions
						WHERE token=' . $this->db_quote_escape_string($token) . ' ;';
					$req = $this->dbConnection->prepare($sqlget);
					$req->execute();
					while ($row = $req->fetch()) {
						$dbtoken = $row['token'];
					}
					$req->closeCursor();

					// if not, check it is a shared session
					if ($dbtoken === null) {
						$sqlget = '
							SELECT sessionid
							FROM *PREFIX*phonetrack_shares
							WHERE sharetoken=' . $this->db_quote_escape_string($token) . '
								  AND username=' . $this->db_quote_escape_string($this->userId) . ' ;';
						$req = $this->dbConnection->prepare($sqlget);
						$req->execute();
						while ($row = $req->fetch()) {
							$dbtoken = $row['sessionid'];
						}
						$req->closeCursor();
					}

					// session exists
					if ($dbtoken !== null) {
						// get list of devices
						$devices = [];
						$sqldev = '
							SELECT id
							FROM *PREFIX*phonetrack_devices
							WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . ' ;';
						$req = $this->dbConnection->prepare($sqldev);
						$req->execute();
						while ($row = $req->fetch()) {
							array_push($devices, intval($row['id']));
						}
						$req->closeCursor();

						// get the coords for each device
						$result[$token] = [];

						foreach ($devices as $devid) {
							$resultDevArray = [];

							$firstDeviceTimeSQL = '';
							if (is_array($firstTime) && array_key_exists($devid, $firstTime)) {
								$firstDeviceTime = $firstTime[$devid];
								$firstDeviceTimeSQL = 'timestamp<' . $this->db_quote_escape_string($firstDeviceTime);
							}

							$lastDeviceTime = 0;
							$lastDeviceTimeSQL = '';
							if (is_array($lastTime) && array_key_exists($devid, $lastTime)) {
								$lastDeviceTime = $lastTime[$devid];
								$lastDeviceTimeSQL = 'timestamp>' . $this->db_quote_escape_string($lastDeviceTime);
							}
							// build SQL condition for first/last
							$firstLastSQL = '';
							if ($firstDeviceTimeSQL !== '') {
								if ($lastDeviceTimeSQL !== '') {
									$firstLastSQL = 'AND (' . $firstDeviceTimeSQL . ' OR ' . $lastDeviceTimeSQL . ') ';
								} else {
									$firstLastSQL = 'AND ' . $firstDeviceTimeSQL . ' ';
								}
							} elseif ($lastDeviceTimeSQL !== '') {
								$firstLastSQL = 'AND ' . $lastDeviceTimeSQL . ' ';
							}
							// we give color (first point given)
							else {
								$sqlcolor = '
									SELECT color, name, alias, shape
									FROM *PREFIX*phonetrack_devices
									WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
										  AND id=' . $this->db_quote_escape_string($devid) . ' ;';
								$req = $this->dbConnection->prepare($sqlcolor);
								$req->execute();
								$col = '';
								while ($row = $req->fetch()) {
									$shape = $row['shape'];
									$col = $row['color'];
									$name = $row['name'];
									$alias = $row['alias'];
								}
								$req->closeCursor();
								if (!array_key_exists($token, $shapes)) {
									$shapes[$token] = [];
								}
								$shapes[$token][$devid] = $shape;
								if (!array_key_exists($token, $colors)) {
									$colors[$token] = [];
								}
								$colors[$token][$devid] = $col;
								if (!array_key_exists($token, $names)) {
									$names[$token] = [];
								}
								$names[$token][$devid] = $name;
								if (!array_key_exists($token, $aliases)) {
									$aliases[$token] = [];
								}
								$aliases[$token][$devid] = $alias;
								// geofences
								if (!array_key_exists($token, $geofences)) {
									$geofences[$token] = [];
								}
								if (!array_key_exists($devid, $geofences[$token])) {
									$geofences[$token][$devid] = [];
								}
								$geofences[$token][$devid] = $this->getGeofences($devid);
								// proxims
								if (!array_key_exists($token, $proxims)) {
									$proxims[$token] = [];
								}
								if (!array_key_exists($devid, $proxims[$token])) {
									$proxims[$token][$devid] = [];
								}
								$proxims[$token][$devid] = $this->getProxims($devid);
							}

							$sqlget = '
								SELECT id, deviceid, lat, lon, timestamp, accuracy, satellites,
									   altitude, batterylevel, useragent, speed, bearing
								FROM *PREFIX*phonetrack_points
								WHERE deviceid=' . $this->db_quote_escape_string($devid) . ' ' .
								$firstLastSQL . ' ' .
								$settingsTimeFilterSQL . ' ';
							// get max number of points to load
							if (is_numeric($nbpointsload)) {
								$sqlget .= 'ORDER BY timestamp DESC LIMIT ' . intval($nbpointsload);
							} else {
								$sqlget .= 'ORDER BY timestamp DESC';
							}
							$req = $this->dbConnection->prepare($sqlget);
							$req->execute();
							while ($row = $req->fetch()) {
								$entry = [
									intval($row['id']),
									floatval($row['lat']),
									floatval($row['lon']),
									intval($row['timestamp']),
									is_numeric($row['accuracy']) ? floatval($row['accuracy']) : null,
									is_numeric($row['satellites']) ? intval($row['satellites']) : null,
									is_numeric($row['altitude']) ? floatval($row['altitude']) : null,
									is_numeric($row['batterylevel']) ? floatval($row['batterylevel']) : null,
									$row['useragent'],
									is_numeric($row['speed']) ? floatval($row['speed']) : null,
									is_numeric($row['bearing']) ? floatval($row['bearing']) : null
								];
								array_unshift($resultDevArray, $entry);
							}
							$req->closeCursor();
							if (count($resultDevArray) > 0) {
								$result[$token][$devid] = $resultDevArray;
							} else {
								// if device has no new point and no last time
								// it means it was probably reserved : we don't give its name
								if (!is_array($lastTime) || !array_key_exists($devid, $lastTime)) {
									unset($names[$dbtoken][$devid]);
									unset($aliases[$dbtoken][$devid]);
									unset($colors[$dbtoken][$devid]);
									unset($shapes[$dbtoken][$devid]);
									unset($geofences[$dbtoken][$devid]);
								}
							}
						}
					}
				}
			}
		}

		return new DataResponse([
			'sessions' => $result,
			'colors' => $colors,
			'shapes' => $shapes,
			'names' => $names,
			'aliases' => $aliases,
			'geofences' => $geofences,
			'proxims' => $proxims
		]);
	}

	private function getGeofences($devid) {
		$geofences = [];
		$sqlfences = '
			SELECT id, name, latmin, latmax, lonmin,
				   lonmax, urlenter, urlleave,
				   urlenterpost, urlleavepost,
				   sendemail, emailaddr, sendnotif
			FROM *PREFIX*phonetrack_geofences
			WHERE deviceid=' . $this->db_quote_escape_string($devid) . ' ;';
		$req = $this->dbConnection->prepare($sqlfences);
		$req->execute();
		while ($row = $req->fetch()) {
			$fence = [];
			foreach ($row as $k => $v) {
				$fence[$k] = $v;
			}
			array_push($geofences, $fence);
		}
		$req->closeCursor();
		return $geofences;
	}

	private function getProxims($devid) {
		$proxims = [];
		$sqlproxims = '
			SELECT *PREFIX*phonetrack_proxims.id AS id, deviceid2, lowlimit, highlimit,
				urlclose, urlfar,
				urlclosepost, urlfarpost,
				sendemail, emailaddr, sendnotif,
				*PREFIX*phonetrack_devices.name AS dname2,
				*PREFIX*phonetrack_sessions.name AS sname2
			FROM *PREFIX*phonetrack_proxims
			INNER JOIN *PREFIX*phonetrack_devices ON deviceid2=*PREFIX*phonetrack_devices.id
			INNER JOIN *PREFIX*phonetrack_sessions ON *PREFIX*phonetrack_devices.sessionid=*PREFIX*phonetrack_sessions.token
			WHERE deviceid1=' . $this->db_quote_escape_string($devid) . ' ;';
		$req = $this->dbConnection->prepare($sqlproxims);
		$req->execute();
		while ($row = $req->fetch()) {
			$proxim = [];
			foreach ($row as $k => $v) {
				$proxim[$k] = $v;
			}
			array_push($proxims, $proxim);
		}
		$req->closeCursor();
		return $proxims;
	}

	private function isSessionPublic($token) {
		$dbpublic = '';
		$sqlget = '
			SELECT token, public
			FROM *PREFIX*phonetrack_sessions
			WHERE token=' . $this->db_quote_escape_string($token) . ' ;';
		$req = $this->dbConnection->prepare($sqlget);
		$req->execute();
		while ($row = $req->fetch()) {
			$dbtoken = $row['token'];
			$dbpublic = $row['public'];
		}
		$req->closeCursor();

		return ($dbpublic === '1' || $dbpublic === 1);
	}

	/**
	 * called by publicWebLog page
	 */
	#[NoAdminRequired]
	#[PublicPage]
	public function publicWebLogTrack($sessions) {
		$result = [];
		$colors = [];
		$shapes = [];
		$names = [];
		$aliases = [];
		foreach ($sessions as $session) {
			$token = $session[0];
			if ($this->isSessionPublic($token)) {
				$lastTime = $session[1];
				$firstTime = $session[2];

				// check if session exists
				$dbtoken = null;
				$sqlget = '
					SELECT token
					FROM *PREFIX*phonetrack_sessions
					WHERE token=' . $this->db_quote_escape_string($token) . ' ;';
				$req = $this->dbConnection->prepare($sqlget);
				$req->execute();
				while ($row = $req->fetch()) {
					$dbtoken = $row['token'];
				}
				$req->closeCursor();

				// session exists
				if ($dbtoken !== null) {
					// get list of devices
					$devices = [];
					$sqldev = '
						SELECT id
						FROM *PREFIX*phonetrack_devices
						WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . ' ;';
					$req = $this->dbConnection->prepare($sqldev);
					$req->execute();
					while ($row = $req->fetch()) {
						array_push($devices, intval($row['id']));
					}
					$req->closeCursor();

					// get the coords for each device
					$result[$token] = [];

					foreach ($devices as $devid) {
						$resultDevArray = [];

						$firstDeviceTimeSQL = '';
						if (is_array($firstTime) && array_key_exists($devid, $firstTime)) {
							$firstDeviceTime = $firstTime[$devid];
							$firstDeviceTimeSQL = 'timestamp<' . $this->db_quote_escape_string($firstDeviceTime);
						}

						$lastDeviceTime = 0;
						$lastDeviceTimeSQL = '';
						if (is_array($lastTime) && array_key_exists($devid, $lastTime)) {
							$lastDeviceTime = $lastTime[$devid];
							$lastDeviceTimeSQL = 'timestamp>' . $this->db_quote_escape_string($lastDeviceTime);
						}
						// build SQL condition for first/last
						$firstLastSQL = '';
						if ($firstDeviceTimeSQL !== '') {
							if ($lastDeviceTimeSQL !== '') {
								$firstLastSQL = 'AND (' . $firstDeviceTimeSQL . ' OR ' . $lastDeviceTimeSQL . ') ';
							} else {
								$firstLastSQL = 'AND ' . $firstDeviceTimeSQL . ' ';
							}
						} elseif ($lastDeviceTimeSQL !== '') {
							$firstLastSQL = 'AND ' . $lastDeviceTimeSQL . ' ';
						}
						// we give color (first point given)
						else {
							$sqlcolor = '
								SELECT color, name, alias, shape
								FROM *PREFIX*phonetrack_devices
								WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
									  AND id=' . $this->db_quote_escape_string($devid) . ' ;';
							$req = $this->dbConnection->prepare($sqlcolor);
							$req->execute();
							$col = '';
							while ($row = $req->fetch()) {
								$col = $row['color'];
								$shape = $row['shape'];
								$name = $row['name'];
								$alias = $row['alias'];
							}
							$req->closeCursor();
							if (!array_key_exists($dbtoken, $shapes)) {
								$shapes[$dbtoken] = [];
							}
							$shapes[$dbtoken][$devid] = $shape;
							if (!array_key_exists($dbtoken, $colors)) {
								$colors[$dbtoken] = [];
							}
							$colors[$dbtoken][$devid] = $col;
							if (!array_key_exists($dbtoken, $names)) {
								$names[$dbtoken] = [];
							}
							$names[$dbtoken][$devid] = $name;
							if (!array_key_exists($dbtoken, $aliases)) {
								$aliases[$dbtoken] = [];
							}
							$aliases[$dbtoken][$devid] = $alias;
						}

						$sqlget = '
							SELECT id, deviceid, lat, lon,
								   timestamp, accuracy, satellites,
								   altitude, batterylevel,
								   useragent, speed, bearing
							FROM *PREFIX*phonetrack_points
							WHERE deviceid=' . $this->db_quote_escape_string($devid) . ' ' .
							$firstLastSQL . '
							ORDER BY timestamp DESC LIMIT 1000 ;';
						$req = $this->dbConnection->prepare($sqlget);
						$req->execute();
						while ($row = $req->fetch()) {
							$entry = [
								intval($row['id']),
								floatval($row['lat']),
								floatval($row['lon']),
								intval($row['timestamp']),
								is_numeric($row['accuracy']) ? floatval($row['accuracy']) : null,
								is_numeric($row['satellites']) ? intval($row['satellites']) : null,
								is_numeric($row['altitude']) ? floatval($row['altitude']) : null,
								is_numeric($row['batterylevel']) ? floatval($row['batterylevel']) : null,
								$row['useragent'],
								is_numeric($row['speed']) ? floatval($row['speed']) : null,
								is_numeric($row['bearing']) ? floatval($row['bearing']) : null
							];
							array_unshift($resultDevArray, $entry);
						}
						$req->closeCursor();
						if (count($resultDevArray) > 0) {
							$result[$token][$devid] = $resultDevArray;
						} else {
							// if device has no new point and no last time
							// it means it was probably reserved : we don't give its name
							if (!is_array($lastTime) || !array_key_exists($devid, $lastTime)) {
								unset($names[$dbtoken][$devid]);
								unset($aliases[$dbtoken][$devid]);
								unset($colors[$dbtoken][$devid]);
								unset($shapes[$dbtoken][$devid]);
							}
						}
					}
				}
			}
		}

		return new DataResponse([
			'sessions' => $result,
			'colors' => $colors,
			'shapes' => $shapes,
			'names' => $names,
			'aliases' => $aliases
		]);
	}

	/**
	 * called by publicSessionView page
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function publicViewTrack($sessions) {
		$result = [];
		$colors = [];
		$shapes = [];
		$names = [];
		$aliases = [];
		foreach ($sessions as $session) {
			$publicviewtoken = $session[0];
			$lastTime = $session[1];
			$firstTime = $session[2];
			$nbPointsLoad = 1000;
			if (count($session) > 3) {
				$nbPointsLoad = $session[3];
			}
			$lastposonly = 0;
			$geofencify = 0;

			// check if session exists
			$dbtoken = null;
			$dbpublicviewtoken = null;
			$dbpublic = null;
			$filters = null;
			$deviceNameRestriction = '';
			$sqlget = '
				SELECT publicviewtoken, token, public
				FROM *PREFIX*phonetrack_sessions
				WHERE publicviewtoken=' . $this->db_quote_escape_string($publicviewtoken) . ' ;';
			$req = $this->dbConnection->prepare($sqlget);
			$req->execute();
			while ($row = $req->fetch()) {
				$dbpublicviewtoken = $row['publicviewtoken'];
				$dbtoken = $row['token'];
				$dbpublic = intval($row['public']);
			}
			$req->closeCursor();
			if ($dbpublic !== 1) {
				$dbpublicviewtoken = null;
			}

			// there is no session with this publicviewtoken
			// check if there is a public share with the sharetoken
			if ($dbpublicviewtoken === null) {
				$sqlget = '
					SELECT sharetoken, sessionid, filters,
						   devicename, lastposonly, geofencify
					FROM *PREFIX*phonetrack_pubshares
					WHERE sharetoken=' . $this->db_quote_escape_string($publicviewtoken) . ' ;';
				$req = $this->dbConnection->prepare($sqlget);
				$req->execute();
				while ($row = $req->fetch()) {
					$dbpublicviewtoken = $row['sharetoken'];
					$dbtoken = $row['sessionid'];
					$filters = json_decode($row['filters'], true);
					$lastposonly = $row['lastposonly'];
					$geofencify = $row['geofencify'];
					if ($row['devicename'] !== null && $row['devicename'] !== '') {
						$deviceNameRestriction = ' AND name=' . $this->db_quote_escape_string($row['devicename']) . ' ';
					}
				}
				$req->closeCursor();
			}

			// session exists and is public or shared by public share
			if ($dbpublicviewtoken !== null) {
				// get list of devices
				$devices = [];
				$sqldev = '
					SELECT id
					FROM *PREFIX*phonetrack_devices
					WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . ' ' .
					$deviceNameRestriction . ' ;';
				$req = $this->dbConnection->prepare($sqldev);
				$req->execute();
				while ($row = $req->fetch()) {
					array_push($devices, intval($row['id']));
				}
				$req->closeCursor();

				// get the coords for each device
				$result[$dbpublicviewtoken] = [];

				foreach ($devices as $devid) {
					$resultDevArray = [];

					$firstDeviceTimeSQL = '';
					if (is_array($firstTime) && array_key_exists($devid, $firstTime)) {
						$firstDeviceTime = $firstTime[$devid];
						$firstDeviceTimeSQL = 'timestamp<' . $this->db_quote_escape_string($firstDeviceTime);
					}

					$lastDeviceTime = 0;
					$lastDeviceTimeSQL = '';
					if (is_array($lastTime) && array_key_exists($devid, $lastTime)) {
						$lastDeviceTime = $lastTime[$devid];
						$lastDeviceTimeSQL = 'timestamp>' . $this->db_quote_escape_string($lastDeviceTime);
					}
					// build SQL condition for first/last
					$firstLastSQL = '';
					if ($firstDeviceTimeSQL !== '') {
						if ($lastDeviceTimeSQL !== '') {
							$firstLastSQL = 'AND (' . $firstDeviceTimeSQL . ' OR ' . $lastDeviceTimeSQL . ') ';
						} else {
							$firstLastSQL = 'AND ' . $firstDeviceTimeSQL . ' ';
						}
					} elseif ($lastDeviceTimeSQL !== '') {
						$firstLastSQL = 'AND ' . $lastDeviceTimeSQL . ' ';
					}
					// we give color (first point given)
					else {
						$sqlcolor = '
							SELECT color, name, alias, shape
							FROM *PREFIX*phonetrack_devices
							WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
								  AND id=' . $this->db_quote_escape_string($devid) . ' ;';
						$req = $this->dbConnection->prepare($sqlcolor);
						$req->execute();
						$col = '';
						while ($row = $req->fetch()) {
							$col = $row['color'];
							$shape = $row['shape'];
							$name = $row['name'];
							$alias = $row['alias'];
						}
						$req->closeCursor();
						if (!array_key_exists($dbpublicviewtoken, $shapes)) {
							$shapes[$dbpublicviewtoken] = [];
						}
						$shapes[$dbpublicviewtoken][$devid] = $shape;
						if (!array_key_exists($dbpublicviewtoken, $colors)) {
							$colors[$dbpublicviewtoken] = [];
						}
						$colors[$dbpublicviewtoken][$devid] = $col;
						if (!array_key_exists($dbpublicviewtoken, $names)) {
							$names[$dbpublicviewtoken] = [];
						}
						$names[$dbpublicviewtoken][$devid] = $name;
						if (!array_key_exists($dbpublicviewtoken, $aliases)) {
							$aliases[$dbpublicviewtoken] = [];
						}
						$aliases[$dbpublicviewtoken][$devid] = $alias;
					}


					$sqlget = '
						SELECT id, deviceid, lat, lon,
							   timestamp, accuracy, satellites,
							   altitude, batterylevel, useragent,
							   speed, bearing
						FROM *PREFIX*phonetrack_points
						WHERE deviceid=' . $this->db_quote_escape_string($devid) . ' ' .
						$firstLastSQL . ' ';
					if (intval($lastposonly) === 0) {
						if (intval($nbPointsLoad) === 0) {
							$sqlget .= 'ORDER BY timestamp DESC ;';
						} else {
							$sqlget .= 'ORDER BY timestamp DESC LIMIT ' . intval($nbPointsLoad) . ' ;';
						}
					} else {
						$sqlget .= 'ORDER BY timestamp DESC LIMIT 1 ;';
					}
					$req = $this->dbConnection->prepare($sqlget);
					$req->execute();
					while ($row = $req->fetch()) {
						if ($filters === null || $this->filterPoint($row, $filters)) {
							$entry = [
								(int)$row['id'],
								(float)$row['lat'],
								(float)$row['lon'],
								(int)$row['timestamp'],
								is_numeric($row['accuracy']) ? (float)$row['accuracy'] : null,
								is_numeric($row['satellites']) ? (int)$row['satellites'] : null,
								is_numeric($row['altitude']) ? (float)$row['altitude'] : null,
								is_numeric($row['batterylevel']) ? (float)$row['batterylevel'] : null,
								$row['useragent'],
								is_numeric($row['speed']) ? (float)$row['speed'] : null,
								is_numeric($row['bearing']) ? (float)$row['bearing'] : null
							];
							array_unshift($resultDevArray, $entry);
						}
					}
					$req->closeCursor();
					if (count($resultDevArray) > 0) {
						$result[$dbpublicviewtoken][$devid] = $resultDevArray;
					} else {
						// if device has no new point and no last time
						// it means it was probably reserved : we don't give its name
						if (!is_array($lastTime) || !array_key_exists($devid, $lastTime)) {
							unset($names[$dbpublicviewtoken][$devid]);
							unset($aliases[$dbpublicviewtoken][$devid]);
							unset($colors[$dbpublicviewtoken][$devid]);
							unset($shapes[$dbpublicviewtoken][$devid]);
						}
					}
				}
				if (intval($geofencify) !== 0) {
					$result[$dbpublicviewtoken] = $this->geofencify($dbtoken, $dbpublicviewtoken, $result[$dbpublicviewtoken]);
				}
			}
		}

		return new DataResponse([
			'sessions' => $result,
			'colors' => $colors,
			'shapes' => $shapes,
			'names' => $names,
			'aliases' => $aliases
		]);
	}

	private function getDeviceFencesCenter($devid) {
		$fences = [];
		$sqlget = '
			SELECT latmin, lonmin, latmax, lonmax, name
			FROM *PREFIX*phonetrack_geofences
			WHERE deviceid=' . $this->db_quote_escape_string($devid) . ' ;';
		$req = $this->dbConnection->prepare($sqlget);
		$req->execute();
		while ($row = $req->fetch()) {
			$lat = (floatval($row['latmin']) + floatval($row['latmax'])) / 2.0;
			$lon = (floatval($row['lonmin']) + floatval($row['lonmax'])) / 2.0;
			$fences[$row['name']] = [
				$lat, $lon, floatval($row['latmin']), floatval($row['latmax']),
				floatval($row['lonmin']), floatval($row['lonmax'])
			];
		}
		return $fences;
	}

	private function geofencify($token, $ptk, $devtab) {
		$result = [];
		if (count($devtab) > 0) {
			foreach ($devtab as $devid => $entries) {
				$geofencesCenter = $this->getDeviceFencesCenter($devid);
				if (count($geofencesCenter) > 0) {
					$result[$devid] = [];
					foreach ($entries as $entry) {
						$sentry = $this->geofencifyPoint($entry, $geofencesCenter);
						if ($sentry !== null) {
							array_push($result[$devid], $sentry);
						}
					}
					if (count($result[$devid]) === 0) {
						unset($result[$devid]);
					}
				}
			}
		}
		return $result;
	}

	private function geofencifyPoint($entry, $geofencesCenter) {
		$nearestName = null;
		$distMin = null;
		foreach ($geofencesCenter as $name => $coords) {
			// if point is inside geofencing zone
			if ($entry[1] >= $coords[2]
				&& $entry[1] <= $coords[3]
				&& $entry[2] >= $coords[4]
				&& $entry[2] <= $coords[5]
			) {
				$dist = distance($coords[0], $coords[1], $entry[1], $entry[2]);
				if ($nearestName === null || $dist < $distMin) {
					$distMin = $dist;
					$nearestName = $name;
				}
			}
		}
		if ($nearestName !== null) {
			return [
				$entry[0], $geofencesCenter[$nearestName][0], $geofencesCenter[$nearestName][1],
				$entry[3], null, null, null, null, null, null, null
			];
		} else {
			return null;
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function publicSessionWatch($publicviewtoken) {
		if ($publicviewtoken !== '') {
			$lastposonly = 0;
			// check if a public session has this publicviewtoken
			$sqlchk = '
				SELECT token, public
				FROM *PREFIX*phonetrack_sessions
				WHERE publicviewtoken=' . $this->db_quote_escape_string($publicviewtoken) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$req->execute();
			$dbtoken = null;
			$dbpublic = null;
			while ($row = $req->fetch()) {
				$dbtoken = $row['token'];
				$dbpublic = intval($row['public']);
				break;
			}
			$req->closeCursor();

			if ($dbtoken !== null && $dbpublic === 1) {
				// we give publicWebLog the real session id but then, the share token is used in the JS
				$response = $this->publicWebLog($dbtoken, '');
				if (!is_string($response)) {
					$response->setHeaderDetails($this->trans->t('Watch session'));
				}
				return $response;
			} else {
				// check if a public session has this publicviewtoken
				$sqlchk = '
					SELECT sessionid, sharetoken, lastposonly, filters
					FROM *PREFIX*phonetrack_pubshares
					WHERE sharetoken=' . $this->db_quote_escape_string($publicviewtoken) . ' ;';
				$req = $this->dbConnection->prepare($sqlchk);
				$req->execute();
				$dbtoken = null;
				$dbpublic = null;
				$filters = '';
				while ($row = $req->fetch()) {
					$dbtoken = $row['sessionid'];
					$lastposonly = $row['lastposonly'];
					$filters = $row['filters'];
					break;
				}
				$req->closeCursor();

				if ($dbtoken !== null) {
					// we give publicWebLog the real session id but then, the share token is used in the JS
					$response = $this->publicWebLog($dbtoken, '', $lastposonly, $filters);
					if (!is_string($response)) {
						$response->setHeaderDetails($this->trans->t('Watch session'));
					}
					return $response;
				} else {
					return 'Session does not exist or is not public';
				}
			}
		} else {
			return 'Session does not exist or is not public';
		}
	}

	/**
	 * lastposonly is given to the page, it makes the page delete all points but the last for each device
	 **/
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	public function publicWebLog($token, $devicename, $lastposonly = 0, $filters = '') {
		if ($token !== '') {
			// check if session exists
			$sqlchk = '
				SELECT name, public
				FROM *PREFIX*phonetrack_sessions
				WHERE token=' . $this->db_quote_escape_string($token) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$res = $req->execute();
			$dbname = null;
			$dbPublic = null;
			while ($row = $res->fetch()) {
				$dbname = $row['name'];
				$dbPublic = $row['public'];
				break;
			}
			$res->closeCursor();

			if ($dbname !== null && intval($dbPublic) === 1) {
			} else {
				return 'Session does not exist or is not public';
			}
		} else {
			return 'Session does not exist or is not public';
		}

		require_once('tileservers.php');
		$params = [
			'username' => '',
			'basetileservers' => $baseTileServers,
			'usertileservers' => [],
			'usermapboxtileservers' => [],
			'useroverlayservers' => [],
			'usertileserverswms' => [],
			'useroverlayserverswms' => [],
			'publicsessionname' => $dbname,
			'lastposonly' => $lastposonly,
			'sharefilters' => $filters,
			'filtersBookmarks' => [],
			'phonetrack_version' => $this->appManager->getAppVersion(Application::APP_ID),
		];
		$response = new PublicTemplateResponse(Application::APP_ID, 'main', $params);
		$response->setHeaderTitle($this->trans->t('PhoneTrack public access'));
		$response->setHeaderDetails($this->trans->t('Log to session %s', [$dbname]));
		$response->setFooterVisible(false);
		// $response->setHeaders(['X-Frame-Options'=>'']);
		$csp = new ContentSecurityPolicy();
		//		$csp
		//			->addAllowedImageDomain('*')
		//			->addAllowedMediaDomain('*')
		//			->addAllowedChildSrcDomain('*')
		//			->addAllowedFrameDomain('*')
		//			->addAllowedWorkerSrcDomain('*')
		//			->addAllowedObjectDomain('*')
		//			->addAllowedScriptDomain('*')
		//			->allowEvalScript(true)
		//			->addAllowedConnectDomain('*');
		$csp->addAllowedFrameAncestorDomain('*');

		$tsUrls = array_map(static function (array $ts) {
			return $ts['url'];
		}, $baseTileServers);
		$this->addCspForTiles($csp, $tsUrls);

		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	#[NoAdminRequired]
	public function importSession(string $path): DataResponse {
		$done = 1;
		$userFolder = $this->root->getUserFolder($this->userId);
		$cleanPath = str_replace(['../', '..\\'], '', $path);

		$file = null;
		$sessionName = null;
		$token = null;
		$devices = null;
		$publicViewToken = null;
		if ($userFolder->nodeExists($cleanPath)) {
			$file = $userFolder->get($cleanPath);
			if ($file instanceof File && $file->isReadable()) {
				if (str_ends_with($file->getName(), '.gpx') || str_ends_with($file->getName(), '.GPX')) {
					$sessionName = str_replace(['.gpx', '.GPX'], '', $file->getName());
					$res = $this->createSession($sessionName);
					$response = $res->getData();
					if ($response['done'] === 1) {
						$token = $response['token'];
						$publicViewToken = $response['publicviewtoken'];
						$done = $this->readGpxImportPoints($file, $token);
					} else {
						$done = 2;
					}
				} elseif (str_ends_with($file->getName(), '.kml') || str_ends_with($file->getName(), '.KML')) {
					$sessionName = str_replace(['.kml', '.KML'], '', $file->getName());
					$res = $this->createSession($sessionName);
					$response = $res->getData();
					if ($response['done'] === 1) {
						$token = $response['token'];
						$publicViewToken = $response['publicviewtoken'];
						$done = $this->readKmlImportPoints($file, $token);
					} else {
						$done = 2;
					}
				} elseif (str_ends_with($file->getName(), '.json') || str_ends_with($file->getName(), '.JSON')) {
					$sessionName = str_replace(['.json', '.JSON'], '', $file->getName());
					$res = $this->createSession($sessionName);
					$response = $res->getData();
					if ($response['done'] === 1) {
						$token = $response['token'];
						$publicViewToken = $response['publicviewtoken'];
						$done = $this->readJsonImportPoints($file, $token);
					} else {
						$done = 2;
					}
				}
			} else {
				$done = 3;
			}
		} else {
			$done = 4;
		}

		// if done is not 1, 3 or 4 : delete session
		if ($done !== 1 && $done !== 3 && $done !== 4) {
			$this->deleteSession($token);
		}
		$devices = [];
		if ($done === 1) {
			$devices = $this->getDevices($token);
		}

		return new DataResponse([
			'done' => $done,
			'token' => $token,
			'devices' => $devices,
			'sessionName' => $sessionName,
			'publicviewtoken' => $publicViewToken,
		]);
	}

	private function gpxStartElement(XMLParser $parser, string $name, array $attrs): void {
		//$points, array($lat, $lon, $ele, $timestamp, $acc, $bat, $sat, $ua, $speed, $bearing)
		$this->currentXmlTag = $name;
		if ($name === 'TRK') {
			$this->importDevName = 'device' . $this->trackIndex;
			$this->pointIndex = 1;
			$this->currentPointList = [];
		} elseif ($name === 'TRKPT') {
			$this->currentPoint = [null, null, null, $this->pointIndex, null, null,  null, null, null, null];
			if (array_key_exists('LAT', $attrs)) {
				$this->currentPoint[0] = floatval($attrs['LAT']);
			}
			if (array_key_exists('LON', $attrs)) {
				$this->currentPoint[1] = floatval($attrs['LON']);
			}
		}
		//var_dump($attrs);
	}

	private function gpxEndElement(XMLParser $parser, string $name) {
		if ($name === 'TRK') {
			// log last track points
			if (count($this->currentPointList) > 0) {
				$this->logMultiple($this->importToken, $this->importDevName, $this->currentPointList);
			}
			$this->trackIndex++;
			unset($this->currentPointList);
		} elseif ($name === 'TRKPT') {
			// store track point
			array_push($this->currentPointList, $this->currentPoint);
			// if we have enough points, we log them and clean the points array
			if (count($this->currentPointList) >= 100) {
				$this->logMultiple($this->importToken, $this->importDevName, $this->currentPointList);
				unset($this->currentPointList);
				$this->currentPointList = [];
			}
			$this->pointIndex++;
		}
	}

	private function gpxDataElement(XMLParser $parser, string $data): void {
		//$points, array($lat, $lon, $ele, $timestamp, $acc, $bat, $sat, $ua, $speed, $bearing)
		$d = trim($data);
		if (!empty($d)) {
			if ($this->currentXmlTag === 'ELE') {
				$this->currentPoint[2] = floatval($d);
			} elseif ($this->currentXmlTag === 'SPEED') {
				$this->currentPoint[8] = floatval($d);
			} elseif ($this->currentXmlTag === 'SAT') {
				$this->currentPoint[6] = intval($d);
			} elseif ($this->currentXmlTag === 'COURSE') {
				$this->currentPoint[9] = floatval($d);
			} elseif ($this->currentXmlTag === 'USERAGENT') {
				$this->currentPoint[7] = $d;
			} elseif ($this->currentXmlTag === 'BATTERYLEVEL') {
				$this->currentPoint[5] = floatval($d);
			} elseif ($this->currentXmlTag === 'ACCURACY') {
				$this->currentPoint[4] = floatval($d);
			} elseif ($this->currentXmlTag === 'TIME') {
				$time = new DateTime($d);
				$timestamp = $time->getTimestamp();
				$this->currentPoint[3] = $timestamp;
			} elseif ($this->currentXmlTag === 'NAME') {
				$this->importDevName = $d;
			}
		}
	}

	private function readGpxImportPoints(File $gpxFile, string $token): int {
		$this->importToken = $token;
		$this->trackIndex = 1;
		$xml_parser = xml_parser_create();
		xml_set_object($xml_parser, $this);
		xml_set_element_handler($xml_parser, 'gpxStartElement', 'gpxEndElement');
		xml_set_character_data_handler($xml_parser, 'gpxDataElement');

		$fp = $gpxFile->fopen('r');

		while ($data = fread($fp, 4096000)) {
			//$this->logger->info('MEM USAGE '.memory_get_usage(), ['app' => $this->appName]);
			if (!xml_parse($xml_parser, $data, feof($fp))) {
				$this->logger->error(
					'Exception in ' . $gpxFile->getName() . ' parsing at line ' .
					  xml_get_current_line_number($xml_parser) . ' : ' .
					  xml_error_string(xml_get_error_code($xml_parser)),
					['app' => $this->appName]
				);
				return 5;
			}
		}
		fclose($fp);
		xml_parser_free($xml_parser);
		unset($xml_parser);
		if ($this->trackIndex === 1) {
			return 6;
		}
		return 1;
	}

	private function kmlStartElement(XMLParser $parser, string $name, array $attrs): void {
		//$points, array($lat, $lon, $ele, $timestamp, $acc, $bat, $sat, $ua, $speed, $bearing)
		$this->currentXmlTag = $name;
		if ($name === 'GX:TRACK') {
			if (array_key_exists('ID', $attrs)) {
				$this->importDevName = $attrs['ID'];
			} else {
				$this->importDevName = 'device' . $this->trackIndex;
			}
			$this->pointIndex = 1;
			$this->currentPointList = [];
		} elseif ($name === 'WHEN') {
			$this->currentPoint = [null, null, null, $this->pointIndex, null, null,  null, null, null, null];
		}
		//var_dump($attrs);
	}

	private function kmlEndElement(XMLParser $parser, string $name): void {
		if ($name === 'GX:TRACK') {
			// log last track points
			if (count($this->currentPointList) > 0) {
				$this->logMultiple($this->importToken, $this->importDevName, $this->currentPointList);
			}
			$this->trackIndex++;
			unset($this->currentPointList);
		} elseif ($name === 'GX:COORD') {
			// store track point
			$this->currentPointList[] = $this->currentPoint;
			// if we have enough points, we log them and clean the points array
			if (count($this->currentPointList) >= 100) {
				$this->logMultiple($this->importToken, $this->importDevName, $this->currentPointList);
				unset($this->currentPointList);
				$this->currentPointList = [];
			}
			$this->pointIndex++;
		}
	}

	private function kmlDataElement(XMLParser $parser, string $data) {
		//$points, array($lat, $lon, $ele, $timestamp, $acc, $bat, $sat, $ua, $speed, $bearing)
		$d = trim($data);
		if (!empty($d)) {
			if ($this->currentXmlTag === 'WHEN') {
				$time = new DateTime($d);
				$timestamp = $time->getTimestamp();
				$this->currentPoint[3] = $timestamp;
			} elseif ($this->currentXmlTag === 'GX:COORD') {
				$spl = explode(' ', $d);
				if (count($spl) > 1) {
					$this->currentPoint[0] = floatval($spl[1]);
					$this->currentPoint[1] = floatval($spl[0]);
					if (count($spl) > 2) {
						$this->currentPoint[2] = floatval($spl[2]);
					}
				}
			}
		}
	}

	private function readKmlImportPoints(File $kmlFile, string $token): int {
		$this->importToken = $token;
		$this->trackIndex = 1;
		$xml_parser = xml_parser_create();
		xml_set_object($xml_parser, $this);
		xml_set_element_handler($xml_parser, 'kmlStartElement', 'kmlEndElement');
		xml_set_character_data_handler($xml_parser, 'kmlDataElement');

		$fp = $kmlFile->fopen('r');

		while ($data = fread($fp, 4096000)) {
			if (!xml_parse($xml_parser, $data, feof($fp))) {
				$this->logger->error(
					'Exception in ' . $kmlFile->getName() . ' parsing at line ' .
					  xml_get_current_line_number($xml_parser) . ' : ' .
					  xml_error_string(xml_get_error_code($xml_parser)),
					['app' => $this->appName]
				);
				return 5;
			}
		}
		fclose($fp);
		xml_parser_free($xml_parser);
		if ($this->trackIndex === 1) {
			return 6;
		}
		return 1;
	}

	private function readJsonImportPoints(File $jsonFile, string $token): int {
		$importDevName = 'importedDevice';
		$jsonArray = json_decode($jsonFile->getContent(), true);

		$currentPointList = [];
		if (array_key_exists('locations', $jsonArray) && is_array($jsonArray['locations'])) {
			foreach ($jsonArray['locations'] as $loc) {
				// get point info
				//$points, array($lat, $lon, $ele, $timestamp, $acc, $bat, $sat, $ua, $speed, $bearing)
				$point = [null, null, null, null, null, null,  null, null, null, null];
				if (array_key_exists('timestampMs', $loc) && is_numeric($loc['timestampMs'])
					&& array_key_exists('latitudeE7', $loc) && is_numeric($loc['latitudeE7'])
					&& array_key_exists('longitudeE7', $loc) && is_numeric($loc['longitudeE7'])) {
					$point[0] = floatval($loc['latitudeE7']);
					$point[1] = floatval($loc['longitudeE7']);
					if ($point[0] > 900000000) {
						$point[0] -= 4294967296.0;
					}
					if ($point[1] > 1800000000) {
						$point[1] -= 4294967296.0;
					}
					$point[0] /= 10000000.0;
					$point[1] /= 10000000.0;
					$ts = intval(intval($loc['timestampMs']) / 1000);
					$point[3] = $ts;
					if (array_key_exists('latitudeE7', $loc) && is_numeric($loc['latitudeE7'])) {
						$point[4] = $loc['accuracy'];
					}
				}
				// add point
				$currentPointList[] = $point;
				if (count($currentPointList) >= 100) {
					$this->logMultiple($token, $importDevName, $currentPointList);
					unset($currentPointList);
					$currentPointList = [];
				}
			}
			if (count($currentPointList) > 0) {
				$this->logMultiple($token, $importDevName, $currentPointList);
			}
		}

		return 1;
	}

	#[NoAdminRequired]
	public function export(string $name, string $token, string $target, string $username = '', ?array $filterArray = null) {
		$warning = 0;
		$done = false;
		if ($this->userId !== null && $this->userId !== '') {
			$userId = $this->userId;
			$doneAndWarning = $this->sessionService->export($name, $token, $target, $userId, $filterArray);
			$done = $doneAndWarning[0];
			$warning = $doneAndWarning[1];
		}

		return new DataResponse([
			'done' => $done,
			'warning' => $warning,
		]);
	}

	private function filterPoint($p, $fArray): bool {
		return (
			(!array_key_exists('tsmin', $fArray) || intval($p['timestamp']) >= $fArray['tsmin'])
			and (!array_key_exists('tsmax', $fArray) || intval($p['timestamp']) <= $fArray['tsmax'])
			and (!array_key_exists('elevationmax', $fArray) || intval($p['altitude']) <= $fArray['elevationmax'])
			and (!array_key_exists('elevationmin', $fArray) || intval($p['altitude']) >= $fArray['elevationmin'])
			and (!array_key_exists('accuracymax', $fArray) || intval($p['accuracy']) <= $fArray['accuracymax'])
			and (!array_key_exists('accuracymin', $fArray) || intval($p['accuracy']) >= $fArray['accuracymin'])
			and (!array_key_exists('satellitesmax', $fArray) || intval($p['satellites']) <= $fArray['satellitesmax'])
			and (!array_key_exists('satellitesmin', $fArray) || intval($p['satellites']) >= $fArray['satellitesmin'])
			and (!array_key_exists('batterymax', $fArray) || intval($p['batterylevel']) <= $fArray['batterymax'])
			and (!array_key_exists('batterymin', $fArray) || intval($p['batterylevel']) >= $fArray['batterymin'])
			and (!array_key_exists('speedmax', $fArray) || floatval($p['speed']) <= $fArray['speedmax'])
			and (!array_key_exists('speedmin', $fArray) || floatval($p['speed']) >= $fArray['speedmin'])
			and (!array_key_exists('bearingmax', $fArray) || floatval($p['bearing']) <= $fArray['bearingmax'])
			and (!array_key_exists('bearingmin', $fArray) || floatval($p['bearing']) >= $fArray['bearingmin'])
		);
	}

	#[NoAdminRequired]
	public function addUserShare($token, $userId) {
		$ok = 0;
		// check if userId exists
		$userIds = [];
		foreach ($this->userManager->search('') as $u) {
			if ($u->getUID() !== $this->userId) {
				array_push($userIds, $u->getUID());
			}
		}
		if ($userId !== '' && in_array($userId, $userIds)) {
			// check if session exists and owned by current user
			$sqlchk = '
				SELECT name, token
				FROM *PREFIX*phonetrack_sessions
				WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
					  AND token=' . $this->db_quote_escape_string($token) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$req->execute();
			$dbname = null;
			$dbtoken = null;
			while ($row = $req->fetch()) {
				$dbname = $row['name'];
				$dbtoken = $row['token'];
				break;
			}
			$req->closeCursor();

			if ($token !== '' && $dbname !== null) {
				// check if user share exists
				$sqlchk = '
					SELECT username, sessionid
					FROM *PREFIX*phonetrack_shares
					WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
						  AND username=' . $this->db_quote_escape_string($userId) . ' ;';
				$req = $this->dbConnection->prepare($sqlchk);
				$req->execute();
				$dbusername = null;
				while ($row = $req->fetch()) {
					$dbusername = $row['username'];
					break;
				}
				$req->closeCursor();

				if ($dbusername === null) {
					// determine share token
					$sharetoken = md5('share' . $this->userId . $dbname . rand());

					// insert
					$sql = '
						INSERT INTO *PREFIX*phonetrack_shares
						(sessionid, username, sharetoken)
						VALUES (' .
							$this->db_quote_escape_string($dbtoken) . ',' .
							$this->db_quote_escape_string($userId) . ',' .
							$this->db_quote_escape_string($sharetoken) .
						') ;';
					$req = $this->dbConnection->prepare($sql);
					$req->execute();
					$req->closeCursor();

					$ok = 1;

					// activity
					$sessionObj = $this->sessionMapper->findByToken($dbtoken);
					$this->activityManager->triggerEvent(
						ActivityManager::PHONETRACK_OBJECT_SESSION,
						$sessionObj,
						ActivityManager::SUBJECT_SESSION_SHARE,
						[
							'who' => $userId,
							'type' => 'u',
						]
					);

					// SEND NOTIFICATION
					$manager = \OC::$server->getNotificationManager();
					$notification = $manager->createNotification();

					$acceptAction = $notification->createAction();
					$acceptAction->setLabel('accept')
						->setLink('/apps/phonetrack', 'GET');

					$declineAction = $notification->createAction();
					$declineAction->setLabel('decline')
						->setLink('/apps/phonetrack', 'GET');

					$notification->setApp(Application::APP_ID)
						->setUser($userId)
						->setDateTime(new \DateTime())
						->setObject('addusershare', $dbtoken)
						->setSubject('add_user_share', [$this->userId, $dbname])
						->addAction($acceptAction)
						->addAction($declineAction)
					;

					$manager->notify($notification);
				} else {
					$ok = 2;
				}
			} else {
				$ok = 3;
			}
		} else {
			$ok = 4;
		}

		return new DataResponse([
			'done' => $ok
		]);
	}

	/**
	 * Used to build public tokens with filters (then accessed by publicWatchUrl)
	 */
	#[NoAdminRequired]
	public function addPublicShare(string $token, bool $ignoreFilters = false) {
		$ok = 0;
		$filters = '';
		$sharetoken = '';
		// check if session exists and owned by current user
		$sqlchk = '
			SELECT name, token
			FROM *PREFIX*phonetrack_sessions
			WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
				  AND token=' . $this->db_quote_escape_string($token) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$req->execute();
		$dbname = null;
		$dbtoken = null;
		$sharetoken = null;
		while ($row = $req->fetch()) {
			$dbname = $row['name'];
			$dbtoken = $row['token'];
			break;
		}
		$req->closeCursor();

		if ($dbname !== null) {
			$filters = '{}';
			if (!$ignoreFilters) {
				$filterArray = $this->sessionService->getCurrentFilters($this->userId);
				if ($filterArray !== null) {
					$filters = json_encode($filterArray);
				}
			}

			// determine share token
			$sharetoken = md5('share' . $this->userId . $dbname . rand());

			// insert
			$sql = '
				INSERT INTO *PREFIX*phonetrack_pubshares
				(sessionid, sharetoken, filters)
				VALUES (' .
					$this->db_quote_escape_string($dbtoken) . ',' .
					$this->db_quote_escape_string($sharetoken) . ',' .
					$this->db_quote_escape_string($filters) .
				') ;';
			$req = $this->dbConnection->prepare($sql);
			$req->execute();
			$req->closeCursor();

			$ok = 1;
		} else {
			$ok = 3;
		}

		return new DataResponse([
			'done' => $ok,
			'sharetoken' => $sharetoken,
			'filters' => $filters
		]);
	}

	#[NoAdminRequired]
	public function deleteUserShare($token, $userId) {
		$ok = 0;
		// check if session exists
		$sqlchk = '
			SELECT name, token
			FROM *PREFIX*phonetrack_sessions
			WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
				  AND token=' . $this->db_quote_escape_string($token) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$req->execute();
		$dbname = null;
		$dbtoken = null;
		while ($row = $req->fetch()) {
			$dbname = $row['name'];
			$dbtoken = $row['token'];
			break;
		}
		$req->closeCursor();

		if ($token !== '' && $dbname !== null) {
			// check if user share exists
			$sqlchk = '
				SELECT username, sessionid
				FROM *PREFIX*phonetrack_shares
				WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
					  AND username=' . $this->db_quote_escape_string($userId) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$req->execute();
			$dbuserId = null;
			while ($row = $req->fetch()) {
				$dbuserId = $row['username'];
				break;
			}
			$req->closeCursor();

			if ($dbuserId !== null) {
				// activity
				$sessionObj = $this->sessionMapper->findByToken($dbtoken);
				$this->activityManager->triggerEvent(
					ActivityManager::PHONETRACK_OBJECT_SESSION,
					$sessionObj,
					ActivityManager::SUBJECT_SESSION_UNSHARE,
					[
						'who' => $userId,
						'type' => 'u',
					]
				);

				// delete
				$sqldel = '
					DELETE FROM *PREFIX*phonetrack_shares
					WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
						  AND username=' . $this->db_quote_escape_string($userId) . ' ;';
				$req = $this->dbConnection->prepare($sqldel);
				$req->execute();
				$req->closeCursor();

				$ok = 1;

				// SEND NOTIFICATION
				$manager = \OC::$server->getNotificationManager();
				$notification = $manager->createNotification();

				$acceptAction = $notification->createAction();
				$acceptAction->setLabel('accept')
					->setLink('/apps/phonetrack', 'GET');

				$declineAction = $notification->createAction();
				$declineAction->setLabel('decline')
					->setLink('/apps/phonetrack', 'GET');

				$notification->setApp(Application::APP_ID)
					->setUser($userId)
					->setDateTime(new \DateTime())
					->setObject('deleteusershare', $dbtoken)
					->setSubject('delete_user_share', [$this->userId, $dbname])
					->addAction($acceptAction)
					->addAction($declineAction)
				;

				$manager->notify($notification);
			} else {
				$ok = 2;
			}
		} else {
			$ok = 3;
		}

		return new DataResponse([
			'done' => $ok
		]);
	}

	#[NoAdminRequired]
	public function deletePublicShare($token, $sharetoken) {
		$ok = 0;
		// check if session exists
		$sqlchk = '
			SELECT name, token
			FROM *PREFIX*phonetrack_sessions
			WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
				  AND token=' . $this->db_quote_escape_string($token) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$req->execute();
		$dbname = null;
		$dbtoken = null;
		while ($row = $req->fetch()) {
			$dbname = $row['name'];
			$dbtoken = $row['token'];
			break;
		}
		$req->closeCursor();

		if ($dbname !== null) {
			// check if public share exists
			$sqlchk = '
				SELECT sharetoken, sessionid
				FROM *PREFIX*phonetrack_pubshares
				WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
					  AND sharetoken=' . $this->db_quote_escape_string($sharetoken) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$req->execute();
			$dbsharetoken = null;
			while ($row = $req->fetch()) {
				$dbsharetoken = $row['sharetoken'];
				break;
			}
			$req->closeCursor();

			if ($dbsharetoken !== null) {
				// delete
				$sqldel = '
					DELETE FROM *PREFIX*phonetrack_pubshares
					WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
						  AND sharetoken=' . $this->db_quote_escape_string($dbsharetoken) . ' ;';
				$req = $this->dbConnection->prepare($sqldel);
				$req->execute();
				$req->closeCursor();

				$ok = 1;
			} else {
				$ok = 2;
			}
		} else {
			$ok = 3;
		}

		return new DataResponse([
			'done' => $ok
		]);
	}

	#[NoAdminRequired]
	public function addNameReservation($token, $devicename) {
		$ok = 0;
		$nametoken = null;
		if ($devicename !== '' && $devicename !== null) {
			// check if session exists and owned by current user
			$sqlchk = '
				SELECT name, token
				FROM *PREFIX*phonetrack_sessions
				WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
					  AND token=' . $this->db_quote_escape_string($token) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$req->execute();
			$dbname = null;
			$dbtoken = null;
			while ($row = $req->fetch()) {
				$dbname = $row['name'];
				$dbtoken = $row['token'];
				break;
			}
			$req->closeCursor();

			if ($dbname !== null) {
				// check if name reservation exists
				$sqlchk = '
					SELECT name, sessionid, nametoken
					FROM *PREFIX*phonetrack_devices
					WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
						  AND name=' . $this->db_quote_escape_string($devicename) . ' ;';
				$req = $this->dbConnection->prepare($sqlchk);
				$req->execute();
				$dbdevicename = null;
				$dbdevicenametoken = null;
				while ($row = $req->fetch()) {
					$dbdevicename = $row['name'];
					$dbdevicenametoken = $row['nametoken'];
					break;
				}
				$req->closeCursor();

				// no entry in DB : we create it
				if ($dbdevicename === null) {
					// determine name token
					$nametoken = md5('nametoken' . $this->userId . $dbdevicename . rand());

					// insert
					$sql = '
						INSERT INTO *PREFIX*phonetrack_devices
						(sessionid, name, nametoken)
						VALUES (' .
						$this->db_quote_escape_string($dbtoken) . ',' .
						$this->db_quote_escape_string($devicename) . ',' .
						$this->db_quote_escape_string($nametoken) .
						') ;';
					$req = $this->dbConnection->prepare($sql);
					$req->execute();
					$req->closeCursor();

					$ok = 1;
				} elseif ($dbdevicenametoken === '' || $dbdevicenametoken === null) {
					// if there is an entry but no token, name is free to be reserved
					// so we update the entry
					$nametoken = md5('nametoken' . $this->userId . $dbdevicename . rand());
					$sqlupd = '
						UPDATE *PREFIX*phonetrack_devices
						SET nametoken=' . $this->db_quote_escape_string($nametoken) . '
						WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
							  AND name=' . $this->db_quote_escape_string($dbdevicename) . ' ;';
					$req = $this->dbConnection->prepare($sqlupd);
					$req->execute();
					$req->closeCursor();

					$ok = 1;
				} else {
					// the name is already reserved
					$ok = 2;
				}
			} else {
				$ok = 3;
			}
		} else {
			$ok = 4;
		}

		return new DataResponse([
			'done' => $ok,
			'nametoken' => $nametoken
		]);
	}

	#[NoAdminRequired]
	public function deleteNameReservation($token, $devicename) {
		$ok = 0;
		if ($devicename !== '' && $devicename !== null) {
			// check if session exists
			$sqlchk = '
				SELECT name, token
				FROM *PREFIX*phonetrack_sessions
				WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
					  AND token=' . $this->db_quote_escape_string($token) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$req->execute();
			$dbname = null;
			$dbtoken = null;
			while ($row = $req->fetch()) {
				$dbname = $row['name'];
				$dbtoken = $row['token'];
				break;
			}
			$req->closeCursor();

			if ($dbname !== null) {
				// check if name reservation exists
				$sqlchk = '
					SELECT name, sessionid, nametoken
					FROM *PREFIX*phonetrack_devices
					WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
						  AND name=' . $this->db_quote_escape_string($devicename) . ' ;';
				$req = $this->dbConnection->prepare($sqlchk);
				$req->execute();
				$dbdevicename = null;
				$dbdevicenametoken = null;
				while ($row = $req->fetch()) {
					$dbdevicename = $row['name'];
					$dbdevicenametoken = $row['nametoken'];
					break;
				}
				$req->closeCursor();

				// there is no such device
				if ($dbdevicename === null) {
					$ok = 2;
				} elseif ($dbdevicenametoken !== '' && $dbdevicenametoken !== null) {
					// the device exists and is has a nametoken
					// delete
					$sqlupd = '
						UPDATE *PREFIX*phonetrack_devices
						SET nametoken=' . $this->db_quote_escape_string('') . '
						WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
							  AND name=' . $this->db_quote_escape_string($dbdevicename) . ' ;';
					$req = $this->dbConnection->prepare($sqlupd);
					$req->execute();
					$req->closeCursor();

					$ok = 1;
				} else {
					$ok = 3;
				}
			} else {
				$ok = 4;
			}
		} else {
			$ok = 5;
		}

		return new DataResponse([
			'done' => $ok
		]);
	}

	private function sessionExists($token, $userid) {
		$sqlchk = '
			SELECT name
			FROM *PREFIX*phonetrack_sessions
			WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($userid) . '
				  AND token=' . $this->db_quote_escape_string($token) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$req->execute();
		$dbname = null;
		while ($row = $req->fetch()) {
			$dbname = $row['name'];
			break;
		}
		$req->closeCursor();

		return ($dbname !== null);
	}

	private function deviceExists($devid, $token) {
		$sqlchk = '
			SELECT name
			FROM *PREFIX*phonetrack_devices
			WHERE sessionid=' . $this->db_quote_escape_string($token) . '
				  AND id=' . $this->db_quote_escape_string($devid) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$req->execute();
		$dbname = null;
		while ($row = $req->fetch()) {
			$dbname = $row['name'];
			break;
		}
		$req->closeCursor();

		return ($dbname !== null);
	}

	#[NoAdminRequired]
	public function addFiltersBookmark($name, $filters) {
		$ok = 0;
		$bookid = null;
		// check there is no bookmark with this name already
		$sqlchk = '
			SELECT name
			FROM *PREFIX*phonetrack_filtersb
			WHERE name=' . $this->db_quote_escape_string($name) . '
				  AND username=' . $this->db_quote_escape_string($this->userId) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$req->execute();
		$dbbookname = null;
		while ($row = $req->fetch()) {
			$dbbookname = $row['name'];
			break;
		}
		$req->closeCursor();

		if ($dbbookname === null) {
			// insert
			$sql = '
				INSERT INTO *PREFIX*phonetrack_filtersb
				(username, name, filterjson)
				VALUES (' .
					 $this->db_quote_escape_string($this->userId) . ',' .
					 $this->db_quote_escape_string($name) . ',' .
					 $this->db_quote_escape_string($filters) . '
				) ;';
			$req = $this->dbConnection->prepare($sql);
			$req->execute();
			$req->closeCursor();

			$sqlchk = '
				SELECT id
				FROM *PREFIX*phonetrack_filtersb
				WHERE name=' . $this->db_quote_escape_string($name) . '
					  AND username=' . $this->db_quote_escape_string($this->userId) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$req->execute();
			while ($row = $req->fetch()) {
				$bookid = $row['id'];
				break;
			}
			$req->closeCursor();

			$ok = 1;
		} else {
			$ok = 2;
		}

		return new DataResponse([
			'done' => $ok,
			'bookid' => $bookid
		]);
	}

	#[NoAdminRequired]
	public function deleteFiltersBookmark($bookid) {
		$ok = 0;
		$sqldel = '
			DELETE FROM *PREFIX*phonetrack_filtersb
			WHERE id=' . $this->db_quote_escape_string($bookid) . '
				  AND username=' . $this->db_quote_escape_string($this->userId) . ' ;';
		$req = $this->dbConnection->prepare($sqldel);
		$req->execute();
		$req->closeCursor();

		$ok = 1;

		return new DataResponse([
			'done' => $ok
		]);
	}

	private function getFiltersBookmarks() {
		$res = [];
		$sql = '
			SELECT id, username, name, filterjson
			FROM *PREFIX*phonetrack_filtersb
			WHERE username=' . $this->db_quote_escape_string($this->userId) . ' ;';
		$req = $this->dbConnection->prepare($sql);
		$req->execute();
		while ($row = $req->fetch()) {
			$bookid = $row['id'];
			$name = $row['name'];
			$filters = $row['filterjson'];
			$res[$bookid] = [$name, $filters];
		}
		$req->closeCursor();

		return $res;
	}

	#[NoAdminRequired]
	public function addGeofence($token, $device, $fencename, $latmin, $latmax, $lonmin, $lonmax,
		$urlenter, $urlleave, $urlenterpost, $urlleavepost, $sendemail, $emailaddr, $sendnotif) {
		$ok = 0;
		$fenceid = null;
		if ($this->sessionExists($token, $this->userId) && $this->deviceExists($device, $token)) {
			// check there is no fence with this name already
			$sqlchk = '
				SELECT name
				FROM *PREFIX*phonetrack_geofences
				WHERE name=' . $this->db_quote_escape_string($fencename) . '
					  AND deviceid=' . $this->db_quote_escape_string($device) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$req->execute();
			$dbfencename = null;
			while ($row = $req->fetch()) {
				$dbfencename = $row['name'];
				break;
			}
			$req->closeCursor();

			if ($dbfencename === null) {
				// insert
				$sql = '
					INSERT INTO *PREFIX*phonetrack_geofences
					(name, deviceid, latmin, latmax,
					 lonmin, lonmax, urlenter, urlleave,
					 urlenterpost, urlleavepost, sendemail, emailaddr, sendnotif)
					VALUES (' .
						 $this->db_quote_escape_string($fencename) . ',' .
						 $this->db_quote_escape_string($device) . ',' .
						 $this->db_quote_escape_string(floatval($latmin)) . ',' .
						 $this->db_quote_escape_string(floatval($latmax)) . ',' .
						 $this->db_quote_escape_string(floatval($lonmin)) . ',' .
						 $this->db_quote_escape_string(floatval($lonmax)) . ',' .
						 $this->db_quote_escape_string($urlenter) . ',' .
						 $this->db_quote_escape_string($urlleave) . ',' .
						 $this->db_quote_escape_string(intval($urlenterpost)) . ',' .
						 $this->db_quote_escape_string(intval($urlleavepost)) . ',' .
						 $this->db_quote_escape_string(intval($sendemail)) . ',' .
						 $this->db_quote_escape_string($emailaddr) . ',' .
						 $this->db_quote_escape_string(intval($sendnotif)) . '
					) ;';
				$req = $this->dbConnection->prepare($sql);
				$req->execute();
				$req->closeCursor();

				$sqlchk = '
					SELECT id
					FROM *PREFIX*phonetrack_geofences
					WHERE name=' . $this->db_quote_escape_string($fencename) . '
						  AND deviceid=' . $this->db_quote_escape_string($device) . ' ;';
				$req = $this->dbConnection->prepare($sqlchk);
				$req->execute();
				while ($row = $req->fetch()) {
					$fenceid = $row['id'];
					break;
				}
				$req->closeCursor();

				$user = $this->userManager->get($this->userId);
				$userEmail = $user->getEMailAddress();
				if (!empty($userEmail)) {
					$ok = 1;
				} else {
					$ok = 4;
				}
			} else {
				$ok = 3;
			}
		} else {
			$ok = 2;
		}

		return new DataResponse([
			'done' => $ok,
			'fenceid' => $fenceid
		]);
	}

	#[NoAdminRequired]
	public function deleteGeofence($token, $device, $fenceid) {
		$ok = 0;
		if ($this->sessionExists($token, $this->userId) && $this->deviceExists($device, $token)) {
			$sqldel = '
				DELETE FROM *PREFIX*phonetrack_geofences
				WHERE deviceid=' . $this->db_quote_escape_string($device) . '
					  AND id=' . $this->db_quote_escape_string($fenceid) . ' ;';
			$req = $this->dbConnection->prepare($sqldel);
			$req->execute();
			$req->closeCursor();

			$ok = 1;
		} else {
			$ok = 2;
		}

		return new DataResponse([
			'done' => $ok
		]);
	}

	#[NoAdminRequired]
	public function addProxim($token, $device, $sid, $dname, $lowlimit, $highlimit,
		$urlclose, $urlfar, $urlclosepost, $urlfarpost, $sendemail, $emailaddr, $sendnotif) {
		$ok = 0;
		$proximid = null;
		$targetDeviceId = null;
		if ($this->sessionExists($token, $this->userId) && $this->deviceExists($device, $token)) {
			// check if target session id is owned by current user or if it's shared with him/her
			$targetSessionId = null;
			$ownsTargetSession = $this->sessionExists($sid, $this->userId);
			if ($ownsTargetSession) {
				$targetSessionId = $sid;
			} else {
				$sqlchk = '
					SELECT id, sessionid, sharetoken
					FROM *PREFIX*phonetrack_shares
					WHERE username=' . $this->db_quote_escape_string($this->userId) . '
						  AND sharetoken=' . $this->db_quote_escape_string($sid) . ' ;';
				$req = $this->dbConnection->prepare($sqlchk);
				$req->execute();
				while ($row = $req->fetch()) {
					$targetSessionId = $row['sessionid'];
					break;
				}
				$req->closeCursor();
			}

			if ($targetSessionId !== null) {
				// check if there is a device named like that in target session
				$sqlchk = '
					SELECT id
					FROM *PREFIX*phonetrack_devices
					WHERE name=' . $this->db_quote_escape_string($dname) . '
						  AND sessionid=' . $this->db_quote_escape_string($targetSessionId) . ' ;';
				$req = $this->dbConnection->prepare($sqlchk);
				$req->execute();
				while ($row = $req->fetch()) {
					$targetDeviceId = $row['id'];
					break;
				}
				$req->closeCursor();

				if ($targetDeviceId !== null) {
					// insert
					$sql = '
						INSERT INTO *PREFIX*phonetrack_proxims
						(deviceid1, deviceid2, lowlimit, highlimit, urlclose, urlfar,
						 urlclosepost, urlfarpost, sendemail, emailaddr, sendnotif)
						VALUES (' .
							$this->db_quote_escape_string($device) . ',' .
							$this->db_quote_escape_string($targetDeviceId) . ',' .
							$this->db_quote_escape_string(intval($lowlimit)) . ',' .
							$this->db_quote_escape_string(intval($highlimit)) . ',' .
							$this->db_quote_escape_string($urlclose) . ',' .
							$this->db_quote_escape_string($urlfar) . ',' .
							$this->db_quote_escape_string(intval($urlclosepost)) . ',' .
							$this->db_quote_escape_string(intval($urlfarpost)) . ',' .
							$this->db_quote_escape_string(intval($sendemail)) . ',' .
							$this->db_quote_escape_string($emailaddr) . ',' .
							$this->db_quote_escape_string(intval($sendnotif)) .
						') ;';
					$req = $this->dbConnection->prepare($sql);
					$req->execute();
					$req->closeCursor();

					$sqlchk = '
						SELECT MAX(id) as maxid
						FROM *PREFIX*phonetrack_proxims
						WHERE deviceid1=' . $this->db_quote_escape_string($device) . '
							  AND deviceid2=' . $this->db_quote_escape_string($targetDeviceId) . ' ;';
					$req = $this->dbConnection->prepare($sqlchk);
					$req->execute();
					while ($row = $req->fetch()) {
						$proximid = $row['maxid'];
						break;
					}
					$req->closeCursor();

					$user = $this->userManager->get($this->userId);
					$userEmail = $user->getEMailAddress();
					if (!empty($userEmail)) {
						$ok = 1;
					} else {
						$ok = 4;
					}
				} else {
					$ok = 5;
				}
			} else {
				$ok = 3;
			}
		} else {
			$ok = 2;
		}

		return new DataResponse([
			'done' => $ok,
			'proximid' => $proximid,
			'targetdeviceid' => $targetDeviceId
		]);
	}

	#[NoAdminRequired]
	public function deleteProxim($token, $device, $proximid) {
		$ok = 0;
		if ($this->sessionExists($token, $this->userId) && $this->deviceExists($device, $token)) {
			$dbproximid = null;
			$sqlchk = '
				SELECT id, deviceid1
				FROM *PREFIX*phonetrack_proxims
				WHERE id=' . $this->db_quote_escape_string($proximid) . '
					  AND deviceid1=' . $this->db_quote_escape_string($device) . ' ;';
			$req = $this->dbConnection->prepare($sqlchk);
			$req->execute();
			while ($row = $req->fetch()) {
				$dbproximid = $row['id'];
				break;
			}
			$req->closeCursor();

			if ($dbproximid !== null) {
				$sqldel = '
					DELETE FROM *PREFIX*phonetrack_proxims
					WHERE id=' . $this->db_quote_escape_string($dbproximid) . '
						  AND deviceid1=' . $this->db_quote_escape_string($device) . ' ;';
				$req = $this->dbConnection->prepare($sqldel);
				$req->execute();
				$req->closeCursor();

				$ok = 1;
			}
		} else {
			$ok = 2;
		}

		return new DataResponse([
			'done' => $ok
		]);
	}

	#[NoAdminRequired]
	public function getUserList() {
		$userNames = [];
		try {
			foreach ($this->userManager->search('') as $u) {
				if ($u->getUID() !== $this->userId) {
					//array_push($userNames, $u->getUID());
					$userNames[$u->getUID()] = $u->getDisplayName();
				}
			}
		} catch (\Throwable $t) {
		}
		return new DataResponse([
			'users' => $userNames
		]);
	}

	private function logMultiple($token, $devicename, $points) {
		$done = 0;
		// check if session exists
		$sqlchk = '
			SELECT name
			FROM *PREFIX*phonetrack_sessions
			WHERE token=' . $this->db_quote_escape_string($token) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$req->execute();
		$dbname = null;
		while ($row = $req->fetch()) {
			$dbname = $row['name'];
			break;
		}
		$req->closeCursor();

		if ($dbname !== null) {
			$dbdeviceid = null;
			$sqlgetres = '
				SELECT id, name
				FROM *PREFIX*phonetrack_devices
				WHERE sessionid=' . $this->db_quote_escape_string($token) . '
					  AND name=' . $this->db_quote_escape_string($devicename) . ' ;';
			$req = $this->dbConnection->prepare($sqlgetres);
			$req->execute();
			while ($row = $req->fetch()) {
				$dbdeviceid = $row['id'];
				$dbdevicename = $row['name'];
			}
			$req->closeCursor();

			if ($dbdeviceid === null) {
				// device does not exist and there is no reservation corresponding
				// => we create it
				$sql = '
					INSERT INTO *PREFIX*phonetrack_devices
					(name, sessionid)
					VALUES (' .
						$this->db_quote_escape_string($devicename) . ',' .
						$this->db_quote_escape_string($token) .
					') ;';
				$req = $this->dbConnection->prepare($sql);
				$req->execute();
				$req->closeCursor();

				// get the newly created device id
				$sqlgetdid = '
					SELECT id
					FROM *PREFIX*phonetrack_devices
					WHERE sessionid=' . $this->db_quote_escape_string($token) . '
						  AND name=' . $this->db_quote_escape_string($devicename) . ' ;';
				$req = $this->dbConnection->prepare($sqlgetdid);
				$res = $req->execute();
				while ($row = $req->fetch()) {
					$dbdeviceid = $row['id'];
				}
				$res->closeCursor();
			}

			$valuesStrings = [];
			foreach ($points as $point) {
				// correct timestamp if needed
				$time = $point[3];
				if (is_numeric($time)) {
					$time = floatval($time);
					if ($time > 10000000000.0) {
						$time = $time / 1000.0;
					}
				}

				$lat = $this->db_quote_escape_string(number_format($point[0], 8, '.', ''));
				$lon = $this->db_quote_escape_string(number_format($point[1], 8, '.', ''));
				$alt = is_numeric($point[2]) ? $this->db_quote_escape_string(number_format($point[2], 2, '.', '')) : 'NULL';
				$time = is_numeric($time) ? $this->db_quote_escape_string(number_format((float)$time, 0, '.', '')) : 0;
				$acc = is_numeric($point[4]) ? $this->db_quote_escape_string(number_format($point[4], 2, '.', '')) : 'NULL';
				$bat = is_numeric($point[5]) ? $this->db_quote_escape_string(number_format($point[5], 2, '.', '')) : 'NULL';
				$sat = is_numeric($point[6]) ? $this->db_quote_escape_string(number_format($point[6], 0, '.', '')) : 'NULL';
				$speed = is_numeric($point[8]) ? $this->db_quote_escape_string(number_format($point[8], 3, '.', '')) : 'NULL';
				$bearing = is_numeric($point[9]) ? $this->db_quote_escape_string(number_format($point[9], 2, '.', '')) : 'NULL';
				$useragent = $point[7];

				$oneVal = '(';
				$oneVal .= $this->db_quote_escape_string($dbdeviceid) . ',';
				$oneVal .= $lat . ',';
				$oneVal .= $lon . ',';
				$oneVal .= $time . ',';
				$oneVal .= $acc . ',';
				$oneVal .= $sat . ',';
				$oneVal .= $alt . ',';
				$oneVal .= $bat . ',';
				$oneVal .= $this->db_quote_escape_string($useragent) . ',';
				$oneVal .= $speed . ',';
				$oneVal .= $bearing . ') ';

				$valuesStrings[] = $oneVal;
			}

			// insert by packets of 500
			while (count($valuesStrings) > 0) {
				$c = 0;
				$values = '';
				$values .= array_shift($valuesStrings);
				$c++;
				while (count($valuesStrings) > 0 && $c < 500) {
					$values .= ', ' . array_shift($valuesStrings);
					$c++;
				}

				$sql = '
					INSERT INTO *PREFIX*phonetrack_points
					(deviceid, lat, lon, timestamp,
					 accuracy, satellites, altitude, batterylevel,
					 useragent, speed, bearing)
					VALUES ' . $values . ' ;';
				$req = $this->dbConnection->prepare($sql);
				$res = $req->execute();
				$res->closeCursor();
			}

			$done = 1;
		} else {
			$done = 3;
		}
		return $done;
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function APIPing() {
		return new DataResponse([$this->userId]);
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[NoCSRFRequired]
	public function APIgetLastDevicePositionPublic(string $sessionId, ?string $deviceName = null) {
		return $this->APIgetLastPositionsPublic($sessionId, $deviceName);
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[NoCSRFRequired]
	public function APIgetLastPositionsPublic(string $sessionId, ?string $deviceName = null) {
		$result = [];
		// check if session exists
		$dbToken = null;
		$sqlGet = '
			SELECT publicviewtoken, token
			FROM *PREFIX*phonetrack_sessions
			WHERE publicviewtoken=' . $this->db_quote_escape_string($sessionId) . '
				  AND public=1 ;';
		$req = $this->dbConnection->prepare($sqlGet);
		$res = $req->execute();
		while ($row = $res->fetch()) {
			$dbToken = $row['token'];
			$dbPubToken = $row['publicviewtoken'];
		}
		$res->closeCursor();

		// session exists
		if ($dbToken !== null) {
			// get list of devices
			$devices = [];
			$sqlDev = '
				SELECT name, id
				FROM *PREFIX*phonetrack_devices
				WHERE sessionid=' . $this->db_quote_escape_string($dbToken);
			if ($deviceName !== null) {
				$sqlDev .= ' AND name=' . $this->db_quote_escape_string($deviceName);
			}
			$sqlDev .= ' ;';
			$req = $this->dbConnection->prepare($sqlDev);
			$res = $req->execute();
			while ($row = $res->fetch()) {
				$devices[] = [
					'id' => $row['id'],
					'name' => $row['name'],
				];
			}
			$res->closeCursor();

			// get the coords for each device
			$result[$dbPubToken] = [];

			foreach ($devices as $device) {
				$deviceName = $device['name'];
				$deviceId = $device['id'];

				$entry = [];
				$sqlGet = '
					SELECT lat, lon, timestamp, batterylevel, useragent,
						   satellites, accuracy, altitude, speed, bearing
					FROM *PREFIX*phonetrack_points
					WHERE deviceid=' . $this->db_quote_escape_string($deviceId) . '
					ORDER BY timestamp DESC LIMIT 1 ;';
				$req = $this->dbConnection->prepare($sqlGet);
				$res = $req->execute();
				while ($row = $res->fetch()) {
					$entry['useragent'] = $row['useragent'];
					unset($row['useragent']);
					foreach ($row as $k => $v) {
						$entry[$k] = is_numeric($v) ? floatval($v) : null;
					}
				}
				$res->closeCursor();
				if (count($entry) > 0) {
					$result[$dbPubToken][$deviceName] = $entry;
				}
			}
		}
		return new DataResponse($result);
	}

	#[NoAdminRequired]
	#[PublicPage]
	#[NoCSRFRequired]
	public function APIgetPositionsPublic($sessionid, $limit = null) {
		$result = [];
		// check if session exists
		$dbtoken = null;
		$sqlget = '
			SELECT publicviewtoken, token
			FROM *PREFIX*phonetrack_sessions
			WHERE publicviewtoken=' . $this->db_quote_escape_string($sessionid) . '
				  AND public=1 ;';
		$req = $this->dbConnection->prepare($sqlget);
		$req->execute();
		while ($row = $req->fetch()) {
			$dbtoken = $row['token'];
			$dbpubtoken = $row['publicviewtoken'];
		}
		$req->closeCursor();

		$dbFilters = null;
		$dbDevicename = null;
		$dbLastPosOnly = null;
		$dbGeofencify = null;
		if ($dbtoken === null) {
			$sqlget = '
			SELECT sessionid, sharetoken, filters, devicename, lastposonly, geofencify
			FROM *PREFIX*phonetrack_pubshares
			WHERE sharetoken=' . $this->db_quote_escape_string($sessionid) . ' ;';
			$req = $this->dbConnection->prepare($sqlget);
			$req->execute();
			while ($row = $req->fetch()) {
				$dbtoken = $row['sessionid'];
				$dbpubtoken = $row['sharetoken'];
				$dbFilters = json_decode($row['filters'], true);
				$dbDevicename = $row['devicename'];
				$dbLastPosOnly = $row['lastposonly'];
				$dbGeofencify = $row['geofencify'];
			}
			$req->closeCursor();
		}

		// session exists
		if ($dbtoken !== null) {
			// get list of devices
			$devices = [];

			$deviceNameRestriction = '';
			if ($dbDevicename !== null && $dbDevicename !== '') {
				$deviceNameRestriction = ' AND name=' . $this->db_quote_escape_string($dbDevicename) . ' ';
			}
			$sqldev = '
				SELECT id
				FROM *PREFIX*phonetrack_devices
				WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
				' . $deviceNameRestriction . ' ;';
			$req = $this->dbConnection->prepare($sqldev);
			$req->execute();
			while ($row = $req->fetch()) {
				array_push($devices, $row['id']);
			}
			$req->closeCursor();

			// get the coords for each device
			$result[$dbpubtoken] = [];

			foreach ($devices as $devid) {
				$name = null;
				$color = null;
				$sqlname = '
					SELECT name, color
					FROM *PREFIX*phonetrack_devices
					WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
						  AND id=' . $this->db_quote_escape_string($devid) . ' ;';
				$req = $this->dbConnection->prepare($sqlname);
				$req->execute();
				$col = '';
				while ($row = $req->fetch()) {
					$name = $row['name'];
					$color = $row['color'];
				}
				$req->closeCursor();

				$entries = [];
				$sqlLimit = '';
				if (intval($dbLastPosOnly) === 1) {
					$sqlLimit = 'LIMIT 1';
				} elseif (is_numeric($limit)) {
					$sqlLimit = 'LIMIT ' . intval($limit);
				}
				$sqlget = '
					SELECT lat, lon, timestamp, batterylevel, useragent,
						   satellites, accuracy, altitude, speed, bearing
					FROM *PREFIX*phonetrack_points
					WHERE deviceid=' . $this->db_quote_escape_string($devid) . '
					ORDER BY timestamp DESC ' . $sqlLimit . ' ;';
				$req = $this->dbConnection->prepare($sqlget);
				$req->execute();
				while ($row = $req->fetch()) {
					if ($dbFilters === null || $this->filterPoint($row, $dbFilters)) {
						$entry = [];
						$entry['useragent'] = $row['useragent'];
						unset($row['useragent']);
						foreach ($row as $k => $v) {
							$entry[$k] = is_numeric($v) ? floatval($v) : null;
						}
						array_unshift($entries, $entry);
					}
				}
				$req->closeCursor();
				if (count($entries) > 0) {
					$result[$dbpubtoken][$name] = [
						'color' => $color,
						'points' => $entries
					];
				}
			}
		}
		return new DataResponse($result);
	}

	/**
	 * get last positions of a user's session
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function APIgetLastPositionsUser($sessionid) {
		$result = [];
		// check if session exists
		$dbtoken = null;
		$sqlget = '
			SELECT token
			FROM *PREFIX*phonetrack_sessions
			WHERE token=' . $this->db_quote_escape_string($sessionid) . '
				  AND ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . ' ;';
		$req = $this->dbConnection->prepare($sqlget);
		$req->execute();
		while ($row = $req->fetch()) {
			$dbtoken = $row['token'];
		}
		$req->closeCursor();

		// check if session is shared with current user
		if ($dbtoken === null) {
			$sqlget = '
				SELECT sessionid
				FROM *PREFIX*phonetrack_shares
				WHERE sharetoken=' . $this->db_quote_escape_string($sessionid) . '
					  AND username=' . $this->db_quote_escape_string($this->userId) . ' ;';
			$req = $this->dbConnection->prepare($sqlget);
			$req->execute();
			while ($row = $req->fetch()) {
				$dbtoken = $row['sessionid'];
			}
			$req->closeCursor();
		}

		// session exists
		if ($dbtoken !== null) {
			// get list of devices
			$devices = [];
			$sqldev = '
				SELECT id
				FROM *PREFIX*phonetrack_devices
				WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . ' ;';
			$req = $this->dbConnection->prepare($sqldev);
			$req->execute();
			while ($row = $req->fetch()) {
				array_push($devices, $row['id']);
			}
			$req->closeCursor();

			// get the coords for each device
			$result[$sessionid] = [];

			foreach ($devices as $devid) {
				$name = null;
				$color = null;
				$sqlname = '
					SELECT name, color
					FROM *PREFIX*phonetrack_devices
					WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
						  AND id=' . $this->db_quote_escape_string($devid) . ' ;';
				$req = $this->dbConnection->prepare($sqlname);
				$req->execute();
				$col = '';
				while ($row = $req->fetch()) {
					$name = $row['name'];
					$color = $row['color'];
				}
				$req->closeCursor();

				$entry = [];
				$sqlget = '
					SELECT lat, lon, timestamp, batterylevel, useragent,
						   satellites, accuracy, altitude, speed, bearing
					FROM *PREFIX*phonetrack_points
					WHERE deviceid=' . $this->db_quote_escape_string($devid) . '
					ORDER BY timestamp DESC LIMIT 1 ;';
				$req = $this->dbConnection->prepare($sqlget);
				$req->execute();
				while ($row = $req->fetch()) {
					$entry['useragent'] = $row['useragent'];
					unset($row['useragent']);
					foreach ($row as $k => $v) {
						$entry[$k] = is_numeric($v) ? floatval($v) : null;
					}
				}
				$req->closeCursor();
				if (count($entry) > 0) {
					$entry['color'] = $color;
					$result[$sessionid][$name] = $entry;
				}
			}
		}
		return new DataResponse($result);
	}

	/**
	 * get positions of a user's session
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function APIgetPositionsUser($sessionid, $limit = null, $tsmin = null) {
		$result = [];
		// check if session exists
		$dbtoken = null;
		$sqlget = '
			SELECT token
			FROM *PREFIX*phonetrack_sessions
			WHERE token=' . $this->db_quote_escape_string($sessionid) . '
				  AND ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . ' ;';
		$req = $this->dbConnection->prepare($sqlget);
		$req->execute();
		while ($row = $req->fetch()) {
			$dbtoken = $row['token'];
		}
		$req->closeCursor();

		// check if session is shared with current user
		if ($dbtoken === null) {
			$sqlget = '
				SELECT sessionid
				FROM *PREFIX*phonetrack_shares
				WHERE sharetoken=' . $this->db_quote_escape_string($sessionid) . '
					  AND username=' . $this->db_quote_escape_string($this->userId) . ' ;';
			$req = $this->dbConnection->prepare($sqlget);
			$req->execute();
			while ($row = $req->fetch()) {
				$dbtoken = $row['sessionid'];
			}
			$req->closeCursor();
		}

		// session exists
		if ($dbtoken !== null) {
			// get list of devices
			$devices = [];
			$sqldev = '
				SELECT id
				FROM *PREFIX*phonetrack_devices
				WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . ' ;';
			$req = $this->dbConnection->prepare($sqldev);
			$req->execute();
			while ($row = $req->fetch()) {
				array_push($devices, $row['id']);
			}
			$req->closeCursor();

			// get the coords for each device
			$result[$sessionid] = [];

			foreach ($devices as $devid) {
				$name = null;
				$color = null;
				$sqlname = '
					SELECT name, color
					FROM *PREFIX*phonetrack_devices
					WHERE sessionid=' . $this->db_quote_escape_string($dbtoken) . '
						  AND id=' . $this->db_quote_escape_string($devid) . ' ;';
				$req = $this->dbConnection->prepare($sqlname);
				$req->execute();
				$col = '';
				while ($row = $req->fetch()) {
					$name = $row['name'];
					$color = $row['color'];
				}
				$req->closeCursor();

				$entries = [];
				$sqlLimit = '';
				if (is_numeric($limit)) {
					$sqlLimit = 'LIMIT ' . intval($limit);
				}
				$tsminCondition = '';
				if (is_numeric($tsmin)) {
					$tsminCondition = 'AND timestamp >= ' . $this->db_quote_escape_string($tsmin) . ' ';
				}
				$sqlget = '
					SELECT lat, lon, timestamp, batterylevel, useragent,
						   satellites, accuracy, altitude, speed, bearing
					FROM *PREFIX*phonetrack_points
					WHERE deviceid=' . $this->db_quote_escape_string($devid) . ' ' .
					$tsminCondition . '
					ORDER BY timestamp DESC ' . $sqlLimit . ' ;';
				$req = $this->dbConnection->prepare($sqlget);
				$req->execute();
				while ($row = $req->fetch()) {
					$entry = [];
					$entry['useragent'] = $row['useragent'];
					unset($row['useragent']);
					foreach ($row as $k => $v) {
						$entry[$k] = is_numeric($v) ? floatval($v) : null;
					}
					array_unshift($entries, $entry);
				}
				$req->closeCursor();
				if (count($entries) > 0) {
					$result[$sessionid][$name] = [
						'color' => $color,
						'points' => $entries
					];
				}
			}
		}
		return new DataResponse($result);
	}

	/**
	 * check if there already is a public share restricted on that device
	 * if not => add it
	 * returns the share token
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function APIshareDevice($sessionid, $devicename) {
		$result = ['code' => 0, 'sharetoken' => '', 'done' => 0];
		// check if session exists and is owned by current user
		$sqlchk = '
			SELECT token
			FROM *PREFIX*phonetrack_sessions
			WHERE ' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($this->userId) . '
				  AND token=' . $this->db_quote_escape_string($sessionid) . ' ;';
		$req = $this->dbConnection->prepare($sqlchk);
		$req->execute();
		$dbtoken = null;
		while ($row = $req->fetch()) {
			$dbtoken = $row['token'];
			break;
		}
		$req->closeCursor();

		if ($dbtoken !== null) {
			$dbsharetoken = null;
			$sqlget = '
				SELECT sharetoken
				FROM *PREFIX*phonetrack_pubshares
				WHERE sessionid=' . $this->db_quote_escape_string($sessionid) . '
					  AND devicename=' . $this->db_quote_escape_string($devicename) . ' ;';
			$req = $this->dbConnection->prepare($sqlget);
			$req->execute();
			while ($row = $req->fetch()) {
				$dbsharetoken = $row['sharetoken'];
			}
			$req->closeCursor();

			// public share exists
			if ($dbsharetoken !== null) {
				$result['sharetoken'] = $dbsharetoken;
				$result['code'] = 1;
				$result['done'] = 1;
			} else {
				// let's create the public share without filters
				$resp = $this->addPublicShare($dbtoken, true);
				$data = $resp->getData();
				$done = $data['done'];
				$sharetoken = $data['sharetoken'];
				if ($done === 1) {
					$resp2 = $this->setPublicShareDevice($dbtoken, $sharetoken, $devicename);
					$data2 = $resp2->getData();
					$done2 = $data2['done'];
					if ($done2 === 1) {
						$result['sharetoken'] = $sharetoken;
						$result['done'] = 1;
					}
				}
			}
		}
		return new DataResponse($result);
	}

}
