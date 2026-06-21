<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PhoneTrack\Command;

use OC\Core\Command\Base;
use OCA\PhoneTrack\Service\SessionService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AutoExport extends Base {

	public function __construct(
		private SessionService $sessionService,
	) {
		parent::__construct();
	}

	protected function configure() {
		$this->setName('phonetrack:autoexport')
			->setDescription('Manually trigger the automatic export routine');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		foreach ($this->sessionService->cronAutoExport() as $message) {
			$output->writeln($message);
		}
		return 0;
	}
}
