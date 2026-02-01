<?php

declare(strict_types=1);

namespace OCA\PhoneTrack\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010000Date20251207171022 extends SimpleMigrationStep {

	public function __construct(
		private IDBConnection $db,
	) {
	}

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

		if ($schema->hasTable('phonetrack_devices')) {
			$table = $schema->getTable('phonetrack_devices');
			if (!$table->hasColumn('session_id')) {
				$table->addColumn('session_id', Types::BIGINT, [
					'notnull' => true,
					'default' => 0,
					'unsigned' => true,
				]);
				$schemaChanged = true;
			} else {
				$output->warning('Column phonetrack_devices.session_id already exists');
			}
			if (!$table->hasColumn('session_token')) {
				$table->addColumn('session_token', Types::STRING, [
					'notnull' => true,
					'default' => 'initial',
					'length' => 300,
				]);
				$schemaChanged = true;
			} else {
				$output->warning('Column phonetrack_devices.session_token already exists');
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
		$schema = $schemaClosure();
		if (!$schema->hasTable('phonetrack_devices')) {
			$output->warning('Missing table: phonetrack_devices');
			$output->warning('Skipping postSchemaChange in Version010000Date20251207171022');
			return;
		}
		$table = $schema->getTable('phonetrack_devices');
		foreach (['session_id', 'session_token', 'sessionid'] as $col) {
			if (!$table->hasColumn($col)) {
				$output->warning('Missing column: ' . $col . ' in table: ' . $table->getName());
				$output->warning('Skipping postSchemaChange in Version010000Date20251207171022');
				return;
			}
		}

		// set session_token <- sessionid
		$qbUpdateNewCol = $this->db->getQueryBuilder();
		$qbUpdateNewCol->update('phonetrack_devices')
			->set('session_token', 'sessionid');
		$qbUpdateNewCol->executeStatement();

		// set the session_id for all existing devices
		$qbUpdate = $this->db->getQueryBuilder();
		$qbUpdate->update('phonetrack_devices')
			->set('session_id', $qbUpdate->createParameter('session_id_param'))
			->where(
				$qbUpdate->expr()->eq('id', $qbUpdate->createParameter('device_id_param'))
			);

		$qbSelectSession = $this->db->getQueryBuilder();
		$qbSelectSession->select('id')
			->from('phonetrack_sessions')
			->where(
				$qbSelectSession->expr()->eq('token', $qbSelectSession->createParameter('session_token_param'))
			);

		$sessionTokenToId = [];

		$qbSelectDevices = $this->db->getQueryBuilder();
		$qbSelectDevices->select('id', 'session_token')
			->from('phonetrack_devices');
		$devicesResult = $qbSelectDevices->executeQuery();
		while ($row = $devicesResult->fetch()) {
			$deviceId = $row['id'];
			$sessionToken = $row['session_token'];
			$sessionId = null;
			if (isset($sessionTokenToId[$sessionToken])) {
				$sessionId = $sessionTokenToId[$sessionToken];
			} else {
				$qbSelectSession->setParameter('session_token_param', $sessionToken, IQueryBuilder::PARAM_STR);
				$req = $qbSelectSession->executeQuery();
				while ($row = $req->fetch()) {
					$sessionId = $row['id'];
					$sessionTokenToId[$sessionToken] = $sessionId;
				}
			}
			if ($sessionId !== null) {
				$qbUpdate->setParameter('session_id_param', $sessionId, IQueryBuilder::PARAM_INT);
				$qbUpdate->setParameter('device_id_param', $deviceId, IQueryBuilder::PARAM_INT);
				$qbUpdate->executeStatement();
			}
		}
	}
}
