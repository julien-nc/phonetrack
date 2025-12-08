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
	 * @param int $pointId
	 * @return Point
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getDevicePoint(int $deviceId, int $pointId): Point {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('deviceid', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($pointId, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param int $deviceId
	 * @return Point
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getLastDevicePoint(int $deviceId): Point {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('deviceid', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT))
			)
			->orderBy('timestamp', 'DESC')
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	/**
	 * @param int $deviceId
	 * @param int|null $minTimestamp
	 * @param int|null $maxTimestamp
	 * @param int $maxPoints
	 * @return array
	 * @throws Exception
	 */
	public function getDevicePoints(
		int $deviceId, ?int $minTimestamp = null, ?int $maxTimestamp = null, int $maxPoints = 1000,
	): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('deviceid', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT))
			);
		if ($minTimestamp !== null) {
			$qb->andWhere(
				$qb->expr()->gt('timestamp', $qb->createNamedParameter($minTimestamp, IQueryBuilder::PARAM_INT))
			);
		}
		if ($maxTimestamp !== null) {
			$qb->andWhere(
				$qb->expr()->lt('timestamp', $qb->createNamedParameter($maxTimestamp, IQueryBuilder::PARAM_INT))
			);
		}
		// sort order is DESC to make sure we get the most recent points with the limit (maxPoints),
		// we reverse the order anyway
		$qb->orderBy('timestamp', 'DESC');
		$qb->setMaxResults($maxPoints);

		return array_reverse($this->findEntities($qb));
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

	/**
	 * @param int $deviceId
	 * @param float $lat
	 * @param float $lon
	 * @param int $timestamp
	 * @param float|null $accuracy
	 * @param float|null $altitude
	 * @param float|null $batterylevel
	 * @param int|null $satellites
	 * @param string $useragent
	 * @param float|null $speed
	 * @param float|null $bearing
	 * @return Point
	 * @throws Exception
	 */
	public function addPoint(
		int $deviceId, float $lat, float $lon, int $timestamp,
		?float $accuracy = null, ?float $altitude = null, ?float $batterylevel = null, ?int $satellites = null,
		string $useragent = '', ?float $speed = null, ?float $bearing = null,
	): Point {
		$point = new Point();
		$point->setDeviceid($deviceId);
		$point->setLat($lat);
		$point->setLon($lon);
		$point->setTimestamp($timestamp);
		$point->setUseragent('');
		$point->setAccuracy($accuracy);
		$point->setAltitude($altitude);
		$point->setBatterylevel($batterylevel);
		$point->setSatellites($satellites);
		$point->setUseragent($useragent);
		$point->setSpeed($speed);
		$point->setBearing($bearing);
		return $this->insert($point);
	}
}
