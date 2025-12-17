<?php

declare(strict_types=1);

namespace OCA\PhoneTrack\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method \string|\null getUserId()
 * @method \void setUserId(?string $userId)
 * @method \int getType()
 * @method \void setType(int $type)
 * @method \string getName()
 * @method \void setName(string $name)
 * @method \string getUrl()
 * @method \void setUrl(string $url)
 * @method \int|\null getMinZoom()
 * @method \void setMinZoom(?int $minZoom)
 * @method \int|\null getMaxZoom()
 * @method \void setMaxZoom(?int $maxZoom)
 * @method \string|\null getAttribution()
 * @method \void setAttribution(?string $attribution)
 */
class TileServer extends Entity implements \JsonSerializable {

	protected $userId;
	protected $type;
	protected $name;
	protected $url;
	protected $minZoom;
	protected $maxZoom;
	protected $attribution;

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('type', Types::INTEGER);
		$this->addType('name', Types::STRING);
		$this->addType('url', Types::STRING);
		$this->addType('minZoom', Types::INTEGER);
		$this->addType('maxZoom', Types::INTEGER);
		$this->addType('attribution', Types::STRING);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'user_id' => $this->getUserId(),
			'type' => $this->getType(),
			'name' => $this->getName(),
			'url' => $this->getUrl(),
			'min_zoom' => $this->getMinZoom(),
			'max_zoom' => $this->getMaxZoom(),
			'attribution' => $this->getAttribution(),
		];
	}
}
