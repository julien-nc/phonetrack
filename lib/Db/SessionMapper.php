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

 namespace OCA\Phonetrack\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\Mapper;

class SessionMapper extends Mapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'phonetrack_sessions');
    }

    public function find($id) {
        $sql = 'SELECT * FROM `*PREFIX*phonetrack_sessions` ' .
            'WHERE `id` = ?';
        return $this->findEntity($sql, [$id]);
    }

}
