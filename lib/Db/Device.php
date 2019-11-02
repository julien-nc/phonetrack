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

use OCP\AppFramework\Db\Entity;

class Device extends Entity {

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('name', 'string');
        $this->addType('alias', 'string');
        $this->addType('sessionid', 'string');
        $this->addType('color', 'string');
        $this->addType('shape', 'string');
        $this->addType('nametoken', 'string');
    }
}
