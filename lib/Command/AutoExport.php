<?php

/**
 * Nextcloud - PhoneTrack
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2024
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
		$this->sessionService->cronAutoExport();
		return 0;
	}
}
