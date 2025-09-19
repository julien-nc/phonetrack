<?php

namespace OCA\PhoneTrack\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Point>
 */
class PointMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'phonetrack_points', Point::class);
	}

	/**
	 * @param $id
	 * @return Point
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function find($id): Point {
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
	 * @param float|null $minTimestamp
	 * @param float|null $maxTimestamp
	 * @param string|null $sortOrder
	 * @return array
	 * @throws Exception
	 */
	public function getDevicePoints(int $deviceId, ?float $minTimestamp = null, ?float $maxTimestamp = null, ?string $sortOrder = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('deviceid', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT))
			);
		if ($minTimestamp !== null) {
			$qb->andWhere(
				$qb->expr()->gt('timestamp', $qb->createNamedParameter($minTimestamp, IQueryBuilder::PARAM_STR))
			);
		}
		if ($maxTimestamp !== null) {
			$qb->andWhere(
				$qb->expr()->lt('timestamp', $qb->createNamedParameter($maxTimestamp, IQueryBuilder::PARAM_STR))
			);
		}
		if ($sortOrder !== null) {
			$qb->orderBy('timestamp', $sortOrder);
		}

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
