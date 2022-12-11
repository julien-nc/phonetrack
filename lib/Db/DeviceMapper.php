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

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

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
}
