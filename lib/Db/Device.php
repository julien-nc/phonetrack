<?php

namespace OCA\PhoneTrack\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method \int getId()
 * @method \void setId(int $id)
 * @method \string getName()
 * @method \void setName(string $name)
 * @method \string|\null getAlias()
 * @method \void setAlias(?string $alias)
 * @method \int getSessionId()
 * @method \void setSessionId(int $sessionId)
 * @method \string getSessionToken()
 * @method \void setSessionToken(string $sessionToken)
 * @method \string|\null getColor()
 * @method \void setColor(?string $color)
 * @method \string|\null getShape()
 * @method \void setShape(?string $shape)
 * @method \string|\null getNametoken()
 * @method \void setNametoken(?string $nametoken)
 * @method \int getEnabled()
 * @method \void setEnabled(int $enabled)
 * @method \int getColorCriteria()
 * @method \void setColorCriteria(int $colorCriteria)
 * @method \int getLineEnabled()
 * @method \void setLineEnabled(int $lineEnabled)
 * @method \int getAutoZoom()
 * @method \void setAutoZoom(int $autoZoom)
 */
class Device extends Entity implements \JsonSerializable {

	protected $name;
	protected $alias;
	protected $sessionId;
	protected $sessionToken;
	protected $color;
	protected $shape;
	protected $nametoken;
	protected $enabled;
	protected $colorCriteria;
	protected $lineEnabled;
	protected $autoZoom;

	public function __construct() {
		$this->addType('name', Types::STRING);
		$this->addType('alias', Types::STRING);
		$this->addType('sessionId', Types::INTEGER);
		$this->addType('sessionToken', Types::STRING);
		$this->addType('color', Types::STRING);
		$this->addType('shape', Types::STRING);
		$this->addType('nametoken', Types::STRING);
		$this->addType('enabled', Types::INTEGER);
		$this->addType('colorCriteria', Types::INTEGER);
		$this->addType('lineEnabled', Types::INTEGER);
		$this->addType('autoZoom', Types::INTEGER);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'alias' => $this->getAlias(),
			'session_id' => $this->getSessionId(),
			'session_token' => $this->getSessionToken(),
			'color' => $this->getColor(),
			'shape' => $this->getShape(),
			'nametoken' => $this->getNametoken(),
			'enabled' => $this->getEnabled() !== 0,
			'colorCriteria' => $this->getColorCriteria(),
			'lineEnabled' => $this->getLineEnabled() !== 0,
			'autoZoom' => $this->getAutoZoom() !== 0,
		];
	}
}
