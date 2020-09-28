<?php
/**
 * Nextcloud - PhoneTrack
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 */

namespace OCA\PhoneTrack\Cron;

use \OCA\PhoneTrack\AppInfo\Application;
use \OCA\PhoneTrack\Service\SessionService;

class AutoExport extends \OC\BackgroundJob\TimedJob {

	public function __construct(SessionService $sessionService) {
	$this->sessionService = $sessionService;
		// Run each day
		$this->setInterval(24 * 60 * 60);
	}

	protected function run($argument) {
		$d = new \DateTime();
		$this->sessionService->cronAutoExport();
	}

}
