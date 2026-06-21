<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PhoneTrack\AppInfo;

use OCA\PhoneTrack\Listener\UserDeletedListener;
use OCA\PhoneTrack\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap {

	public const APP_ID = 'phonetrack';

	public const ACTIVITY_PROXIMITY_EVENT = 'phonetrack_proximity_event';
	public const ACTIVITY_GEOFENCE_EVENT = 'phonetrack_geofence_event';

	public const TILE_SERVER_RASTER = 0;
	public const TILE_SERVER_VECTOR = 1;

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerNotifierService(Notifier::class);
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
	}

	public function boot(IBootContext $context): void {
	}

}
