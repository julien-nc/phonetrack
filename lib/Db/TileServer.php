<?php

declare(strict_types=1);

namespace OCA\PhoneTrack\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string|null getUserId()
 * @method void setUserId(?string $userId)
 * @method int getType()
 * @method void setType(int $type)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getUrl()
 * @method void setUrl(string $url)
 * @method int|null getMinZoom()
 * @method void setMinZoom(?int $minZoom)
 * @method int|null getMaxZoom()
 * @method void setMaxZoom(?int $maxZoom)
 * @method string|null getAttribution()
 * @method void setAttribution(?string $attribution)
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
		$this->addType('user_id', 'string');
		$this->addType('type', 'integer');
		$this->addType('name', 'string');
		$this->addType('url', 'string');
		$this->addType('min_zoom', 'integer');
		$this->addType('max_zoom', 'integer');
		$this->addType('attribution', 'string');
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
