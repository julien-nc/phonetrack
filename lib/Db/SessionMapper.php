<?php

/**
 * Nextcloud - phonetrack
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net
 * @copyright Julien Veyssier 2019
 */

namespace OCA\PhoneTrack\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Session>
 */
class SessionMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'phonetrack_sessions', Session::class);
	}

	/**
	 * @param $id
	 * @return Session
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function find($id): Session {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param string $token
	 * @return Session
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByToken(string $token): Session {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param string $userId
	 * @return Session[]
	 * @throws Exception
	 */
	public function findByUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @param $value
	 * @return Session[]
	 * @throws Exception
	 */
	public function findByAutoPurge($value): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('autopurge', $qb->createNamedParameter($value, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @param string $token
	 * @param string $userId
	 * @return string|null
	 * @throws Exception
	 */
	public function isSharedWith(string $token, string $userId): ?string {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('phonetrack_shares')
			->where(
				$qb->expr()->eq('sharetoken', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('username', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			return $row['sessionid'];
		}

		return null;
	}

	/**
	 * @param string $token
	 * @param array|null $filters
	 * @return int
	 * @throws Exception
	 */
	public function countPointsPerSession(string $token, ?array $filters = null): int {
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'count_points')
			->from('phonetrack_devices', 'dev')
			->innerJoin('dev', 'phonetrack_points', 'poi', $qb->expr()->eq('dev.id', 'poi.deviceid'))
			->where(
				$qb->expr()->eq('sessionid', $qb->createNamedParameter($token))
			);

		if ($filters !== null) {
			$qb = DeviceMapper::applyQueryFilters($qb, $filters);
		}

		$req = $qb->executeQuery();
		return (int) $req->fetchOne();
	}
}
