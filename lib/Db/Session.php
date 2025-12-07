<?php

namespace OCA\PhoneTrack\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method \int getId()
 * @method \void setId(int $id)
 * @method \string getUser()
 * @method \void setUser(string $user)
 * @method \string getName()
 * @method \void setName(string $name)
 * @method \string getToken()
 * @method \void setToken(string $token)
 * @method \string getPublicviewtoken()
 * @method \void setPublicviewtoken(string $publicviewtoken)
 * @method \int getPublic()
 * @method \void setPublic(int $public)
 * @method \int getLocked()
 * @method \void setLocked(int $locked)
 * @method \string getCreationversion()
 * @method \void setCreationversion(string $creationversion)
 * @method \string getAutoexport()
 * @method \void setAutoexport(string $autoexport)
 * @method \string getAutopurge()
 * @method \void setAutopurge(string $autopurge)
 * @method \int getEnabled()
 * @method \void setEnabled(int $enabled)
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
	protected $enabled;

	public function __construct() {
		$this->addType('id', Types::INTEGER);
		$this->addType('user', Types::STRING);
		$this->addType('name', Types::STRING);
		$this->addType('token', Types::STRING);
		$this->addType('publicviewtoken', Types::STRING);
		$this->addType('public', Types::INTEGER);
		$this->addType('locked', Types::INTEGER);
		$this->addType('creationversion', Types::STRING);
		$this->addType('autoexport', Types::STRING);
		$this->addType('autopurge', Types::STRING);
		$this->addType('enabled', Types::INTEGER);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'user' => $this->getUser(),
			'name' => $this->getName(),
			'token' => $this->getToken(),
			'publicviewtoken' => $this->getPublicviewtoken(),
			'public' => $this->getPublic() !== 0,
			'locked' => $this->getLocked() !== 0,
			'creationversion' => $this->getCreationversion(),
			'autoexport' => $this->getAutoexport(),
			'autopurge' => $this->getAutopurge(),
			'enabled' => $this->getEnabled() !== 0,
		];
	}
}
