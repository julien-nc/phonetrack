<?php

namespace OCA\PhoneTrack\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PDO;

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

	public function countPointsPerUser(string $userId): int {
		$qb = $this->db->getQueryBuilder();

		/*
		equivalent of
		SELECT count(*) as co
			FROM *PREFIX*phonetrack_points AS p
			INNER JOIN *PREFIX*phonetrack_devices AS d ON p.deviceid=d.id
			INNER JOIN *PREFIX*phonetrack_sessions AS s ON d.session_token=s.token
			WHERE s.' . $this->dbdblquotes . 'user' . $this->dbdblquotes . '=' . $this->db_quote_escape_string($userid) . ' ;
		*/

		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'count_points')
			->from($this->getTableName(), 'point')
			->innerJoin('point', 'phonetrack_devices', 'device', $qb->expr()->eq('device.id', 'point.deviceid'))
			->innerJoin('point', 'phonetrack_sessions', 'session', $qb->expr()->eq('device.session_id', 'session.id'))
			->where(
				$qb->expr()->eq('session.user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);

		$req = $qb->executeQuery();
		return (int)$req->fetchOne();
	}

	/**
	 * @param int $deviceId
	 * @param int $number
	 * @param string $dbType
	 * @return int
	 * @throws Exception
	 */
	public function deleteFirstPointsOfDevice(int $deviceId, int $number, string $dbType): int {
		if ($dbType === 'pgsql') {
			$deletionQuery = '
				DELETE FROM *PREFIX*phonetrack_points
				WHERE id IN (
					SELECT id
					FROM *PREFIX*phonetrack_points
					WHERE deviceid = ?
					ORDER BY timestamp ASC LIMIT ' . $number . '
				);
			';
			$deletionStatement = $this->db->prepare($deletionQuery);
			$res = $deletionStatement->execute([$deviceId]);
			return $res->rowCount();

			// I can't get this to work
			/*
			$qbSelectPointIds = $this->db->getQueryBuilder();
			$qbSelectPointIds->select('id')
				->from($this->getTableName(), 'p222')
				->where(
					$qbSelectPointIds->expr()->eq(
						'p222.deviceid',
						$qbSelectPointIds->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT),
						IQueryBuilder::PARAM_INT,
					)
				)
				->orderBy('timestamp', 'ASC')
				->setMaxResults($number);

			$qbDelete = $this->db->getQueryBuilder();
			$qbDelete->delete($this->getTableName(), 'p111')
				->where(
					$qbDelete->expr()->in(
						'p111.id',
						$qbDelete->createFunction($qbSelectPointIds->getSQL()),
						IQueryBuilder::PARAM_INT_ARRAY,
					)
				);
			return $qbDelete->executeStatement();
			*/
		}

		// db type is not postgres
		$deletionQuery = 'DELETE FROM *PREFIX*phonetrack_points
			WHERE deviceid = ?
			ORDER BY timestamp ASC LIMIT ' . $number . ' ;';

		$deletionStatement = $this->db->prepare($deletionQuery);
		$res = $deletionStatement->execute([$deviceId]);
		return $res->rowCount();
		// this does not work, the qb does not add the order by and limit
		/*
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('deviceid', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT))
			)
			->orderBy('timestamp', 'ASC')
			->setMaxResults($number);
		return $qb->executeStatement();
		*/
	}

	/**
	 * @param string $userId
	 * @param int $number
	 * @param string $dbType
	 * @return int
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	public function deleteFirstPointsOfUser(string $userId, int $number, string $dbType): int {
		if ($dbType === 'mysql') {
			$sqlSelect = '
				SELECT p.id AS id
				FROM *PREFIX*phonetrack_points AS p
				INNER JOIN *PREFIX*phonetrack_devices AS d ON p.deviceid = d.id
				INNER JOIN *PREFIX*phonetrack_sessions AS s ON d.session_token = s.token
				WHERE s.user = ?
				ORDER BY timestamp ASC LIMIT ' . $number . ' ;';
			$req = $this->db->prepare($sqlSelect);
			$res = $req->execute([$userId]);
			// since 33
			// $pids = $res->fetchFirstColumn();
			$pids = $res->fetchAll(PDO::FETCH_COLUMN);
			$res->closeCursor();

			$qbDelete = $this->db->getQueryBuilder();
			$qbDelete->delete($this->getTableName())
				->where(
					$qbDelete->expr()->eq('id', $qbDelete->createParameter('point_id_param'))
				);

			foreach ($pids as $pid) {
				$qbDelete->setParameter('point_id_param', $pid, IQueryBuilder::PARAM_INT);
				$qbDelete->executeStatement();
			}
			return count($pids);
		}

		// type is not MySQL
		$quote = $dbType === 'pgsql' ? '"' : '';
		$sqlSelect = '
			DELETE FROM *PREFIX*phonetrack_points
			WHERE *PREFIX*phonetrack_points.id IN
				(SELECT p.id
				FROM *PREFIX*phonetrack_points AS p
				INNER JOIN *PREFIX*phonetrack_devices AS d ON p.deviceid = d.id
				INNER JOIN *PREFIX*phonetrack_sessions AS s ON d.session_token = s.token
				WHERE s.' . $quote . 'user' . $quote . ' = ?
				ORDER BY timestamp ASC LIMIT ' . $number . ')
			 ;';
		$req = $this->db->prepare($sqlSelect);
		$res = $req->execute([$userId]);
		return $res->rowCount();
	}
}
