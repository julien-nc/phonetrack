<?php

declare(strict_types=1);

namespace OCA\PhoneTrack\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version000603Date20200215183145 extends SimpleMigrationStep {

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

		if ($schema->hasTable('phonetrack_devices')) {
			$table = $schema->getTable('phonetrack_devices');
			if (!$table->hasColumn('alias')) {
				$table->addColumn('alias', 'string', [
					'notnull' => false,
					'length' => 300,
				]);
			}
			if (!$table->hasColumn('color')) {
				$table->addColumn('color', 'string', [
					'notnull' => false,
					'length' => 300,
				]);
			}
			if (!$table->hasColumn('shape')) {
				$table->addColumn('shape', 'string', [
					'notnull' => false,
					'length' => 1,
				]);
			}
			if (!$table->hasColumn('nametoken')) {
				$table->addColumn('nametoken', 'string', [
					'notnull' => false,
					'length' => 300,
				]);
			}
		}

		if ($schema->hasTable('phonetrack_sessions')) {
			$table = $schema->getTable('phonetrack_sessions');
			if (!$table->hasColumn('publicviewtoken')) {
				$table->addColumn('publicviewtoken', 'string', [
					'notnull' => true,
					'length' => 300,
					'default' => '',
				]);
			}
			if (!$table->hasColumn('public')) {
				$table->addColumn('public', 'smallint', [
					'notnull' => false,
					'length' => 1,
					'default' => 1,
				]);
			}
			if (!$table->hasColumn('locked')) {
				$table->addColumn('locked', 'smallint', [
					'notnull' => false,
					'length' => 1,
					'default' => 0,
				]);
			}
			if (!$table->hasColumn('creationversion')) {
				$table->addColumn('creationversion', 'string', [
					'notnull' => false,
					'length' => 300,
				]);
			}
			if (!$table->hasColumn('autoexport')) {
				$table->addColumn('autoexport', 'string', [
					'notnull' => true,
					'length' => 10,
					'default' => 'no',
				]);
			}
			if (!$table->hasColumn('autopurge')) {
				$table->addColumn('autopurge', 'string', [
					'notnull' => true,
					'length' => 10,
					'default' => 'no',
				]);
			}
		}

		if ($schema->hasTable('phonetrack_geofences')) {
			$table = $schema->getTable('phonetrack_geofences');
			if (!$table->hasColumn('urlenterpost')) {
				$table->addColumn('urlenterpost', 'integer', [
					'notnull' => true,
					'length' => 4,
					'default' => 0,
				]);
			}
			if (!$table->hasColumn('urlleavepost')) {
				$table->addColumn('urlleavepost', 'integer', [
					'notnull' => true,
					'length' => 4,
					'default' => 0,
				]);
			}
			if (!$table->hasColumn('sendemail')) {
				$table->addColumn('sendemail', 'integer', [
					'notnull' => true,
					'length' => 4,
					'default' => 1,
				]);
			}
			if (!$table->hasColumn('emailaddr')) {
				$table->addColumn('emailaddr', 'string', [
					'notnull' => false,
					'length' => 500,
				]);
			}
			if (!$table->hasColumn('sendnotif')) {
				$table->addColumn('sendnotif', 'integer', [
					'notnull' => true,
					'default' => 1,
				]);
			}
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}
}
