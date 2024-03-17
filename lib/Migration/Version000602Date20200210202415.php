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
class Version000602Date20200210202415 extends SimpleMigrationStep {

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

		if ($schema->hasTable('phonetrack_tileserver')) {
			$table = $schema->getTable('phonetrack_tileserver');
			if (!$table->hasColumn('token')) {
				$table->addColumn('token', 'string', [
					'notnull' => true,
					'length' => 300,
					'default' => 'no-token',
				]);
			}
		}

		if ($schema->hasTable('phonetrack_sessions')) {
			$table = $schema->getTable('phonetrack_sessions');
			if (!$table->hasColumn('locked')) {
				$table->addColumn('locked', 'smallint', [
					'notnull' => false,
					'length' => 1,
					'default' => 0,
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
