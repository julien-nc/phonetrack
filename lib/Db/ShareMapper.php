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
				$qb->expr()->eq('sessionid', $qb->createNamedParameter($sessionToken, IQueryBuilder::PARAM_STR))
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
				$qb->expr()->eq('sessionid', $qb->createNamedParameter($sessionToken, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}

	public function findBySessionToken(string $sessionId) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('sessionid', $qb->createNamedParameter($sessionId, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntities($qb);
	}
}
