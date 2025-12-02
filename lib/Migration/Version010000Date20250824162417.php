<?php

declare(strict_types=1);

namespace OCA\PhoneTrack\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010000Date20250824162417 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$schemaChanged = false;

		if ($schema->hasTable('phonetrack_pubshares')) {
			$table = $schema->getTable('phonetrack_pubshares');
			if (!$table->hasColumn('label')) {
				$table->addColumn('label', Types::STRING, [
					'notnull' => false,
					'default' => null,
					'length' => 128,
				]);
				$schemaChanged = true;
			}
		}
		if ($schema->hasTable('phonetrack_points')) {
			$table = $schema->getTable('phonetrack_points');
			if ($table->hasIndex('phonetrack_timestamp_index')) {
				$table->dropIndex('phonetrack_timestamp_index');
				$schemaChanged = true;
			}
			if (!$table->hasIndex('phonetrack_timestamp_devid_idx')) {
				$table->addIndex(['deviceid', 'timestamp'], 'phonetrack_timestamp_devid_idx');
				$schemaChanged = true;
			}
		}

		return $schemaChanged ? $schema : null;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}
}
