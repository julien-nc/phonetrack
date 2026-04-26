<?php

declare(strict_types=1);

namespace OCA\PhoneTrack\Migration;

use Closure;
use Doctrine\DBAL\Types\Type;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010200Date20260416223500 extends SimpleMigrationStep {

	/**
	 * Add the missing database columns for the Device and Session models
	 * that weren't added when upgrading from <1 to 1.0
	 * If they are already there, make sure they have the right type and make them unsigned
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		$schema = $schemaClosure();
		$schemaChanged = false;

		if ($schema->hasTable('phonetrack_devices')) {
			$table = $schema->getTable('phonetrack_devices');

			if (!$table->hasColumn('line_enabled')) {
				$table->addColumn('line_enabled', Types::INTEGER, [
					'notnull' => true,
					'default' => 0,
					'unsigned' => true,
				]);
				$schemaChanged = true;
			} else {
				$column = $table->getColumn('line_enabled');
				if ($column->getType() !== Types::INTEGER || !$column->getUnsigned()) {
					$column->setType(Type::getType(Types::INTEGER));
					$column->setUnsigned(true);
					$schemaChanged = true;
				}
			}

			if (!$table->hasColumn('auto_zoom')) {
				$table->addColumn('auto_zoom', Types::INTEGER, [
					'notnull' => true,
					'default' => 0,
					'unsigned' => true,
				]);
				$schemaChanged = true;
			} else {
				$column = $table->getColumn('auto_zoom');
				if ($column->getType() !== Types::INTEGER || !$column->getUnsigned()) {
					$column->setType(Type::getType(Types::INTEGER));
					$column->setUnsigned(true);
					$schemaChanged = true;
				}
			}

			if (!$table->hasColumn('color_criteria')) {
				$table->addColumn('color_criteria', Types::INTEGER, [
					'notnull' => true,
					'default' => 0,
					'unsigned' => true,
				]);
				$schemaChanged = true;
			} else {
				$column = $table->getColumn('color_criteria');
				if ($column->getType() !== Types::INTEGER || !$column->getUnsigned()) {
					$column->setType(Type::getType(Types::INTEGER));
					$column->setUnsigned(true);
					$schemaChanged = true;
				}
			}

			if (!$table->hasColumn('enabled')) {
				$table->addColumn('enabled', Types::INTEGER, [
					'notnull' => true,
					'default' => 0,
					'unsigned' => true,
				]);
				$schemaChanged = true;
			} else {
				$column = $table->getColumn('enabled');
				if ($column->getType() !== Types::INTEGER || !$column->getUnsigned()) {
					$column->setType(Type::getType(Types::INTEGER));
					$column->setUnsigned(true);
					$schemaChanged = true;
				}
			}
		}

		if ($schema->hasTable('phonetrack_sessions')) {
			$table = $schema->getTable('phonetrack_sessions');

			if (!$table->hasColumn('enabled')) {
				$table->addColumn('enabled', Types::INTEGER, [
					'notnull' => true,
					'default' => 0,
					'unsigned' => true,
				]);
				$schemaChanged = true;
			} else {
				$column = $table->getColumn('enabled');
				if ($column->getType() !== Types::INTEGER || !$column->getUnsigned()) {
					$column->setType(Type::getType(Types::INTEGER));
					$column->setUnsigned(true);
					$schemaChanged = true;
				}
			}
		}

		return $schemaChanged ? $schema : null;
	}
}
