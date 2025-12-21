<?php

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
	 * @param int $id
	 * @return Session
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id): Session {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @return list<string>
	 * @throws Exception
	 */
	public function getUserIds(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('user')
			->from($this->getTableName())
			->groupBy('user');

		$res = $qb->executeQuery();
		$all = $res->fetchAll();
		return array_column($all, 'user');
	}

	/**
	 * @param string $token
	 * @param string|null $userId
	 * @return Session
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByToken(string $token, ?string $userId = null): Session {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR))
			);
		if ($userId !== null) {
			$qb->andWhere(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		}

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
	 * @param string $userId
	 * @param string $name
	 * @return Session
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getUserSessionByName(string $userId, string $name): Session {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param string $userId
	 * @param int $id
	 * @return Session
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getUserSessionById(string $userId, int $id): Session {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param string $userId
	 * @param string $name
	 * @param string $token
	 * @param string $publicViewToken
	 * @param bool $isPublic
	 * @return Session
	 * @throws Exception
	 */
	public function createSession(string $userId, string $name, string $token, string $publicViewToken, bool $isPublic): Session {
		$session = new Session();
		$session->setUser($userId);
		$session->setName($name);
		$session->setToken($token);
		$session->setPublicviewtoken($publicViewToken);
		$session->setPublic($isPublic ? 1 : 0);
		$session->setEnabled(0);
		$session->setLocked(0);

		return $this->insert($session);
	}

	/**
	 * @param string $userId
	 * @param int $id
	 * @return void
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function deleteSession(string $userId, int $id): void {
		$session = $this->getUserSessionById($userId, $id);

		$qb = $this->db->getQueryBuilder();
		$qb->delete('phonetrack_devices')
			->where(
				$qb->expr()->eq('session_id', $qb->createNamedParameter($session->getToken(), IQueryBuilder::PARAM_STR))
			);
		$qb->executeStatement();

		$qb = $this->db->getQueryBuilder();
		$qb->delete('phonetrack_shares')
			->where(
				$qb->expr()->eq('session_id', $qb->createNamedParameter($session->getId(), IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();

		$qb = $this->db->getQueryBuilder();
		$qb->delete('phonetrack_pubshares')
			->where(
				$qb->expr()->eq('session_id', $qb->createNamedParameter($session->getId(), IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();

		$this->delete($session);
	}

	/**
	 * @param array $sessionIds
	 * @return array
	 * @throws Exception
	 */
	public function getSessionsById(array $sessionIds): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->in('id', $qb->createNamedParameter($sessionIds, IQueryBuilder::PARAM_INT_ARRAY))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @param array $tokens
	 * @return array
	 * @throws Exception
	 */
	public function getSessionsByToken(array $tokens): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->in('token', $qb->createNamedParameter($tokens, IQueryBuilder::PARAM_STR_ARRAY))
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
	 * @param string $shareToken
	 * @param string $userId
	 * @return array|null
	 * @throws Exception
	 */
	public function isSharedWith(string $shareToken, string $userId): ?array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('session_token', 'session_id')
			->from('phonetrack_shares')
			->where(
				$qb->expr()->eq('sharetoken', $qb->createNamedParameter($shareToken, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('username', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			return [
				'session_token' => $row['session_token'],
				'session_id' => $row['session_id'],
			];
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
				$qb->expr()->eq('session_token', $qb->createNamedParameter($token))
			);

		if ($filters !== null) {
			$qb = DeviceMapper::applyQueryFilters($qb, $filters);
		}

		$req = $qb->executeQuery();
		return (int)$req->fetchOne();
	}
}
