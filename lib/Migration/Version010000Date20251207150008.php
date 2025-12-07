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

class Version010000Date20251207150008 extends SimpleMigrationStep {

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

		if ($schema->hasTable('phonetrack_shares')) {
			$table = $schema->getTable('phonetrack_shares');
			if (!$table->hasColumn('session_id')) {
				$table->addColumn('session_id', Types::BIGINT, [
					'notnull' => true,
					'default' => 0,
					'unsigned' => true,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('session_token')) {
				$table->addColumn('session_token', Types::STRING, [
					'notnull' => true,
					'default' => 'initial',
					'length' => 300,
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
		// set session_token <- sessionid
		$qbUpdateNewCol = $this->db->getQueryBuilder();
		$qbUpdateNewCol->update('phonetrack_shares')
			->set('session_token', 'sessionid');
		$qbUpdateNewCol->executeStatement();

		// set the session_id for all existing shares
		$qbUpdate = $this->db->getQueryBuilder();
		$qbUpdate->update('phonetrack_shares')
			->set('session_id', $qbUpdate->createParameter('session_id_param'))
			->where(
				$qbUpdate->expr()->eq('id', $qbUpdate->createParameter('share_id_param'))
			);

		$qbSelectSession = $this->db->getQueryBuilder();
		$qbSelectSession->select('id')
			->from('phonetrack_sessions')
			->where(
				$qbSelectSession->expr()->eq('token', $qbSelectSession->createParameter('session_token_param'))
			);

		$sessionTokenToId = [];

		$qbSelectShares = $this->db->getQueryBuilder();
		$qbSelectShares->select('id', 'session_token')
			->from('phonetrack_shares');
		$sharesResult = $qbSelectShares->executeQuery();
		while ($row = $sharesResult->fetch()) {
			$shareId = $row['id'];
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
				$qbUpdate->setParameter('share_id_param', $shareId, IQueryBuilder::PARAM_INT);
				$qbUpdate->executeStatement();
			}
		}
	}
}
