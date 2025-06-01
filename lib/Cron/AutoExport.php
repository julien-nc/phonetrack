<?php

/**
 * Nextcloud - PhoneTrack
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 */

namespace OCA\PhoneTrack\Cron;

use OCA\PhoneTrack\Service\SessionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class AutoExport extends TimedJob {

	public function __construct(
		ITimeFactory $time,
		private SessionService $sessionService,
	) {
		parent::__construct($time);
		// Run each day
		$this->setInterval(24 * 60 * 60);
	}

	protected function run($argument): void {
		$this->sessionService->cronAutoExport();
	}
}
