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
 * @method int getId()
 * @method void setId(int $id)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getAlias()
 * @method void setAlias(string $alias)
 * @method string getSessionid()
 * @method void setSessionid(string $sessionid)
 * @method string getColor()
 * @method void setColor(string $color)
 * @method string getShape()
 * @method void setShape(string $shape)
 * @method string getNametoken()
 * @method void setNametoken(string $nametoken)
 * @method int getEnabled()
 * @method void setEnabled(int $enabled)
 * @method int getColorCriteria()
 * @method void setColorCriteria(int $colorCriteria)
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
		];
	}
}
