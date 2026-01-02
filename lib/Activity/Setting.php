<?php

/**
 * @copyright Copyright (c) 2019 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\PhoneTrack\Activity;

use OCA\PhoneTrack\AppInfo\Application;
use OCP\Activity\ActivitySettings;
use OCP\IL10N;

class Setting extends ActivitySettings {

	public function __construct(
		protected IL10N $l,
	) {
	}

	public function getIdentifier() {
		return 'phonetrack';
	}

	public function getName() {
		return $this->l->t('A <strong>PhoneTrack session</strong> has been shared or unshared');
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function getGroupIdentifier(): string {
		return Application::APP_ID;
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	public function getGroupName(): string {
		return $this->l->t('Phonetrack');
	}

	public function getPriority() {
		return 95;
	}

	public function canChangeStream() {
		return true;
	}

	public function isDefaultEnabledStream() {
		return true;
	}

	public function canChangeMail() {
		return true;
	}

	public function isDefaultEnabledMail() {
		return false;
	}
}
