<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PhoneTrack\Activity;

use OCA\PhoneTrack\AppInfo\Application;
use OCP\Activity\IFilter;
use OCP\IL10N;
use OCP\IURLGenerator;

class Filter implements IFilter {

	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
	) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
	}

	public function getIdentifier() {
		return 'phonetrack';
	}

	public function getName() {
		return $this->l10n->t('PhoneTrack');
	}

	public function getPriority() {
		return 95;
	}

	public function getIcon() {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app_black.svg');
	}

	public function filterTypes(array $types) {
		return $types;
	}

	public function allowedApps() {
		return [Application::APP_ID];
	}
}
