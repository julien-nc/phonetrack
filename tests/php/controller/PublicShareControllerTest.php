<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PhoneTrack\Controller;

use OCA\PhoneTrack\AppInfo\Application;
use OCA\PhoneTrack\Db\DeviceMapper;
use OCA\PhoneTrack\Db\PointMapper;
use OCA\PhoneTrack\Db\PublicShareMapper;
use OCA\PhoneTrack\Db\SessionMapper;
use OCA\PhoneTrack\Db\TileServer;
use OCA\PhoneTrack\Db\TileServerMapper;
use OCA\PhoneTrack\Service\MapService;
use OCA\PhoneTrack\Service\SessionService;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Server;
use PHPUnit\Framework\TestCase;

class PublicShareControllerTest extends TestCase {

	private const USER_1 = 'pt_public_share_test';
	private const USER_PASSWORD = 'T0T0T0';

	private static bool $user1CreatedByTest = false;

	private SessionMapper $sessionMapper;
	private SessionService $sessionService;
	private TileServerMapper $tileServerMapper;
	/** @var TileServer[] */
	private array $createdTileServers = [];

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
		$this->sessionMapper = Server::get(SessionMapper::class);
		$this->sessionService = Server::get(SessionService::class);
		$this->tileServerMapper = Server::get(TileServerMapper::class);
		$this->deleteSessions();
	}

	protected function tearDown(): void {
		foreach ($this->createdTileServers as $tileServer) {
			$this->tileServerMapper->deleteTileserver($tileServer->getId(), $tileServer->getUserId());
		}
		$this->createdTileServers = [];
		$this->deleteSessions();
	}

	public function testPublicPageDoesNotExposeTileServerOwner(): void {
		$session = $this->sessionService->createSession(self::USER_1, 'public-tile-server-test');
		$ownerTileServer = $this->tileServerMapper->createTileServer(
			self::USER_1, Application::TILE_SERVER_RASTER, 'owner tiles', 'https://{s}.tiles.example.org/{z}/{x}/{y}.png', null,
		);
		$this->createdTileServers[] = $ownerTileServer;

		$providedState = null;
		$initialState = $this->createMock(IInitialState::class);
		$initialState->expects($this->once())
			->method('provideInitialState')
			->with('phonetrack-state', $this->anything())
			->willReturnCallback(function (string $key, $data) use (&$providedState): void {
				$providedState = $data;
			});

		$controller = new PublicShareController(
			Application::APP_ID,
			Server::get(IRequest::class),
			Server::get(IAppConfig::class),
			(new Application())->getContainer()->get(IL10N::class),
			$this->sessionService,
			Server::get(MapService::class),
			$this->sessionMapper,
			Server::get(DeviceMapper::class),
			Server::get(PointMapper::class),
			Server::get(PublicShareMapper::class),
			$this->tileServerMapper,
			$initialState,
		);
		$controller->index($session->getPublicviewtoken());

		$this->assertIsArray($providedState);
		// round-trip through JSON like the initial state does
		$state = json_decode(json_encode($providedState), true);
		$tileServers = $state['settings']['extra_tile_servers'];

		// the owner's tile server is still available to the public page
		$ownerEntries = array_values(array_filter($tileServers, static fn (array $ts) => $ts['id'] === $ownerTileServer->getId()));
		$this->assertCount(1, $ownerEntries);
		$this->assertSame('owner tiles', $ownerEntries[0]['name']);
		$this->assertSame('https://{s}.tiles.example.org/{z}/{x}/{y}.png', $ownerEntries[0]['url']);

		// but it does not say who owns it
		foreach ($tileServers as $tileServer) {
			$this->assertArrayNotHasKey('user_id', $tileServer);
		}
		$this->assertStringNotContainsString(self::USER_1, json_encode($state));
	}

	private function deleteSessions(): void {
		foreach ($this->sessionMapper->findByUser(self::USER_1) as $session) {
			$this->sessionService->deleteSession($session);
		}
	}
}
