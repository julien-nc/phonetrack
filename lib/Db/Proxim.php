<?php

namespace OCA\PhoneTrack\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method \int getId()
 * @method \void setId(int $id)
 * @method \int getDeviceid1()
 * @method \void setDeviceid1(int $deviceid1)
 * @method \int getDeviceid2()
 * @method \void setDeviceid2(int $deviceid2)
 * @method \int getLowlimit()
 * @method \void setLowlimit(int $lowlimit)
 * @method \int getHighlimit()
 * @method \void setHighlimit(int $highlimit)
 * @method \string|\null getUrlclose()
 * @method \void setUrlclose(?string $urlclose)
 * @method \string|\null getUrlfar()
 * @method \void setUrlfar(?string $urlfar)
 * @method \int getUrlclosepost()
 * @method \void setUrlclosepost(int $urlclosepost)
 * @method \int getUrlfarpost()
 * @method \void setUrlfarpost(int $urlfarpost)
 * @method \int getSendemail()
 * @method \void setSendemail(int $sendemail)
 * @method \string|\null getEmailaddr()
 * @method \void setEmailaddr(?string $emailaddr)
 * @method \int getSendnotif()
 * @method \void setSendnotif(int $sendnotif)
 */
class Proxim extends Entity implements \JsonSerializable {

	protected $deviceid1;
	protected $deviceid2;
	protected $lowlimit;
	protected $highlimit;
	protected $urlclose;
	protected $urlfar;
	protected $urlclosepost;
	protected $urlfarpost;
	protected $sendemail;
	protected $emailaddr;
	protected $sendnotif;

	public function __construct() {
		$this->addType('deviceid1', Types::INTEGER);
		$this->addType('deviceid2', Types::INTEGER);
		$this->addType('lowlimit', Types::INTEGER);
		$this->addType('highlimit', Types::INTEGER);
		$this->addType('urlclose', Types::STRING);
		$this->addType('urlfar', Types::STRING);
		$this->addType('urlclosepost', Types::INTEGER);
		$this->addType('urlfarpost', Types::INTEGER);
		$this->addType('sendemail', Types::INTEGER);
		$this->addType('emailaddr', Types::STRING);
		$this->addType('sendnotif', Types::INTEGER);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'deviceid1' => $this->getDeviceid1(),
			'deviceid2' => $this->getDeviceid2(),
			'lowlimit' => $this->getLowlimit(),
			'highlimit' => $this->getHighlimit(),
			'urlclose' => $this->getUrlclose() ?? '',
			'urlfar' => $this->getUrlfar() ?? '',
			'urlclosepost' => $this->getUrlclosepost() !== 0,
			'urlfarpost' => $this->getUrlfarpost() !== 0,
			'sendemail' => $this->getSendemail() !== 0,
			'emailaddr' => $this->getEmailaddr() ?? '',
			'sendnotif' => $this->getSendnotif() !== 0,
		];
	}
}
