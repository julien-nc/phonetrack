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
use OCA\PhoneTrack\Db\Session;
use OCA\PhoneTrack\Db\SessionMapper;
use OCA\PhoneTrack\Service\SessionService;
use OCA\PhoneTrack\Service\ToolsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;

use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\IL10N;

use OCP\IRequest;
use Psr\Log\LoggerInterface;

class PageController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private LoggerInterface $logger,
		private IL10N $l10n,
		private SessionMapper $sessionMapper,
		private SessionService $sessionService,
		private IDBConnection $dbConnection,
		private IInitialState $initialStateService,
		private IAppConfig $appConfig,
		private ToolsService $toolsService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function indexVue(): TemplateResponse {
		$settings = $this->toolsService->getOptionsValues($this->userId);
		$adminMaptilerApiKey = $this->appConfig->getValueString(Application::APP_ID, 'maptiler_api_key', Application::DEFAULT_MAPTILER_API_KEY) ?: Application::DEFAULT_MAPTILER_API_KEY;
		$maptilerApiKey = $this->toolsService->getEncryptedUserValue($this->userId, 'maptiler_api_key') ?: $adminMaptilerApiKey;
		$settings['maptiler_api_key'] = $maptilerApiKey;
		$settings['proxy_osm'] = false;

		$sessions = $this->getSessions2();

		$state = [
			'sessions' => $sessions,
			'settings' => $settings,
		];
		$this->initialStateService->provideInitialState('phonetrack-state', $state);
		$response = new TemplateResponse(Application::APP_ID, 'mainVue');
		$csp = new ContentSecurityPolicy();
		$this->addPageCsp($csp);
		$response->setContentSecurityPolicy($csp);
		return $response;
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

	private function getSessions2(): array {
		$sessions = $this->sessionMapper->findByUser($this->userId);
		$sessions = array_map(function (Session $session) {
			$json = $session->jsonSerialize();
			$json['shared_with'] = $this->sessionService->getUserShares($session->getToken());
			$json['reserved_names'] = $this->sessionService->getReservedNames($session->getToken());
			$json['public_shares'] = $this->sessionService->getPublicShares($session->getToken());
			$json['devices'] = $this->sessionService->getDevices($session->getToken());
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
			$json['shared_with'] = $this->sessionService->getUserShares($session->getToken());
			$json['reserved_names'] = $this->sessionService->getReservedNames($session->getToken());
			$json['public_shares'] = $this->sessionService->getPublicShares($session->getToken());
			$json['devices'] = $this->sessionService->getDevices($session->getToken());
			$json['token'] = $sidToShareToken[$json['id']];
			return $json;
		}, $sharedSessions);

		return array_merge($sessions, $sharedSessions);
	}
}
