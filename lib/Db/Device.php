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
use OCP\DB\Types;

/**
 * @method \int getId()
 * @method \void setId(int $id)
 * @method \string getName()
 * @method \void setName(string $name)
 * @method \string|\null getAlias()
 * @method \void setAlias(?string $alias)
 * @method \string getSessionid()
 * @method \void setSessionid(string $sessionid)
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
	protected $sessionid;
	protected $color;
	protected $shape;
	protected $nametoken;
	protected $enabled;
	protected $colorCriteria;
	protected $lineEnabled;
	protected $autoZoom;

	public function __construct() {
		$this->addType('id', Types::INTEGER);
		$this->addType('name', Types::STRING);
		$this->addType('alias', Types::STRING);
		$this->addType('sessionid', Types::STRING);
		$this->addType('color', Types::STRING);
		$this->addType('shape', Types::STRING);
		$this->addType('nametoken', Types::STRING);
		$this->addType('enabled', Types::INTEGER);
		$this->addType('color_criteria', Types::INTEGER);
		$this->addType('line_enabled', Types::INTEGER);
		$this->addType('auto_zoom', Types::INTEGER);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'alias' => $this->getAlias(),
			'sessionid' => $this->getSessionid(),
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
