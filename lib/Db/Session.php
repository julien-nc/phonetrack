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

use OCP\AppFramework\Db\Entity;

class Session extends Entity {

    protected $userid;
    protected $name;
    protected $email;
    protected $password;
    protected $autoexport;
    protected $lastchanged;

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('user', 'string');
        $this->addType('name', 'string');
        $this->addType('token', 'string');
        $this->addType('publicviewtoken', 'string');
        $this->addType('public', 'integer');
        $this->addType('locked', 'integer');
        $this->addType('creationversion', 'string');
        $this->addType('autoexport', 'string');
        $this->addType('autopurge', 'string');
    }
}
