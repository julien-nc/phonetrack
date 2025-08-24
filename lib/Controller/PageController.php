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
use OCA\PhoneTrack\Db\DeviceMapper;
use OCA\PhoneTrack\Db\PublicShare;
use OCA\PhoneTrack\Db\PublicShareMapper;
use OCA\PhoneTrack\Db\SessionMapper;
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
		private IInitialState $initialStateService,
		private IAppConfig $appConfig,
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
		$adminMaptilerApiKey = $this->appConfig->getValueString(Application::APP_ID, 'maptiler_api_key', Application::DEFAULT_MAPTILER_API_KEY) ?: Application::DEFAULT_MAPTILER_API_KEY;
		$maptilerApiKey = $this->toolsService->getEncryptedUserValue($this->userId, 'maptiler_api_key') ?: $adminMaptilerApiKey;
		$settings['maptiler_api_key'] = $maptilerApiKey;

		$adminProxyOsm = $this->appConfig->getValueString(Application::APP_ID, 'proxy_osm', '1') === '1';
		$settings['proxy_osm'] = $adminProxyOsm;

		$settings['app_version'] = $this->appConfig->getValueString(Application::APP_ID, 'installed_version');

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
	 */
	#[NoAdminRequired]
	public function deleteSession(int $sessionId): DataResponse {
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
	 * @return DataResponse
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	#[NoAdminRequired]
	public function updateDevice(int $sessionId, int $deviceId,
		?bool $enabled = null, ?int $colorCriteria = null, ?string $color = null,
		?string $alias = null, ?string $name = null, ?string $shape = null,
		?string $sessionToken = null,
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
		$this->deviceMapper->update($device);
		return new DataResponse($device);
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
		$newPublicShare->setLastposonly(false);
		$newPublicShare->setGeofencify(false);
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
}
