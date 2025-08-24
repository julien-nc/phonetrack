<?php

/**
 * Nextcloud - phonetrack
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net
 * @copyright Julien Veyssier 2025
 */

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
 * @method \string getLabel()
 * @method \void setLabel(string $label)
 * @method \string getFilters()
 * @method \void setFilters(string $filters)
 * @method \string|\null getDevicename()
 * @method \void setDevicename(?string $devicename)
 * @method \int getLastposonly()
 * @method \void setLastposonly(int $lastposonly)
 * @method \int getGeofencify()
 * @method \void setGeofencify(int $geofencify)
 */
class PublicShare extends Entity implements \JsonSerializable {

	protected $sessionid;
	protected $sharetoken;
	protected $label;
	protected $filters;
	protected $devicename;
	protected $lastposonly;
	protected $geofencify;

	public function __construct() {
		$this->addType('id', Types::INTEGER);
		$this->addType('sessionid', Types::STRING);
		$this->addType('sharetoken', Types::STRING);
		$this->addType('label', Types::STRING);
		$this->addType('filters', Types::STRING);
		$this->addType('devicename', Types::STRING);
		$this->addType('lastposonly', Types::INTEGER);
		$this->addType('geofencify', Types::INTEGER);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'sessionid' => $this->getSessionid(),
			'sharetoken' => $this->getSharetoken(),
			'label' => $this->getLabel(),
			'filters' => $this->getFilters(),
			'devicename' => $this->getDevicename(),
			'lastposonly' => $this->getLastposonly() !== 0,
			'geofencify' => $this->getGeofencify() !== 0,
		];
	}
}
