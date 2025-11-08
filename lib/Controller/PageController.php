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

use OCA\PhoneTrack\AppInfo\Application;
use OCA\PhoneTrack\Db\Device;
use OCA\PhoneTrack\Db\DeviceMapper;
use OCA\PhoneTrack\Db\Geofence;
use OCA\PhoneTrack\Db\GeofenceMapper;
use OCA\PhoneTrack\Db\PointMapper;
use OCA\PhoneTrack\Db\Proxim;
use OCA\PhoneTrack\Db\ProximMapper;
use OCA\PhoneTrack\Db\PublicShare;
use OCA\PhoneTrack\Db\PublicShareMapper;
use OCA\PhoneTrack\Db\SessionMapper;
use OCA\PhoneTrack\Db\Share;
use OCA\PhoneTrack\Db\ShareMapper;
use OCA\PhoneTrack\Db\TileServerMapper;
use OCA\PhoneTrack\Service\MapService;
use OCA\PhoneTrack\Service\SessionService;
use OCA\PhoneTrack\Service\ToolsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;

use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\DB\Exception;
use OCP\IAppConfig;
use OCP\IL10N;

use OCP\IRequest;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use stdClass;

class PageController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private LoggerInterface $logger,
		private IL10N $l10n,
		private SessionMapper $sessionMapper,
		private SessionService $sessionService,
		private DeviceMapper $deviceMapper,
		private PublicShareMapper $publicShareMapper,
		private ShareMapper $shareMapper,
		private GeofenceMapper $geofenceMapper,
		private ProximMapper $proximMapper,
		private PointMapper $pointMapper,
		private IInitialState $initialStateService,
		private IAppConfig $appConfig,
		private IUserManager $userManager,
		private ToolsService $toolsService,
		private MapService $mapService,
		private TileServerMapper $tileServerMapper,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		$settings = $this->toolsService->getOptionsValues($this->userId);
		$adminMaptilerApiKey = $this->appConfig->getValueString(Application::APP_ID, 'maptiler_api_key', Application::DEFAULT_MAPTILER_API_KEY, lazy: true) ?: Application::DEFAULT_MAPTILER_API_KEY;
		$maptilerApiKey = $this->toolsService->getEncryptedUserValue($this->userId, 'maptiler_api_key') ?: $adminMaptilerApiKey;
		$settings['maptiler_api_key'] = $maptilerApiKey;

		$adminProxyOsm = $this->appConfig->getValueString(Application::APP_ID, 'proxy_osm', '1', lazy: true) === '1';
		$settings['proxy_osm'] = $adminProxyOsm;

		$settings['app_version'] = $this->appConfig->getValueString(Application::APP_ID, 'installed_version');

		$settings['refresh_duration'] = isset($settings['refresh_duration'])
			? (int)$settings['refresh_duration']
			: 125;

		$sessions = $this->sessionService->getSessions2($this->userId);
		$sessionsById = [];
		foreach ($sessions as $session) {
			$sessionsById[$session['id']] = $session;
		}

		$userTileServers = $this->tileServerMapper->getTileServersOfUser($this->userId);
		$adminTileServers = $this->tileServerMapper->getTileServersOfUser(null);
		$extraTileServers = array_merge($userTileServers, $adminTileServers);
		$settings['extra_tile_servers'] = $extraTileServers;

		$state = [
			'sessions' => empty($sessionsById) ? new stdClass() : $sessionsById,
			'settings' => $settings,
		];
		$this->initialStateService->provideInitialState('phonetrack-state', $state);
		$response = new TemplateResponse(Application::APP_ID, 'mainVue');
		$csp = new ContentSecurityPolicy();
		$this->mapService->addPageCsp($csp, $extraTileServers);
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * @param string $name
	 * @return DataResponse
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	#[NoAdminRequired]
	public function createSession(string $name): DataResponse {
		// check if session name is not already used
		try {
			$session = $this->sessionMapper->getUserSessionByName($this->userId, $name);
			return new DataResponse(['error' => 'already_exists'], Http::STATUS_BAD_REQUEST);
		} catch (DoesNotExistException $e) {
		}

		// determine token
		$token = md5($this->userId . $name . rand());
		$publicViewToken = md5($this->userId . $name . rand());

		$newSession = $this->sessionMapper->createSession($this->userId, $name, $token, $publicViewToken, true);
		$newSession = $newSession->jsonSerialize();
		$newSession['shared_with'] = [];
		$newSession['reserved_names'] = [];
		$newSession['public_shares'] = [];
		$newSession['devices'] = [];
		return new DataResponse($newSession);
	}

	/**
	 * @param int $sessionId
	 * @return DataResponse
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	#[NoAdminRequired]
	public function deleteSession(int $sessionId): DataResponse {
		$session = $this->sessionMapper->getUserSessionById($this->userId, $sessionId);
		$devices = $this->deviceMapper->findBySessionId($session->getToken());
		foreach ($devices as $device) {
			$deviceId = $device->getId();
			$this->pointMapper->deleteByDeviceId($deviceId);
			$this->geofenceMapper->deleteByDeviceId($deviceId);
			$this->proximMapper->deleteByDeviceId($deviceId);
			$this->deviceMapper->deleteDevice($session->getToken(), $deviceId);
		}
		$this->sessionMapper->deleteSession($this->userId, $sessionId);
		return new DataResponse([]);
	}

	/**
	 * @param int $sessionId
	 * @param bool|null $enabled
	 * @param bool|null $locked
	 * @param bool|null $public
	 * @param string|null $name
	 * @param string|null $autoexport
	 * @param string|null $autopurge
	 * @return DataResponse
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	#[NoAdminRequired]
	public function updateSession(
		int $sessionId, ?bool $enabled = null, ?bool $locked = null, ?bool $public = null,
		?string $name = null, ?string $autoexport = null, ?string $autopurge = null,
	): DataResponse {
		try {
			$session = $this->sessionMapper->getUserSessionById($this->userId, $sessionId);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		if ($enabled !== null) {
			$session->setEnabled($enabled ? 1 : 0);
		}
		if ($locked !== null) {
			$session->setLocked($locked ? 1 : 0);
		}
		if ($public !== null) {
			$session->setPublic($public ? 1 : 0);
		}
		if ($name !== null) {
			$session->setName($name);
		}
		if ($autoexport !== null) {
			$session->setAutoexport($autoexport);
		}
		if ($autopurge !== null) {
			$session->setAutopurge($autopurge);
		}
		$this->sessionMapper->update($session);
		return new DataResponse($session);
	}


	/**
	 * @param int $sessionId
	 * @param int $deviceId
	 * @return DataResponse
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	#[NoAdminRequired]
	public function deleteDevice(int $sessionId, int $deviceId): DataResponse {
		try {
			$session = $this->sessionMapper->getUserSessionById($this->userId, $sessionId);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		$device = $this->deviceMapper->getBySessionTokenAndDeviceId($session->getToken(), $deviceId);
		$this->pointMapper->deleteByDeviceId($deviceId);
		$this->geofenceMapper->deleteByDeviceId($deviceId);
		$this->proximMapper->deleteByDeviceId($deviceId);
		$this->deviceMapper->deleteDevice($session->getToken(), $deviceId);
		return new DataResponse([]);
	}

	/**
	 * @param int $sessionId
	 * @param int $deviceId
	 * @param bool|null $enabled
	 * @param int|null $colorCriteria
	 * @param string|null $color
	 * @param string|null $alias
	 * @param string|null $name
	 * @param string|null $shape
	 * @param string|null $sessionToken
	 * @param string|null $nametoken
	 * @param bool|null $lineEnabled
	 * @param bool|null $autoZoom
	 * @return DataResponse
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	#[NoAdminRequired]
	public function updateDevice(int $sessionId, int $deviceId,
		?bool $enabled = null, ?int $colorCriteria = null, ?string $color = null,
		?string $alias = null, ?string $name = null, ?string $shape = null,
		?string $sessionToken = null, ?string $nametoken = null, ?bool $lineEnabled = null, ?bool $autoZoom = null,
	): DataResponse {
		try {
			$session = $this->sessionMapper->getUserSessionById($this->userId, $sessionId);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		$device = $this->deviceMapper->getBySessionTokenAndDeviceId($session->getToken(), $deviceId);
		if ($enabled !== null) {
			$device->setEnabled($enabled ? 1 : 0);
		}
		if ($colorCriteria !== null) {
			$device->setColorCriteria($colorCriteria);
		}
		if ($color !== null) {
			$device->setColor($color);
		}
		if ($alias !== null) {
			$device->setAlias($alias);
		}
		if ($name !== null) {
			$device->setName($name);
		}
		if ($shape !== null) {
			$device->setShape($shape);
		}
		if ($sessionToken !== null) {
			$device->setSessionid($sessionToken);
		}
		if ($nametoken !== null) {
			$device->setNametoken($nametoken === '' ? null : $nametoken);
		}
		if ($lineEnabled !== null) {
			$device->setLineEnabled($lineEnabled ? 1 : 0);
		}
		if ($autoZoom !== null) {
			$device->setAutoZoom($autoZoom ? 1 : 0);
		}
		$this->deviceMapper->update($device);
		return new DataResponse($device);
	}

	/**
	 * @param int $sessionId
	 * @param int $deviceId
	 * @param int $maxPoints
	 * @param int|null $minTimestamp
	 * @param int|null $maxTimestamp
	 * @param bool $combine
	 * @return DataResponse
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getDevicePoints(
		int $sessionId, int $deviceId, int $maxPoints = 1000, ?int $minTimestamp = null, ?int $maxTimestamp = null,
		bool $combine = false,
	): DataResponse {
		try {
			$session = $this->sessionMapper->getUserSessionById($this->userId, $sessionId);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'session_not_found'], Http::STATUS_NOT_FOUND);
		}
		try {
			$device = $this->deviceMapper->getBySessionTokenAndDeviceId($session->getToken(), $deviceId);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'device_not_found'], Http::STATUS_NOT_FOUND);
		}
		return new DataResponse(
			$combine
				? $this->sessionService->getDevicePointsCombined($device->getId(), $minTimestamp, $maxTimestamp, $maxPoints)
				: $this->pointMapper->getDevicePoints($deviceId, $minTimestamp, $maxTimestamp, $maxPoints)
		);
	}

	/**
	 * @param int $sessionId
	 * @param string $deviceName
	 * @return DataResponse
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	#[NoAdminRequired]
	public function addDeviceReservation(int $sessionId, string $deviceName): DataResponse {
		try {
			$session = $this->sessionMapper->getUserSessionById($this->userId, $sessionId);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}

		try {
			$device = $this->deviceMapper->getByName($session->getToken(), $deviceName);
			if ($device->getNametoken() !== null && $device->getNametoken() !== '') {
				return new DataResponse(['error' => 'already_reserved'], Http::STATUS_CONFLICT);
			}
			$nameToken = md5('nametoken' . $this->userId . rand());
			$device->setNametoken($nameToken);
			$device = $this->deviceMapper->update($device);
			return new DataResponse($device);
		} catch (DoesNotExistException $e) {
			// create
			$device = new Device();
			$device->setSessionid($session->getToken());
			$nameToken = md5('nametoken' . $this->userId . rand());
			$device->setNametoken($nameToken);
			$device->setName($deviceName);
			$device->setEnabled(0);
			$device->setColorCriteria(0);
			$device = $this->deviceMapper->insert($device);
			return new DataResponse($device);
		} catch (MultipleObjectsReturnedException|\Exception $e) {
			$this->logger->warning('Impossible to reserve name', ['exception' => $e]);
			return new DataResponse(['error' => 'unknown', 'exception' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @param int $sessionId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	public function createPublicShare(int $sessionId): DataResponse {
		// check if session exists
		try {
			$session = $this->sessionMapper->getUserSessionById($this->userId, $sessionId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return new DataResponse(['error' => 'session_not_found'], Http::STATUS_NOT_FOUND);
		}

		// determine token
		$shareToken = md5('share' . $this->userId . $session->getName() . rand());

		$newPublicShare = new PublicShare();
		$newPublicShare->setSessionid($session->getToken());
		$newPublicShare->setSharetoken($shareToken);
		$newPublicShare->setLastposonly(0);
		$newPublicShare->setGeofencify(0);
		$newPublicShare = $this->publicShareMapper->insert($newPublicShare);
		return new DataResponse($newPublicShare->jsonSerialize());
	}

	/**
	 * @param int $sessionId
	 * @param int $pubShareId
	 * @param string|null $label
	 * @param string|null $filters
	 * @param string|null $devicename
	 * @param bool|null $lastposonly
	 * @param bool|null $geofencify
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	public function updatePublicShare(int $sessionId, int $pubShareId,
		?string $label = null, ?string $filters = null, ?string $devicename = null,
		?bool $lastposonly = null, ?bool $geofencify = null,
	): DataResponse {
		// check if session exists
		try {
			$session = $this->sessionMapper->getUserSessionById($this->userId, $sessionId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return new DataResponse(['error' => 'session_not_found'], Http::STATUS_NOT_FOUND);
		}

		// get pub share
		$publicShare = $this->publicShareMapper->findByIdAndSessionToken($pubShareId, $session->getToken());
		if ($label !== null) {
			$publicShare->setLabel($label === '' ? null : $label);
		}
		if ($filters !== null) {
			$publicShare->setFilters($filters);
		}
		if ($devicename !== null) {
			$publicShare->setDevicename($devicename === '' ? null : $devicename);
		}
		if ($lastposonly !== null) {
			$publicShare->setLastposonly($lastposonly ? 1 : 0);
		}
		if ($geofencify !== null) {
			$publicShare->setGeofencify($geofencify ? 1 : 0);
		}
		$updatedPublicShare = $this->publicShareMapper->update($publicShare);
		return new DataResponse($updatedPublicShare);
	}

	/**
	 * @param int $sessionId
	 * @param int $pubShareId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	public function deletePublicShare(int $sessionId, int $pubShareId): DataResponse {
		try {
			$session = $this->sessionMapper->getUserSessionById($this->userId, $sessionId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		$publicShare = $this->publicShareMapper->findByIdAndSessionToken($pubShareId, $session->getToken());
		$this->publicShareMapper->delete($publicShare);
		return new DataResponse([]);
	}

	/**
	 * @param int $sessionId
	 * @param string $userId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	public function createShare(int $sessionId, string $userId): DataResponse {
		// check if session exists
		try {
			$session = $this->sessionMapper->getUserSessionById($this->userId, $sessionId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return new DataResponse(['error' => 'session_not_found'], Http::STATUS_NOT_FOUND);
		}

		// check if already shared
		try {
			$share = $this->shareMapper->findBySessionTokenAndUser($userId, $session->getToken());
		} catch (DoesNotExistException) {
		}

		$shareToken = md5('share' . $this->userId . $session->getName() . rand());

		$newShare = new Share();
		$newShare->setSessionid($session->getToken());
		$newShare->setSharetoken($shareToken);
		$newShare->setUsername($userId);
		$newShare = $this->shareMapper->insert($newShare);
		$jsonNewShare = $newShare->jsonSerialize();
		$jsonNewShare['type'] = 'u';
		$user = $this->userManager->get($userId);
		if ($user !== null) {
			$jsonNewShare['display_name'] = $user->getDisplayName();
		} else {
			$this->shareMapper->delete($newShare);
			return new DataResponse(['error' => 'user_not_found'], Http::STATUS_NOT_FOUND);
		}
		return new DataResponse($jsonNewShare);
	}

	/**
	 * @param int $sessionId
	 * @param int $shareId
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	public function deleteShare(int $sessionId, int $shareId): DataResponse {
		try {
			$session = $this->sessionMapper->getUserSessionById($this->userId, $sessionId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		$share = $this->shareMapper->findByIdAndSessionToken($shareId, $session->getToken());
		$this->shareMapper->delete($share);
		return new DataResponse([]);
	}

	/**
	 * @param string $fileName
	 * @param string $color
	 * @return NotFoundResponse|DataDisplayResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getSvgFromApp(string $fileName, string $color = 'ffffff') {
		try {
			$svg = $this->toolsService->getSvgFromApp($fileName, $color);
		} catch (\Exception $e) {
			return new NotFoundResponse();
		}

		$response = new DataDisplayResponse($svg, Http::STATUS_OK, ['Content-Type' => 'image/svg+xml']);
		$response->cacheFor(31536000);
		return $response;
	}

	#[NoAdminRequired]
	public function createGeofence(
		int $sessionId, int $deviceId, string $name,
		float $latmin, float $latmax, float $lonmin, float $lonmax,
		?string $urlenter, ?string $urlleave, int $urlenterpost, int $urlleavepost,
		int $sendemail, ?string $emailaddr, int $sendnotif,
	): DataResponse {
		// check if session exists
		try {
			$session = $this->sessionMapper->getUserSessionById($this->userId, $sessionId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return new DataResponse(['error' => 'session_not_found'], Http::STATUS_NOT_FOUND);
		}

		$device = $this->deviceMapper->getBySessionTokenAndDeviceId($session->getToken(), $deviceId);

		$geofence = new Geofence();
		$geofence->setDeviceid($deviceId);
		$geofence->setName($name);
		$geofence->setLatmin($latmin);
		$geofence->setLatmax($latmax);
		$geofence->setLonmin($lonmin);
		$geofence->setLonmax($lonmax);
		$geofence->setUrlenter($urlenter);
		$geofence->setUrlleave($urlleave);
		$geofence->setUrlenterpost($urlenterpost);
		$geofence->setUrlleavepost($urlleavepost);
		$geofence->setSendemail($sendemail);
		$geofence->setEmailaddr($emailaddr);
		$geofence->setSendnotif($sendnotif);
		$insertedGeofence = $this->geofenceMapper->insert($geofence);
		return new DataResponse($insertedGeofence->jsonSerialize());
	}

	#[NoAdminRequired]
	public function updateGeofence(
		int $sessionId, int $deviceId, int $geofenceId, ?string $name = null,
		?float $latmin = null, ?float $latmax = null, ?float $lonmin = null, ?float $lonmax = null,
		?string $urlenter = null, ?string $urlleave = null, ?int $urlenterpost = null, ?int $urlleavepost = null,
		?int $sendemail = null, ?string $emailaddr = null, ?int $sendnotif = null,
	): DataResponse {
		// check if session exists
		try {
			$session = $this->sessionMapper->getUserSessionById($this->userId, $sessionId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return new DataResponse(['error' => 'session_not_found'], Http::STATUS_NOT_FOUND);
		}

		$geofence = $this->geofenceMapper->find($geofenceId);
		if ($deviceId !== $geofence->getDeviceid()) {
			return new DataResponse(['error' => 'device_not_found'], Http::STATUS_NOT_FOUND);
		}
		try {
			$device = $this->deviceMapper->getBySessionTokenAndDeviceId($session->getToken(), $geofence->getDeviceid());
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return new DataResponse(['error' => 'device_not_found'], Http::STATUS_NOT_FOUND);
		}

		if ($name !== null) {
			$geofence->setName($name);
		}
		if ($latmin !== null) {
			$geofence->setLatmin($latmin);
		}
		if ($latmax !== null) {
			$geofence->setLatmax($latmax);
		}
		if ($lonmin !== null) {
			$geofence->setLonmin($lonmin);
		}
		if ($lonmax !== null) {
			$geofence->setLonmax($lonmax);
		}
		if ($urlenter !== null) {
			$geofence->setUrlenter($urlenter === '' ? null : $urlenter);
		}
		if ($urlleave !== null) {
			$geofence->setUrlleave($urlleave === '' ? null : $urlleave);
		}
		if ($urlenterpost !== null) {
			$geofence->setUrlenterpost($urlenterpost);
		}
		if ($urlleavepost !== null) {
			$geofence->setUrlleavepost($urlleavepost);
		}
		if ($sendemail !== null) {
			$geofence->setSendemail($sendemail);
		}
		if ($emailaddr !== null) {
			$geofence->setEmailaddr($emailaddr === '' ? null : $emailaddr);
		}
		if ($sendemail !== null) {
			$geofence->setSendnotif($sendnotif);
		}
		$updatedGeofence = $this->geofenceMapper->update($geofence);
		return new DataResponse($updatedGeofence->jsonSerialize());
	}

	#[NoAdminRequired]
	public function deleteGeofence(int $sessionId, int $deviceId, int $geofenceId): DataResponse {
		try {
			$session = $this->sessionMapper->getUserSessionById($this->userId, $sessionId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return new DataResponse(['error' => 'session_not_found'], Http::STATUS_NOT_FOUND);
		}
		$geofence = $this->geofenceMapper->find($geofenceId);
		if ($deviceId !== $geofence->getDeviceid()) {
			return new DataResponse(['error' => 'device_not_found'], Http::STATUS_NOT_FOUND);
		}
		try {
			$device = $this->deviceMapper->getBySessionTokenAndDeviceId($session->getToken(), $geofence->getDeviceid());
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return new DataResponse(['error' => 'device_not_found'], Http::STATUS_NOT_FOUND);
		}
		$this->geofenceMapper->delete($geofence);
		return new DataResponse([]);
	}

	#[NoAdminRequired]
	public function createProxim(
		int $sessionId1, int $deviceId1, int $sessionid2, int $deviceid2,
		int $lowlimit, int $highlimit,
		?string $urlclose, ?string $urlfar, int $urlclosepost, int $urlfarpost,
		int $sendemail, ?string $emailaddr, int $sendnotif,
	): DataResponse {
		// check if session exists
		try {
			$session1 = $this->sessionMapper->getUserSessionById($this->userId, $sessionId1);
			$session2 = $this->sessionMapper->getUserSessionById($this->userId, $sessionid2);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return new DataResponse(['error' => 'session_not_found'], Http::STATUS_NOT_FOUND);
		}

		$device1 = $this->deviceMapper->getBySessionTokenAndDeviceId($session1->getToken(), $deviceId1);
		$device2 = $this->deviceMapper->getBySessionTokenAndDeviceId($session2->getToken(), $deviceid2);

		$proxim = new Proxim();
		$proxim->setDeviceid1($deviceId1);
		$proxim->setDeviceid2($deviceid2);
		$proxim->setLowlimit($lowlimit);
		$proxim->setHighlimit($highlimit);
		$proxim->setUrlclose($urlclose);
		$proxim->setUrlfar($urlfar);
		$proxim->setUrlclosepost($urlclosepost);
		$proxim->setUrlfarpost($urlfarpost);
		$proxim->setSendemail($sendemail);
		$proxim->setEmailaddr($emailaddr);
		$proxim->setSendnotif($sendnotif);
		$insertedProxim = $this->proximMapper->insert($proxim);
		return new DataResponse($insertedProxim->jsonSerialize());
	}

	#[NoAdminRequired]
	public function updateProxim(
		int $sessionId1, int $deviceId1, int $proximId, ?int $sessionid2 = null, ?int $deviceid2 = null,
		?int $lowlimit = null, ?int $highlimit = null,
		?string $urlclose = null, ?string $urlfar = null, ?int $urlclosepost = null, ?int $urlfarpost = null,
		?int $sendemail = null, ?string $emailaddr = null, ?int $sendnotif = null,
	): DataResponse {
		// check if session exists
		try {
			$session1 = $this->sessionMapper->getUserSessionById($this->userId, $sessionId1);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return new DataResponse(['error' => 'session_not_found'], Http::STATUS_NOT_FOUND);
		}

		$proxim = $this->proximMapper->find($proximId);
		if ($deviceId1 !== $proxim->getDeviceid1()) {
			return new DataResponse(['error' => 'device_not_found'], Http::STATUS_NOT_FOUND);
		}
		try {
			$device1 = $this->deviceMapper->getBySessionTokenAndDeviceId($session1->getToken(), $proxim->getDeviceid1());
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return new DataResponse(['error' => 'device_not_found'], Http::STATUS_NOT_FOUND);
		}
		// check that device2 is in a session owned by the user
		if ($deviceid2 !== null && $sessionid2 !== null) {
			try {
				$session2 = $this->sessionMapper->getUserSessionById($this->userId, $sessionid2);
				$device2 = $this->deviceMapper->getBySessionTokenAndDeviceId($session2->getToken(), $deviceid2);
				$proxim->setDeviceid2($deviceid2);
			} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
				return new DataResponse(['error' => 'device2_not_found'], Http::STATUS_NOT_FOUND);
			}
		}

		if ($lowlimit !== null) {
			$proxim->setLowlimit($lowlimit);
		}
		if ($highlimit !== null) {
			$proxim->setHighlimit($highlimit);
		}
		if ($urlclose !== null) {
			$proxim->setUrlclose($urlclose === '' ? null : $urlclose);
		}
		if ($urlfar !== null) {
			$proxim->setUrlfar($urlfar === '' ? null : $urlfar);
		}
		if ($urlclosepost !== null) {
			$proxim->setUrlclosepost($urlclosepost);
		}
		if ($urlfarpost !== null) {
			$proxim->setUrlfarpost($urlfarpost);
		}
		if ($sendemail !== null) {
			$proxim->setSendemail($sendemail);
		}
		if ($emailaddr !== null) {
			$proxim->setEmailaddr($emailaddr === '' ? null : $emailaddr);
		}
		if ($sendemail !== null) {
			$proxim->setSendnotif($sendnotif);
		}
		$updatedProxim = $this->proximMapper->update($proxim);
		return new DataResponse($updatedProxim->jsonSerialize());
	}

	#[NoAdminRequired]
	public function deleteProxim(int $sessionId1, int $deviceId1, int $proximId): DataResponse {
		try {
			$session1 = $this->sessionMapper->getUserSessionById($this->userId, $sessionId1);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return new DataResponse(['error' => 'session_not_found'], Http::STATUS_NOT_FOUND);
		}
		$proxim = $this->proximMapper->find($proximId);
		if ($deviceId1 !== $proxim->getDeviceid1()) {
			return new DataResponse(['error' => 'device_not_found'], Http::STATUS_NOT_FOUND);
		}
		try {
			$device1 = $this->deviceMapper->getBySessionTokenAndDeviceId($session1->getToken(), $proxim->getDeviceid1());
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return new DataResponse(['error' => 'device_not_found'], Http::STATUS_NOT_FOUND);
		}
		$this->proximMapper->delete($proxim);
		return new DataResponse([]);
	}
}
