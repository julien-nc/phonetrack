<?php

namespace OCA\PhoneTrack\Service;

use DateTime;
use OCA\PhoneTrack\Db\Device;
use OCA\PhoneTrack\Db\DeviceMapper;
use OCA\PhoneTrack\Db\Point;
use OCA\PhoneTrack\Db\PointMapper;
use OCP\Files\File;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;
use XMLParser;

class ImportGpxService {

	private int $sessionId;
	private string $sessionToken;
	private int $trackIndex;
	private string $currentXmlTag;
	private int $pointIndex;
	private array $currentPointList;
	private array $currentPoint;
	private Device $currentDevice;
	private array $tagStack = [];

	public function __construct(
		private LoggerInterface $logger,
		private IDBConnection $db,
		private PointMapper $pointMapper,
		private DeviceMapper $deviceMapper,
	) {
	}

	public function importGpx(File $gpxFile, int $sessionId, string $sessionToken): void {
		$this->sessionId = $sessionId;
		$this->sessionToken = $sessionToken;
		$this->trackIndex = 1;
		$xmlParser = xml_parser_create();
		xml_set_element_handler($xmlParser, [$this, 'gpxStartElement'], [$this, 'gpxEndElement']);
		xml_set_character_data_handler($xmlParser, [$this, 'gpxDataElement']);

		$fileDescriptor = $gpxFile->fopen('r');

		while ($data = fread($fileDescriptor, 4096000)) {
			//$this->logger->info('MEM USAGE '.memory_get_usage(), ['app' => $this->appName]);
			if (!xml_parse($xmlParser, $data, feof($fileDescriptor))) {
				$this->logger->error(
					'Exception in ' . $gpxFile->getName() . ' parsing at line '
					. xml_get_current_line_number($xmlParser) . ' : '
					. xml_error_string(xml_get_error_code($xmlParser)),
				);
				throw new \Exception(
					'Error parsing ' . $gpxFile->getName() . ' at line '
					. xml_get_current_line_number($xmlParser) . ' : '
					. xml_error_string(xml_get_error_code($xmlParser))
				);
			}
		}
		fclose($fileDescriptor);
		xml_parser_free($xmlParser);
		unset($xmlParser);
		if ($this->trackIndex === 1) {
			throw new \Exception('no_device_to_import');
		}
	}

	public function gpxStartElement(XMLParser $parser, string $name, array $attrs): void {
		$this->currentXmlTag = $name;
		$this->tagStack[] = $name;
		if ($name === 'TRK') {
			$device = new Device();
			$device->setSessionId($this->sessionId);
			$device->setSessionToken($this->sessionToken);
			$device->setName('device ' . $this->trackIndex);
			$this->currentDevice = $this->deviceMapper->insert($device);
			$this->pointIndex = 1;
			$this->currentPointList = [];
		} elseif ($name === 'TRKPT') {
			$this->currentPoint = [
				$this->currentDevice->getId(),
				$this->pointIndex,
				null,
				null,
				null,
				null,
				null,
				null,
				null,
				null,
				null,
			];
			if (array_key_exists('LAT', $attrs)) {
				$this->currentPoint[2] = (float)$attrs['LAT'];
			}
			if (array_key_exists('LON', $attrs)) {
				$this->currentPoint[3] = (float)$attrs['LON'];
			}
		}
	}

	public function gpxEndElement(XMLParser $parser, string $name) {
		array_pop($this->tagStack);
		if ($name === 'TRK') {
			// log last track points
			if (count($this->currentPointList) > 0) {
				$this->pointMapper->storePoints($this->currentPointList);
			}
			$this->trackIndex++;
			unset($this->currentPointList);
		} elseif ($name === 'TRKPT') {
			// store track point
			$this->currentPointList[] = $this->currentPoint;
			// if we have enough points, we store them and clean the points array
			if (count($this->currentPointList) >= 1000) {
				$this->pointMapper->storePoints($this->currentPointList);
				unset($this->currentPointList);
				$this->currentPointList = [];
			}
			$this->pointIndex++;
		}
	}

	public function gpxDataElement(XMLParser $parser, string $data): void {
		$textContent = trim($data);
		if (!empty($textContent)) {
			if ($this->currentXmlTag === 'ELE') {
				$this->currentPoint[4] = (float)$textContent;
			} elseif ($this->currentXmlTag === 'SPEED') {
				$this->currentPoint[5] = (float)$textContent;
			} elseif ($this->currentXmlTag === 'SAT') {
				$this->currentPoint[6] = (int)$textContent;
			} elseif ($this->currentXmlTag === 'COURSE') {
				$this->currentPoint[7] = (float)$textContent;
			} elseif ($this->currentXmlTag === 'USERAGENT') {
				$this->currentPoint[8] = $textContent;
			} elseif ($this->currentXmlTag === 'BATTERYLEVEL') {
				$this->currentPoint[9] = (float)$textContent;
			} elseif ($this->currentXmlTag === 'ACCURACY') {
				$this->currentPoint[10] = (float)$textContent;
			} elseif ($this->currentXmlTag === 'TIME' && $this->tagStack[count($this->tagStack) - 2] === 'TRKPT') {
				$time = new DateTime($textContent);
				$this->currentPoint[1] = $time->getTimestamp();
			} elseif ($this->currentXmlTag === 'NAME' && $this->tagStack[count($this->tagStack) - 2] === 'TRK') {
				$this->currentDevice->setName($textContent);
				$this->deviceMapper->update($this->currentDevice);
			}
		}
	}
}
