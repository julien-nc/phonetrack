<?php

declare(strict_types=1);

namespace OCA\PhoneTrack\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010000Date20250803151308 extends SimpleMigrationStep {

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

		if ($schema->hasTable('phonetrack_sessions')) {
			$table = $schema->getTable('phonetrack_sessions');
			if (!$table->hasColumn('enabled')) {
				$table->addColumn('enabled', Types::SMALLINT, [
					'notnull' => true,
					'default' => 0,
				]);
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('phonetrack_devices')) {
			$table = $schema->getTable('phonetrack_devices');
			if (!$table->hasColumn('enabled')) {
				$table->addColumn('enabled', Types::SMALLINT, [
					'notnull' => true,
					'default' => 0,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('line_enabled')) {
				$table->addColumn('line_enabled', Types::SMALLINT, [
					'notnull' => true,
					'default' => 0,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('auto_zoom')) {
				$table->addColumn('auto_zoom', Types::SMALLINT, [
					'notnull' => true,
					'default' => 0,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('color_criteria')) {
				$table->addColumn('color_criteria', Types::SMALLINT, [
					'notnull' => true,
					'default' => 0,
				]);
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
