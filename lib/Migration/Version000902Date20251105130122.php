<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PhoneTrack\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version000902Date20251105130122 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[Override]
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if ($schema->hasTable('phonetrack_geofences')) {
			$table = $schema->getTable('phonetrack_geofences');
			if (!$table->hasColumn('urlenterusebody')) {
				$table->addColumn('urlenterusebody', 'integer', [
					'notnull' => true,
					'default' => 1,
				]);
			}
			if (!$table->hasColumn('urlleaveusebody')) {
				$table->addColumn('urlleaveusebody', 'integer', [
					'notnull' => true,
					'default' => 1,
				]);
			}
		}

		if ($schema->hasTable('phonetrack_proxims')) {
			$table = $schema->getTable('phonetrack_proxims');
			if (!$table->hasColumn('urlcloseusebody')) {
				$table->addColumn('urlcloseusebody', 'integer', [
					'notnull' => true,
					'default' => 1,
				]);
			}
			if (!$table->hasColumn('urlfarusebody')) {
				$table->addColumn('urlfarusebody', 'integer', [
					'notnull' => true,
					'default' => 1,
				]);
			}
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
