<?php

namespace OCA\PhoneTrack\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<PublicShare>
 */
class PublicShareMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'phonetrack_pubshares', PublicShare::class);
	}

	public function findById(int $id): PublicShare {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	public function findByIdAndSessionToken(int $id, string $sessionToken): PublicShare {
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

	public function findBySessionId(int $sessionId) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntities($qb);
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
}
