<?php

namespace OCA\PhoneTrack\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method \int getId()
 * @method \void setId(int $id)
 * @method \string getSessionid()
 * @method \void setSessionid(string $sessionid)
 * @method \string getSharetoken()
 * @method \void setSharetoken(string $sharetoken)
 * @method \string getUsername()
 * @method \void setUsername(string $username)
 */
class Share extends Entity implements \JsonSerializable {

	protected $sessionid;
	protected $sharetoken;
	protected $username;

	public function __construct() {
		$this->addType('id', Types::INTEGER);
		$this->addType('sessionid', Types::STRING);
		$this->addType('sharetoken', Types::STRING);
		$this->addType('username', Types::STRING);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'sessionid' => $this->getSessionid(),
			'sharetoken' => $this->getSharetoken(),
			'username' => $this->getUsername(),
		];
	}
}
