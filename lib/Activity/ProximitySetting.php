<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\PhoneTrack\Activity;

use OCA\PhoneTrack\AppInfo\Application;

class ProximitySetting extends Setting {

	public function getIdentifier() {
		return Application::ACTIVITY_PROXIMITY_EVENT;
	}

	public function getName() {
		return $this->l->t('<strong>PhoneTrack device proximity</strong> events');
	}

}
