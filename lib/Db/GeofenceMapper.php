<?php

namespace OCA\PhoneTrack\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Geofence>
 */
class GeofenceMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'phonetrack_geofences', Geofence::class);
	}

	public function find(int $id): Geofence {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param int $deviceId
	 * @return Geofence[]
	 * @throws Exception
	 */
	public function findByDeviceId(int $deviceId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('deviceid', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @param int $deviceId
	 * @return int
	 * @throws Exception
	 */
	public function deleteByDeviceId(int $deviceId): int {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('deviceid', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT))
			);

		$nbDeleted = $qb->executeStatement();
		return $nbDeleted;
	}
}
