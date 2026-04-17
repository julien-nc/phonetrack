<?php

declare(strict_types=1);

namespace OCA\PhoneTrack\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\DB\Types;

class Version010000Date20260417095600 extends SimpleMigrationStep {

	public function __construct() {}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {}

    /** Add the missing database columns for the Device and Session models
     * that weren't added when upgrading from <1 to 1.0
     */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		$schema = $schemaClosure();
		$schemaChanged = false;

        // add missing columns for Device.php
		if ($schema->hasTable('phonetrack_devices')) {
			$table = $schema->getTable('phonetrack_devices');

            if (!$table->hasColumn('line_enabled')) {
                $table->addColumn('line_enabled', Types::INTEGER, [
                    'notnull' => true,
					'default' => 0,
					'unsigned' => true,
				]);
                $schemaChanged = true;
            }

            if (!$table->hasColumn('auto_zoom')) {
                $table->addColumn('auto_zoom', Types::INTEGER, [
                    'notnull' => true,
                    'default' => 0,
                    'unsigned' => true,
                ]);
                $schemaChanged = true;
            }

            if (!$table->hasColumn('color_criteria')) {
                $table->addColumn('color_criteria', Types::INTEGER, [
                    'notnull' => true,
                    'default' => 0,
                    'unsigned' => true,
                ]);
                $schemaChanged = true;
            }

            if (!$table->hasColumn('enabled')) {
                $table->addColumn('enabled', Types::INTEGER, [
                    'notnull' => true,
                    'default' => 0,
                    'unsigned' => true,
                ]);
                $schemaChanged = true;
            }
		}

        // add missing Session.php column
        if ($schema->hasTable('phonetrack_sessions')) {
            $table = $schema->getTable('phonetrack_sessions');

            if (!$table->hasColumn('enabled')) {
                $table->addColumn('enabled', Types::INTEGER, [
                    'notnull' => true,
                    'default' => 0,
                    'unsigned' => true,
                ]);
                $schemaChanged = true;
            }
        }

		return $schemaChanged ? $schema : null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {}
}
