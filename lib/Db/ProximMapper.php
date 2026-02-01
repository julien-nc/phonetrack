<?php

namespace OCA\PhoneTrack\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Proxim>
 */
class ProximMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'phonetrack_proxims', Proxim::class);
	}

	public function find(int $id): Proxim {
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
	 * @return Proxim[]
	 * @throws Exception
	 */
	public function findByDeviceId(int $deviceId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('deviceid1', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT))
			)
			->orWhere(
				$qb->expr()->eq('deviceid2', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @param int $deviceId
	 * @return Proxim[]
	 * @throws Exception
	 */
	public function findByDeviceId1(int $deviceId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('deviceid1', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT))
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

		$qb->delete($this->getTableName());

		$or = $qb->expr()->orx();
		$or->add($qb->expr()->eq('deviceid1', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT)));
		$or->add($qb->expr()->eq('deviceid2', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT)));
		$qb->where($or);

		$nbDeleted = $qb->executeStatement();
		return $nbDeleted;
	}
}
