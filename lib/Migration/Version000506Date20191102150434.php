<?php

declare(strict_types=1);

namespace OCA\PhoneTrack\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version000506Date20191102150434 extends SimpleMigrationStep {

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

		if (!$schema->hasTable('phonetrack_sessions')) {
			$table = $schema->createTable('phonetrack_sessions');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('user', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => true,
				'length' => 300,
			]);
			$table->addColumn('token', 'string', [
				'notnull' => true,
				'length' => 300,
				'default' => '',
			]);
			$table->addColumn('publicviewtoken', 'string', [
				'notnull' => true,
				'length' => 300,
				'default' => '',
			]);
			$table->addColumn('public', 'smallint', [
				'notnull' => false,
				'length' => 1,
				'default' => 1,
			]);
			$table->addColumn('locked', 'smallint', [
				'notnull' => false,
				'length' => 1,
				'default' => 0,
			]);
			$table->addColumn('creationversion', 'string', [
				'notnull' => false,
				'length' => 300,
			]);
			$table->addColumn('autoexport', 'string', [
				'notnull' => true,
				'length' => 10,
				'default' => 'no',
			]);
			$table->addColumn('autopurge', 'string', [
				'notnull' => true,
				'length' => 10,
				'default' => 'no',
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('phonetrack_devices')) {
			$table = $schema->createTable('phonetrack_devices');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => true,
				'length' => 300,
			]);
			$table->addColumn('alias', 'string', [
				'notnull' => false,
				'length' => 300,
			]);
			$table->addColumn('sessionid', 'string', [
				'notnull' => true,
				'length' => 300,
				'default' => '',
			]);
			$table->addColumn('color', 'string', [
				'notnull' => false,
				'length' => 300,
			]);
			$table->addColumn('shape', 'string', [
				'notnull' => false,
				'length' => 1,
			]);
			$table->addColumn('nametoken', 'string', [
				'notnull' => false,
				'length' => 300,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('phonetrack_points')) {
			$table = $schema->createTable('phonetrack_points');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('deviceid', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('lat', 'float', [
				'notnull' => true,
				'length' => 10,
			]);
			$table->addColumn('lon', 'float', [
				'notnull' => true,
				'length' => 10,
			]);
			$table->addColumn('timestamp', 'bigint', [
				'notnull' => true,
				'length' => 10,
			]);
			$table->addColumn('accuracy', 'float', [
				'notnull' => false,
				'length' => 4,
			]);
			$table->addColumn('satellites', 'integer', [
				'notnull' => false,
				'length' => 4,
			]);
			$table->addColumn('altitude', 'float', [
				'notnull' => false,
				'length' => 4,
			]);
			$table->addColumn('batterylevel', 'float', [
				'notnull' => false,
				'length' => 4,
			]);
			$table->addColumn('useragent', 'string', [
				'notnull' => true,
				'length' => 100,
				'default' => 'nothing',
			]);
			$table->addColumn('speed', 'float', [
				'notnull' => false,
				'length' => 4,
			]);
			$table->addColumn('bearing', 'float', [
				'notnull' => false,
				'length' => 4,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['deviceid'], 'phonetrack_deviceid_index');
			$table->addIndex(['timestamp'], 'phonetrack_timestamp_index');
		}

		if (!$schema->hasTable('phonetrack_geofences')) {
			$table = $schema->createTable('phonetrack_geofences');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => true,
				'length' => 100,
				'default' => 'default',
			]);
			$table->addColumn('deviceid', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('latmin', 'float', [
				'notnull' => true,
				'length' => 10,
			]);
			$table->addColumn('lonmin', 'float', [
				'notnull' => true,
				'length' => 10,
			]);
			$table->addColumn('latmax', 'float', [
				'notnull' => true,
				'length' => 10,
			]);
			$table->addColumn('lonmax', 'float', [
				'notnull' => true,
				'length' => 10,
			]);
			$table->addColumn('urlenter', 'string', [
				'notnull' => false,
				'length' => 500,
			]);
			$table->addColumn('urlenterpost', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->addColumn('urlleave', 'string', [
				'notnull' => false,
				'length' => 500,
			]);
			$table->addColumn('urlleavepost', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->addColumn('sendemail', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 1,
			]);
			$table->addColumn('emailaddr', 'string', [
				'notnull' => false,
				'length' => 500,
			]);
			$table->addColumn('sendnotif', 'integer', [
				'notnull' => true,
				'default' => 1,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('phonetrack_proxims')) {
			$table = $schema->createTable('phonetrack_proxims');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('deviceid1', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('deviceid2', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('lowlimit', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('highlimit', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('urlclose', 'string', [
				'notnull' => false,
				'length' => 500,
			]);
			$table->addColumn('urlclosepost', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->addColumn('urlfar', 'string', [
				'notnull' => false,
				'length' => 500,
			]);
			$table->addColumn('urlfarpost', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->addColumn('sendemail', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 1,
			]);
			$table->addColumn('emailaddr', 'string', [
				'notnull' => false,
				'length' => 500,
			]);
			$table->addColumn('sendnotif', 'integer', [
				'notnull' => true,
				'default' => 1,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('phonetrack_filtersb')) {
			$table = $schema->createTable('phonetrack_filtersb');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('username', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => true,
				'length' => 100,
				'default' => 'default',
			]);
			$table->addColumn('filterjson', 'string', [
				'notnull' => true,
				'length' => 300,
				'default' => '{}',
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('phonetrack_tileserver')) {
			$table = $schema->createTable('phonetrack_tileserver');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('user', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('type', 'string', [
				'notnull' => true,
				'length' => 20,
				'default' => 'tile',
			]);
			$table->addColumn('servername', 'string', [
				'notnull' => true,
				'length' => 300,
			]);
			$table->addColumn('url', 'string', [
				'notnull' => true,
				'length' => 300,
			]);
			$table->addColumn('token', 'string', [
				'notnull' => true,
				'length' => 300,
				'default' => 'no-token',
			]);
			$table->addColumn('format', 'string', [
				'notnull' => true,
				'length' => 300,
				'default' => 'image/jpeg',
			]);
			$table->addColumn('layers', 'string', [
				'notnull' => true,
				'length' => 300,
				'default' => '',
			]);
			$table->addColumn('version', 'string', [
				'notnull' => true,
				'length' => 30,
				'default' => '1.1.1',
			]);
			$table->addColumn('opacity', 'string', [
				'notnull' => true,
				'length' => 10,
				'default' => '0.4',
			]);
			$table->addColumn('transparent', 'string', [
				'notnull' => true,
				'length' => 10,
				'default' => 'true',
			]);
			$table->addColumn('minzoom', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 1,
			]);
			$table->addColumn('maxzoom', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 18,
			]);
			$table->addColumn('attribution', 'string', [
				'notnull' => true,
				'length' => 300,
				'default' => '???',
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('phonetrack_shares')) {
			$table = $schema->createTable('phonetrack_shares');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('sessionid', 'string', [
				'notnull' => true,
				'length' => 300,
				'default' => '',
			]);
			$table->addColumn('username', 'string', [
				'notnull' => true,
				'length' => 300,
				'default' => '',
			]);
			$table->addColumn('sharetoken', 'string', [
				'notnull' => true,
				'length' => 300,
				'default' => '',
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('phonetrack_pubshares')) {
			$table = $schema->createTable('phonetrack_pubshares');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('sessionid', 'string', [
				'notnull' => true,
				'length' => 300,
				'default' => '',
			]);
			$table->addColumn('sharetoken', 'string', [
				'notnull' => true,
				'length' => 300,
				'default' => '',
			]);
			$table->addColumn('filters', 'string', [
				'notnull' => true,
				'length' => 500,
				'default' => '{}',
			]);
			$table->addColumn('devicename', 'string', [
				'notnull' => false,
				'length' => 300,
			]);
			$table->addColumn('lastposonly', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->addColumn('geofencify', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
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
