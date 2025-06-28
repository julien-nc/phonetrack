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

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUser()
 * @method void setUser(string $user)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getToken()
 * @method void setToken(string $token)
 * @method string getPublicviewtoken()
 * @method void setPublicviewtoken(string $publicviewtoken)
 * @method int getPublic()
 * @method void setPublic(int $public)
 * @method int getLocked()
 * @method void setLocked(int $locked)
 * @method string getCreationversion()
 * @method void setCreationversion(string $creationversion)
 * @method string getAutoexport()
 * @method void setAutoexport(string $autoexport)
 * @method string getAutopurge()
 * @method void setAutopurge(string $autopurge)
 */
class Session extends Entity implements \JsonSerializable {

	protected $user;
	protected $name;
	protected $token;
	protected $publicviewtoken;
	protected $public;
	protected $locked;
	protected $creationversion;
	protected $autoexport;
	protected $autopurge;

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

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'user' => $this->getUser(),
			'name' => $this->getName(),
			'token' => $this->getToken(),
			'publicviewtoken' => $this->getPublicviewtoken(),
			'public' => $this->getPublic(),
			'locked' => $this->getLocked(),
			'creationversion' => $this->getCreationversion(),
			'autoexport' => $this->getAutoexport(),
			'autopurge' => $this->getAutopurge(),
		];
	}
}
