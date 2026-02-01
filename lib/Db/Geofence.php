<?php

namespace OCA\PhoneTrack\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method \int getId()
 * @method \void setId(int $id)
 * @method \string getName()
 * @method \void setName(string $name)
 * @method \int getDeviceid()
 * @method \void setDeviceid(int $deviceid)
 * @method \float getLatmin()
 * @method \void setLatmin(float $latmin)
 * @method \float getLatmax()
 * @method \void setLatmax(float $latmax)
 * @method \float getLonmin()
 * @method \void setLonmin(float $lonmin)
 * @method \float getLonmax()
 * @method \void setLonmax(float $lonmax)
 * @method \string|\null getUrlenter()
 * @method \void setUrlenter(?string $urlenter)
 * @method \string|\null getUrlleave()
 * @method \void setUrlleave(?string $urlleave)
 * @method \int getUrlenterpost()
 * @method \void setUrlenterpost(int $urlenterpost)
 * @method \int getUrlleavepost()
 * @method \void setUrlleavepost(int $urlleavepost)
 * @method \int getSendemail()
 * @method \void setSendemail(int $sendemail)
 * @method \string|\null getEmailaddr()
 * @method \void setEmailaddr(?string $emailaddr)
 * @method \int getSendnotif()
 * @method \void setSendnotif(int $sendnotif)
 */
class Geofence extends Entity implements \JsonSerializable {

	protected $name;
	protected $deviceid;
	protected $latmin;
	protected $latmax;
	protected $lonmin;
	protected $lonmax;
	protected $urlenter;
	protected $urlleave;
	protected $urlenterpost;
	protected $urlleavepost;
	protected $sendemail;
	protected $emailaddr;
	protected $sendnotif;

	public function __construct() {
		$this->addType('name', Types::STRING);
		$this->addType('deviceid', Types::INTEGER);
		$this->addType('latmin', Types::FLOAT);
		$this->addType('latmax', Types::FLOAT);
		$this->addType('lonmin', Types::FLOAT);
		$this->addType('lonmax', Types::FLOAT);
		$this->addType('urlenter', Types::STRING);
		$this->addType('urlleave', Types::STRING);
		$this->addType('urlenterpost', Types::INTEGER);
		$this->addType('urlleavepost', Types::INTEGER);
		$this->addType('sendemail', Types::INTEGER);
		$this->addType('emailaddr', Types::STRING);
		$this->addType('sendnotif', Types::INTEGER);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'deviceid' => $this->getDeviceid(),
			'latmin' => $this->getLatmin(),
			'latmax' => $this->getLatmax(),
			'lonmin' => $this->getLonmin(),
			'lonmax' => $this->getLonmax(),
			'urlenter' => $this->getUrlenter() ?? '',
			'urlleave' => $this->getUrlleave() ?? '',
			'urlenterpost' => $this->getUrlenterpost() !== 0,
			'urlleavepost' => $this->getUrlleavepost() !== 0,
			'sendemail' => $this->getSendemail() !== 0,
			'emailaddr' => $this->getEmailaddr() ?? '',
			'sendnotif' => $this->getSendnotif() !== 0,
		];
	}
}
