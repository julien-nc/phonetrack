<?php

/**
 * Nextcloud - phonetrack
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2019
 */

namespace OCA\PhoneTrack\Service;

use OCP\IL10N;
use OCP\ILogger;
use OCP\DB\QueryBuilder\IQueryBuilder;

use OCA\PhoneTrack\Db\SessionMapper;
use OCP\IGroupManager;

class SessionService {

    private $l10n;
    private $logger;
    private $qb;
    private $dbconnection;

    public function __construct (ILogger $logger, IL10N $l10n, SessionMapper $sessionMapper, IGroupManager $groupManager) {
        $this->l10n = $l10n;
        $this->logger = $logger;
        $this->qb = \OC::$server->getDatabaseConnection()->getQueryBuilder();
        $this->dbconnection = \OC::$server->getDatabaseConnection();
        $this->sessionMapper = $sessionMapper;
        $this->groupManager = $groupManager;
    }

    private function db_quote_escape_string($str){
        return $this->dbconnection->quote($str);
    }

    public function findUsers($id) {
        $userIds = [];
        // get owner with mapper
        $session = $this->sessionMapper->find($id);
        array_push($userIds, $proj->getUser());

        // get user shares from session token
        $token = $session->getToken();
        $qb = $this->dbconnection->getQueryBuilder();
        $qb->select('username')
            ->from('phonetrack_shares', 's')
            ->where(
                $qb->expr()->eq('sessionid', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR))
            );
        $req = $qb->execute();
        while ($row = $req->fetch()) {
            if (!in_array($row['username'], $userIds)) {
                array_push($userIds, $row['username']);
            }
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        return $userIds;
    }

}
