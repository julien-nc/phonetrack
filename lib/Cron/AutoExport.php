<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
		iterator_to_array($this->sessionService->cronAutoExport());
	}
}
