<?php

namespace OCA\PhoneTrack\Settings;

use OCA\PhoneTrack\AppInfo\Application;
use OCA\PhoneTrack\Db\TileServerMapper;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\Settings\ISettings;

class Admin implements ISettings {

	public function __construct(
		private IAppConfig $appConfig,
		private TileServerMapper $tileServerMapper,
		private IInitialState $initialStateService,
	) {
	}

	public function getForm() {
		$quota = $this->appConfig->getValueInt(Application::APP_ID, 'pointQuota');
		$proxyOsm = $this->appConfig->getValueString(Application::APP_ID, 'proxy_osm', '1') === '1';

		$adminTileServers = $this->tileServerMapper->getTileServersOfUser(null);

		$adminConfig = [
			// do not expose the stored value to the user
			'maptiler_api_key' => 'dummyApiKey',
			'extra_tile_servers' => $adminTileServers,
			'point_quota' => $quota,
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
