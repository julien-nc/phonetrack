<?php

namespace OCA\PhoneTrack\Settings;

use OCA\PhoneTrack\AppInfo\Application;
use OCA\PhoneTrack\Db\TileServerMapper;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Services\IInitialState;
use OCP\Exceptions\AppConfigTypeConflictException;
use OCP\Settings\ISettings;

class Admin implements ISettings {

	public function __construct(
		private IAppConfig $appConfig,
		private TileServerMapper $tileServerMapper,
		private IInitialState $initialStateService,
	) {
	}

	public function getForm() {
		try {
			$quota = $this->appConfig->getAppValueInt('pointQuota', 0, lazy: true);
		} catch (AppConfigTypeConflictException $e) {
			$quota = (int)$this->appConfig->getAppValueString('pointQuota', '0', lazy: true);
		}
		$proxyOsm = $this->appConfig->getAppValueString('proxy_osm', '1', lazy: true) === '1';
		$adminMaptilerApiKey = $this->appConfig->getAppValueString('maptiler_api_key', lazy: true);

		$adminTileServers = $this->tileServerMapper->getTileServersOfUser(null);

		$adminConfig = [
			// do not expose the stored value to the user
			'maptiler_api_key' => $adminMaptilerApiKey === '' ? '' : 'dummyApiKey',
			'extra_tile_servers' => $adminTileServers,
			'pointQuota' => $quota,
			'proxy_osm' => $proxyOsm,
		];
		$this->initialStateService->provideInitialState('admin-config', $adminConfig);
		return new TemplateResponse('phonetrack', 'adminSettings');
	}

	public function getSection() {
		return 'additional';
	}

	public function getPriority() {
		return 5;
	}
}
