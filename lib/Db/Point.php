<?php

namespace OCA\PhoneTrack\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method \int getId()
 * @method \void setId(int $id)
 * @method \int getDeviceid()
 * @method \void setDeviceid(int $deviceid)
 * @method \float getLat()
 * @method \void setLat(float $lat)
 * @method \float getLon()
 * @method \void setLon(float $lon)
 * @method \int getTimestamp()
 * @method \void setTimestamp(int $timestamp)
 * @method \float|\null getAccuracy()
 * @method \void setAccuracy(?float $accuracy)
 * @method \int|\null getSatellites()
 * @method \void setSatellites(?int $satellites)
 * @method \float|\null getAltitude()
 * @method \void setAltitude(?float $altitude)
 * @method \float|\null getBatterylevel()
 * @method \void setBatterylevel(?float $batterylevel)
 * @method \string getUseragent()
 * @method \void setUseragent(string $useragent)
 * @method \float|\null getSpeed()
 * @method \void setSpeed(?float $speed)
 * @method \float|\null getBearing()
 * @method \void setBearing(?float $bearing)
 */
class Point extends Entity implements \JsonSerializable {

	protected $deviceid;
	protected $lat;
	protected $lon;
	protected $timestamp;
	protected $accuracy;
	protected $satellites;
	protected $altitude;
	protected $batterylevel;
	protected $useragent;
	protected $speed;
	protected $bearing;

	public function __construct() {
		$this->addType('deviceid', Types::INTEGER);
		$this->addType('lat', Types::FLOAT);
		$this->addType('lon', Types::FLOAT);
		$this->addType('timestamp', Types::INTEGER);
		$this->addType('accuracy', Types::FLOAT);
		$this->addType('satellites', Types::INTEGER);
		$this->addType('altitude', Types::FLOAT);
		$this->addType('batterylevel', Types::FLOAT);
		$this->addType('useragent', Types::STRING);
		$this->addType('speed', Types::FLOAT);
		$this->addType('bearing', Types::FLOAT);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'deviceid' => $this->getDeviceid(),
			'lat' => $this->getLat(),
			'lon' => $this->getLon(),
			'timestamp' => $this->getTimestamp(),
			'accuracy' => $this->getAccuracy(),
			'satellites' => $this->getSatellites(),
			'altitude' => $this->getAltitude(),
			'batterylevel' => $this->getBatterylevel(),
			'useragent' => $this->getUseragent(),
			'speed' => $this->getSpeed(),
			'bearing' => $this->getBearing(),
		];
	}
}
