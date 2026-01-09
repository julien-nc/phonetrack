<?php

namespace OCA\PhoneTrack\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method \int getId()
 * @method \void setId(int $id)
 * @method \string getSharetoken()
 * @method \void setSharetoken(string $sharetoken)
 * @method \string getUsername()
 * @method \void setUsername(string $username)
 * @method \int getSessionId()
 * @method \void setSessionId(int $sessionId)
 * @method \string getSessionToken()
 * @method \void setSessionToken(string $sessionToken)
 */
class Share extends Entity implements \JsonSerializable {

	protected $sharetoken;
	protected $username;
	protected $sessionId;
	protected $sessionToken;

	public function __construct() {
		$this->addType('sharetoken', Types::STRING);
		$this->addType('username', Types::STRING);
		$this->addType('sessionId', Types::INTEGER);
		$this->addType('sessionToken', Types::STRING);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'sharetoken' => $this->getSharetoken(),
			'username' => $this->getUsername(),
			'session_id' => $this->getSessionId(),
			'session_token' => $this->getSessionToken(),
		];
	}
}
