<?php

namespace OCA\PhoneTrack\Controller;

use OCA\PhoneTrack\AppInfo\Application;
use OCA\PhoneTrack\Db\DeviceMapper;
use OCA\PhoneTrack\Db\PointMapper;
use OCA\PhoneTrack\Db\PublicShareMapper;
use OCA\PhoneTrack\Db\SessionMapper;
use OCA\PhoneTrack\Db\TileServerMapper;
use OCA\PhoneTrack\Service\MapService;
use OCA\PhoneTrack\Service\SessionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\DB\Exception;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;

class PublicShareController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IAppConfig $appConfig,
		private TileServerMapper $tileServerMapper,
		private IL10N $l,
		private SessionService $sessionService,
		private MapService $mapService,
		private SessionMapper $sessionMapper,
		private DeviceMapper $deviceMapper,
		private PointMapper $pointMapper,
		private PublicShareMapper $publicShareMapper,
		private IInitialState $initialStateService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}


	#[NoAdminRequired]
	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'phonetrackPublicIndex')]
	public function index(string $token): TemplateResponse {
		try {
			$session = $this->sessionMapper->getSessionsByPublicViewToken($token);
		} catch (DoesNotExistException $e) {
			try {
				$share = $this->publicShareMapper->findByShareToken($token);
				$session = $this->sessionMapper->find($share->getSessionId());
			} catch (DoesNotExistException $e) {
				$response = new TemplateResponse(
					'',
					'error',
					[
						'errors' => [
							['error' => $this->l->t('PhoneTrack public share not found')],
						],
					],
					TemplateResponse::RENDER_AS_ERROR
				);
				$response->setStatus(Http::STATUS_NOT_FOUND);
				$response->throttle(['share_not_found' => $token]);
				return $response;
			}
		}

		$adminMaptilerApiKey = $this->appConfig->getValueString(Application::APP_ID, 'maptiler_api_key', lazy: true);
		$settings = [
			'maptiler_api_key' => $adminMaptilerApiKey,
			'proxy_osm' => false,
			'app_version' => $this->appConfig->getValueString(Application::APP_ID, 'installed_version'),
			'refresh_duration' => 125,
		];

		$serializedSession = $this->sessionService->serializeSession($session, true);
		$serializedSession['id'] = $token;
		foreach ($serializedSession['devices'] as $deviceId => $device) {
			$serializedSession['devices'][$deviceId]['session_id'] = $token;
		}

		$state = [
			'sessions' => [$token => $serializedSession],
			'settings' => $settings,
			'isPublicPage' => true,
		];
		$this->initialStateService->provideInitialState('phonetrack-state', $state);

		$response = new PublicTemplateResponse(Application::APP_ID, 'mainVue');
		$response->setHeaderTitle($this->l->t('PhoneTrack public access'));
		$response->setHeaderDetails($this->l->t('Watch session %s', [$session->getName()]));
		$response->setFooterVisible(false);

		$csp = new ContentSecurityPolicy();
		$csp->addAllowedFrameAncestorDomain('*');
		$this->mapService->addPageCsp($csp);
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * @param string $shareToken
	 * @param int $deviceId
	 * @param int $maxPoints
	 * @param int|null $minTimestamp
	 * @param int|null $maxTimestamp
	 * @param bool $combine
	 * @return DataResponse
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[PublicPage]
	public function getDevicePoints(
		string $shareToken, int $deviceId, int $maxPoints = 1000, ?int $minTimestamp = null, ?int $maxTimestamp = null,
		bool $combine = false,
	): DataResponse {
		try {
			$session = $this->sessionMapper->getSessionsByPublicViewToken($shareToken);
			$filters = [];
		} catch (DoesNotExistException $e) {
			try {
				$share = $this->publicShareMapper->findByShareToken($shareToken);
				$filters = json_decode($share->getFilters(), true);
				$session = $this->sessionMapper->find($share->getSessionId());
			} catch (DoesNotExistException $e) {
				return new DataResponse(['error' => 'session_not_found'], Http::STATUS_NOT_FOUND);
			}
		}
		try {
			$device = $this->deviceMapper->getBySessionIdAndDeviceId($session->getId(), $deviceId);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'device_not_found'], Http::STATUS_NOT_FOUND);
		}

		// let the timestamp user filters override the param ones
		if (isset($filters['timestampmin'])
			&& ($minTimestamp === null || $filters['timestampmin'] > $minTimestamp)) {
			$minTimestamp = $filters['timestampmin'];
		}
		if (isset($filters['timestampmax'])
			&& ($maxTimestamp === null || $filters['timestampmax'] < $maxTimestamp)) {
			$maxTimestamp = $filters['timestampmax'];
		}

		if ($minTimestamp !== null && $maxTimestamp !== null && $minTimestamp > $maxTimestamp) {
			return new DataResponse([]);
		}

		return new DataResponse(
			$combine
				? $this->sessionService->getDevicePointsCombined(
					$device->getId(), $minTimestamp, $maxTimestamp, $maxPoints,
					$filters['satellitesmin'] ?? null, $filters['satellitesmax'] ?? null,
					$filters['altitudemin'] ?? null, $filters['altitudemax'] ?? null,
					$filters['accuracymin'] ?? null, $filters['accuracymax'] ?? null,
					$filters['batterylevelmin'] ?? null, $filters['batterylevelmax'] ?? null,
					$filters['speedmin'] ?? null, $filters['speedmax'] ?? null,
					$filters['bearingmin'] ?? null, $filters['bearingmax'] ?? null,
				)
				: $this->pointMapper->getDevicePoints(
					$deviceId, $minTimestamp, $maxTimestamp, $maxPoints,
					$filters['satellitesmin'] ?? null, $filters['satellitesmax'] ?? null,
					$filters['altitudemin'] ?? null, $filters['altitudemax'] ?? null,
					$filters['accuracymin'] ?? null, $filters['accuracymax'] ?? null,
					$filters['batterylevelmin'] ?? null, $filters['batterylevelmax'] ?? null,
					$filters['speedmin'] ?? null, $filters['speedmax'] ?? null,
					$filters['bearingmin'] ?? null, $filters['bearingmax'] ?? null,
				)
		);
	}
}
