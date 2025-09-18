<?php

namespace OCA\PhoneTrack\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Device>
 */
class DeviceMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'phonetrack_devices', Device::class);
	}

	public function find($id) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	public function findBySessionId(string $sessionId) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('sessionid', $qb->createNamedParameter($sessionId, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntities($qb);
	}


	public function getBySessionTokenAndDeviceId(string $sessionToken, int $deviceId): Device {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('sessionid', $qb->createNamedParameter($sessionToken, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param string $sessionToken
	 * @param string $name
	 * @return Device
	 * @throws Exception
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getByName(string $sessionToken, string $name): Device {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('sessionid', $qb->createNamedParameter($sessionToken, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}

	public function deleteDevice(string $sessionToken, int $deviceId): void {
		$this->deleteDevicePoints($deviceId);

		$qb = $this->db->getQueryBuilder();
		$qb->delete('phonetrack_devices')
			->where(
				$qb->expr()->eq('sessionid', $qb->createNamedParameter($sessionToken, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
	}

	public function deleteDevicePoints(int $deviceId) {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('phonetrack_points')
			->where(
				$qb->expr()->eq('deviceid', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
	}

	public function deletePointsOlderThan(int $deviceId, int $timestamp) {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('phonetrack_points')
			->where(
				$qb->expr()->eq('deviceid', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->lt('timestamp', $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
	}

	public function countDevicesPerSession(string $token): int {
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'count_devs')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('sessionid', $qb->createNamedParameter($token))
			);

		$req = $qb->executeQuery();
		return (int)$req->fetchOne();
	}

	/**
	 * @param int $deviceId
	 * @param array|null $filters
	 * @return int
	 * @throws Exception
	 */
	public function countPointsPerDevice(int $deviceId, ?array $filters = null): int {
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'count_points')
			->from('phonetrack_points')
			->where(
				$qb->expr()->eq('deviceid', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT))
			);

		if ($filters !== null) {
			$qb = self::applyQueryFilters($qb, $filters);
		}

		$req = $qb->executeQuery();
		return (int)$req->fetchOne();
	}

	/**
	 * @param int $deviceId
	 * @param array|null $filters
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return array
	 * @throws Exception
	 */
	public function getDevicePoints(int $deviceId, ?array $filters = null, ?int $limit = null, ?int $offset = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('phonetrack_points')
			->where(
				$qb->expr()->eq('deviceid', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT))
			);

		if ($filters !== null) {
			$qb = self::applyQueryFilters($qb, $filters);
		}

		$qb->orderBy('timestamp', 'ASC');

		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		if ($offset !== null) {
			$qb->setFirstResult($offset);
		}

		$req = $qb->executeQuery();
		$points = $req->fetchAll();
		$qb->resetQueryParts();
		return $points;
	}

	/**
	 * @param IQueryBuilder $qb
	 * @param array $filters
	 * @return IQueryBuilder
	 */
	public static function applyQueryFilters(IQueryBuilder $qb, array $filters): IQueryBuilder {
		if (isset($filters['satellites'])) {
			if (isset($filters['satellites']['min'])) {
				$qb->andWhere(
					$qb->expr()->gte('satellites', $qb->createNamedParameter($filters['satellites']['min'], IQueryBuilder::PARAM_INT))
				);
			}
			if (isset($filters['satellites']['max'])) {
				$qb->andWhere(
					$qb->expr()->lte('satellites', $qb->createNamedParameter($filters['satellites']['max'], IQueryBuilder::PARAM_INT))
				);
			}
		}
		foreach (['timestamp', 'altitude', 'accuracy', 'batterylevel', 'speed', 'bearing'] as $column) {
			if (isset($filters[$column])) {
				if (isset($filters[$column]['min'])) {
					$qb->andWhere(
						$qb->expr()->gte($column, $qb->createNamedParameter($filters[$column]['min']))
					);
				}
				if (isset($filters[$column]['max'])) {
					$qb->andWhere(
						$qb->expr()->lte($column, $qb->createNamedParameter($filters[$column]['max']))
					);
				}
			}
		}
		return $qb;
	}
}
