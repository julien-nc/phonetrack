<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PhoneTrack\Controller;

use OCA\PhoneTrack\Activity\ActivityManager;
use OCA\PhoneTrack\Db\Device;
use OCA\PhoneTrack\Db\DeviceMapper;
use OCA\PhoneTrack\Db\Geofence;
use OCA\PhoneTrack\Db\GeofenceMapper;
use OCA\PhoneTrack\Db\Point;
use OCA\PhoneTrack\Db\PointMapper;
use OCA\PhoneTrack\Db\Proxim;
use OCA\PhoneTrack\Db\ProximMapper;
use OCA\PhoneTrack\Db\SessionMapper;
use OCA\PhoneTrack\Db\ShareMapper;
use OCA\PhoneTrack\Service\ToolsService;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\Notification\IManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class LogControllerTest extends TestCase {

	private LogController $logController;

	/** @var PointMapper&MockObject */
	private $pointMapper;

	/** @var DeviceMapper&MockObject */
	private $deviceMapper;

	/** @var LoggerInterface&MockObject */
	private $logger;

	/** @var IUserManager&MockObject */
	private $userManager;

	protected function setUp(): void {
		/** @var PointMapper&MockObject $pointMapper */
		$this->pointMapper = $this->createMock(PointMapper::class);

		/** @var DeviceMapper&MockObject $deviceMapper */
		$this->deviceMapper = $this->createMock(DeviceMapper::class);

		/** @var LoggerInterface&MockObject $logger */
		$this->logger = $this->createMock(LoggerInterface::class);

		/** @var IUserManager&MockObject $userManager */
		$this->userManager = $this->createMock(IUserManager::class);
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn('');
		$this->userManager->method('get')->willReturn($user);

		$this->logController = new LogController(
			'phonetrack',
			$this->createMock(IRequest::class),
			$this->createMock(IConfig::class),
			$this->createMock(IAppConfig::class),
			$this->createMock(IManager::class),
			$this->userManager,
			$this->createMock(IL10N::class),
			$this->logger,
			$this->createMock(ActivityManager::class),
			$this->createMock(SessionMapper::class),
			$this->deviceMapper,
			$this->pointMapper,
			$this->createMock(ProximMapper::class),
			$this->createMock(GeofenceMapper::class),
			$this->createMock(ShareMapper::class),
			$this->createMock(IDBConnection::class),
			$this->createMock(IMailer::class),
			$this->createMock(IURLGenerator::class),
			'testuser',
		);

		$device = new Device();
		$device->setId(1);
		$device->setName('mydevice');
		$this->deviceMapper->method('find')->willReturn($device);
	}

	private function invokePrivate(string $method, array $args): mixed {
		$reflection = new \ReflectionMethod(LogController::class, $method);
		return $reflection->invokeArgs($this->logController, $args);
	}

	private function makePoint(float $lat, float $lon): Point {
		$point = new Point();
		$point->setLat($lat);
		$point->setLon($lon);
		return $point;
	}

	private function makeFence(float $latmin, float $latmax, float $lonmin, float $lonmax): Geofence {
		$fence = new Geofence();
		$fence->setId(1);
		$fence->setName('fence');
		$fence->setDeviceid(1);
		$fence->setLatmin($latmin);
		$fence->setLatmax($latmax);
		$fence->setLonmin($lonmin);
		$fence->setLonmax($lonmax);
		$fence->setSendemail(0);
		$fence->setSendnotif(0);
		$fence->setEmailaddr(null);
		return $fence;
	}

	private function makeProxim(int $movingDeviceId): Proxim {
		$proxim = new Proxim();
		$proxim->setId(1);
		$proxim->setDeviceid1($movingDeviceId);
		$proxim->setDeviceid2($movingDeviceId + 1);
		$proxim->setSendemail(0);
		$proxim->setSendnotif(0);
		$proxim->setEmailaddr(null);
		return $proxim;
	}

	public function testGeofenceEnterWithNullUrlenter(): void {
		$fence = $this->makeFence(10.0, 11.0, 10.0, 11.0);
		$fence->setUrlenter(null);
		$fence->setUrlenterpost(0);
		$fence->setUrlleave(null);

		// last point outside, new point inside -> ENTER
		$this->invokePrivate('checkGeoGence', [
			10.5, 10.5, $this->makePoint(5.0, 5.0), 1, $fence,
			'testuser', 'device', 'session', 'token',
		]);

		$this->addToAssertionCount(1);
	}

	public function testGeofenceLeaveWithNullUrlleave(): void {
		$fence = $this->makeFence(10.0, 11.0, 10.0, 11.0);
		$fence->setUrlenter(null);
		$fence->setUrlleave(null);
		$fence->setUrlleavepost(0);

		// last point inside, new point outside -> LEAVE
		$this->invokePrivate('checkGeoGence', [
			5.0, 5.0, $this->makePoint(10.5, 10.5), 1, $fence,
			'testuser', 'device', 'session', 'token',
		]);

		$this->addToAssertionCount(1);
	}

	public function testGeofenceLeaveWithPostUrlWithoutPathAndQuery(): void {
		// URL parses without 'path' and without 'query' keys and uses an
		// unsupported scheme so file_get_contents fails fast (no network)
		$fence = $this->makeFence(10.0, 11.0, 10.0, 11.0);
		$fence->setUrlenter(null);
		$fence->setUrlleave('httpx://host');
		$fence->setUrlleavepost(1);

		$this->invokePrivate('checkGeoGence', [
			5.0, 5.0, $this->makePoint(10.5, 10.5), 1, $fence,
			'testuser', 'device', 'session', 'token',
		]);

		$this->addToAssertionCount(1);
	}

	public function testProximCloseWithNullUrlclose(): void {
		$movingDeviceId = 1;
		$proxim = $this->makeProxim($movingDeviceId);
		$proxim->setLowlimit(100000);
		$proxim->setHighlimit(0);
		$proxim->setUrlclose(null);
		$proxim->setUrlclosepost(0);
		$proxim->setUrlfar(null);

		// other device is 1 degree away (~111km)
		$otherPoint = $this->makePoint(1.0, 1.0);
		$this->pointMapper->method('getLastDevicePoint')->willReturnCallback(function (int $deviceId) use ($movingDeviceId, $otherPoint) {
			if ($deviceId === $movingDeviceId + 1) {
				return $otherPoint;
			}
			throw new \OCP\AppFramework\Db\DoesNotExistException('not found');
		});
		$this->deviceMapper->method('getSessionOwnerOfDevice')->willReturn('testuser');

		$otherDevice = new Device();
		$otherDevice->setId(2);
		$otherDevice->setName('other');
		$otherDevice->setAlias('');
		$device = new Device();
		$device->setId($movingDeviceId);
		$device->setName('moving');
		$device->setAlias('');
		$devices = [$movingDeviceId => $device, 2 => $otherDevice];
		$this->deviceMapper->method('find')->willReturnCallback(function (int $id) use ($devices) {
			return $devices[$id];
		});

		// last point far, new point close -> CLOSE alert
		$this->invokePrivate('checkProxim', [
			1.5, 1.5, $movingDeviceId, $proxim, 'testuser',
			$this->makePoint(0.0, 0.0), 'device', 'token',
		]);

		$this->addToAssertionCount(1);
	}

	public function testProximFarWithNullUrlfar(): void {
		$movingDeviceId = 1;
		$proxim = $this->makeProxim($movingDeviceId);
		$proxim->setLowlimit(0);
		$proxim->setHighlimit(1000);
		$proxim->setUrlclose(null);
		$proxim->setUrlfar(null);
		$proxim->setUrlfarpost(0);

		// other device is 100m away
		$otherPoint = $this->makePoint(1.0, 1.0);
		$this->pointMapper->method('getLastDevicePoint')->willReturnCallback(function (int $deviceId) use ($movingDeviceId, $otherPoint) {
			if ($deviceId === $movingDeviceId + 1) {
				return $otherPoint;
			}
			throw new \OCP\AppFramework\Db\DoesNotExistException('not found');
		});
		$this->deviceMapper->method('getSessionOwnerOfDevice')->willReturn('testuser');

		$otherDevice = new Device();
		$otherDevice->setId(2);
		$otherDevice->setName('other');
		$otherDevice->setAlias('');
		$device = new Device();
		$device->setId($movingDeviceId);
		$device->setName('moving');
		$device->setAlias('');
		$devices = [$movingDeviceId => $device, 2 => $otherDevice];
		$this->deviceMapper->method('find')->willReturnCallback(function (int $id) use ($devices) {
			return $devices[$id];
		});

		// last point close (0.001 deg ~ 111m < highlimit), new point far -> FAR alert
		$this->assertTrue(ToolsService::distance(1.001, 1.0, 1.0, 1.0) < 1000);
		$this->assertTrue(ToolsService::distance(1.01, 1.0, 1.0, 1.0) > 1000);
		$this->invokePrivate('checkProxim', [
			1.01, 1.0, $movingDeviceId, $proxim, 'testuser',
			$this->makePoint(1.001, 1.0), 'device', 'token',
		]);

		$this->addToAssertionCount(1);
	}
}
