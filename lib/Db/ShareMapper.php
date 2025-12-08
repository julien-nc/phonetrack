<?php

namespace OCA\PhoneTrack\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Share>
 */
class ShareMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'phonetrack_shares', Share::class);
	}

	public function findById(int $id): Share {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	public function findByIdAndSessionToken(int $id, string $sessionToken): Share {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('session_token', $qb->createNamedParameter($sessionToken, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}

	public function findBySessionTokenAndUser(string $userId, string $sessionToken): Share {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('username', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('session_token', $qb->createNamedParameter($sessionToken, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}

	public function findBySessionIdAndUser(string $userId, int $sessionId): Share {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('username', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	public function findByShareToken(string $shareToken): Share {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('sharetoken', $qb->createNamedParameter($shareToken, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}

	public function findBySessionToken(string $sessionToken) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('session_token', $qb->createNamedParameter($sessionToken, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntities($qb);
	}

	/**
	 * returns user ids the session is shared with
	 */
	public function getSessionSharedUserIdList(string $sessionToken): array {
		$userIds = [];
		$qb = $this->db->getQueryBuilder();
		$qb->select('username')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('session_token', $qb->createNamedParameter($sessionToken, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			$userIds[] = $row['username'];
		}
		return $userIds;
	}
}
