<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PhoneTrack\Service;

use DOMDocument;
use DOMXPath;
use OCA\PhoneTrack\Db\Device;
use OCA\PhoneTrack\Db\DeviceMapper;
use OCA\PhoneTrack\Db\Point;
use OCA\PhoneTrack\Db\PointMapper;
use OCA\PhoneTrack\Db\SessionMapper;
use OCP\IUserManager;
use OCP\Server;
use PHPUnit\Framework\TestCase;

class SessionServiceGpxExportTest extends TestCase {

	private const USER_1 = 'pt_gpx_export_test';
	private const USER_PASSWORD = 'T0T0T0';

	private static bool $user1CreatedByTest = false;

	private SessionService $sessionService;
	private SessionMapper $sessionMapper;

	public static function setUpBeforeClass(): void {
		$userManager = Server::get(IUserManager::class);
		if ($userManager->get(self::USER_1) === null) {
			$userManager->createUser(self::USER_1, self::USER_PASSWORD);
			self::$user1CreatedByTest = true;
		}
	}

	public static function tearDownAfterClass(): void {
		if (self::$user1CreatedByTest) {
			$user = Server::get(IUserManager::class)->get(self::USER_1);
			if ($user !== null) {
				$user->delete();
			}
		}
	}

	protected function setUp(): void {
		$this->sessionService = Server::get(SessionService::class);
		$this->sessionMapper = Server::get(SessionMapper::class);
		$this->deleteSessions();
	}

	protected function tearDown(): void {
		$this->deleteSessions();
	}

	public function testGpxExportEscapesTextValues(): void {
		$sessionName = 'Trip & Co <1>';
		$deviceName = 'phone "A" & <b>';
		$userAgent = "agent</useragent><name>injected</name><useragent>\x01";

		$session = $this->sessionService->createSession(self::USER_1, $sessionName);

		$device = new Device();
		$device->setSessionId($session->getId());
		$device->setSessionToken($session->getToken());
		$device->setName($deviceName);
		$device->setEnabled(1);
		$device->setColorCriteria(0);
		$device = Server::get(DeviceMapper::class)->insert($device);

		$point = new Point();
		$point->setDeviceid($device->getId());
		$point->setLat(45.5);
		$point->setLon(4.5);
		$point->setTimestamp(1700000000);
		$point->setUseragent($userAgent);
		Server::get(PointMapper::class)->insert($point);

		$gpx = implode('', iterator_to_array($this->sessionService->getSessionGpxData($session, self::USER_1), false));

		$dom = new DOMDocument();
		$this->assertTrue($dom->loadXML($gpx), 'exported GPX is not well-formed XML');

		$xpath = new DOMXPath($dom);
		$xpath->registerNamespace('gpx', 'http://www.topografix.com/GPX/1/1');
		$this->assertSame($sessionName, $xpath->evaluate('string(/gpx:gpx/gpx:metadata/gpx:name)'));
		$this->assertSame(1, $xpath->query('/gpx:gpx/gpx:trk')->length);
		$this->assertSame($deviceName, $xpath->evaluate('string(/gpx:gpx/gpx:trk/gpx:name)'));
		$this->assertSame(0, $xpath->query('//gpx:extensions/gpx:name')->length);
		$this->assertSame(
			'agent</useragent><name>injected</name><useragent>',
			$xpath->evaluate('string(//gpx:trkpt/gpx:extensions/gpx:useragent)'),
		);
	}

	private function deleteSessions(): void {
		foreach ($this->sessionMapper->findByUser(self::USER_1) as $session) {
			$this->sessionService->deleteSession($session);
		}
	}
}
