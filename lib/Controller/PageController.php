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
use OCP\AppFramework\Http\DataResponse;
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
	 * @param string|null $name
	 * @param string|null $autoExport
	 * @param string|null $autoPurge
	 * @return DataResponse
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	#[NoAdminRequired]
	public function updateSession(
		int $sessionId, ?bool $enabled = null, ?bool $locked = null, ?bool $public = null,
		?string $name = null, ?string $autoExport = null, ?string $autoPurge = null,
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
		if ($autoExport !== null) {
			$session->setAutoexport($autoExport);
		}
		if ($autoPurge !== null) {
			$session->setAutopurge($autoPurge);
		}
		$this->sessionMapper->update($session);
		return new DataResponse($session);
	}
}
