<?php

/**
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\PhoneTrack\Controller;

use OCA\PhoneTrack\AppInfo\Application;
use OCA\PhoneTrack\Db\DeviceMapper;
use OCA\PhoneTrack\Db\GeofenceMapper;
use OCA\PhoneTrack\Db\PointMapper;
use OCA\PhoneTrack\Db\ProximMapper;
use OCA\PhoneTrack\Db\PublicShareMapper;
use OCA\PhoneTrack\Db\SessionMapper;
use OCA\PhoneTrack\Db\ShareMapper;
use OCA\PhoneTrack\Db\TileServerMapper;
use OCA\PhoneTrack\Service\MapService;
use OCA\PhoneTrack\Service\SessionService;
use OCA\PhoneTrack\Service\ToolsService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Server;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PageControllerTest extends TestCase {

	private const USER_1 = 'pt_page_test';
	private const USER_2 = 'pt_page_test2';
	private const USER_PASSWORD = 'T0T0T0';

	private static bool $user1CreatedByTest = false;
	private static bool $user2CreatedByTest = false;

	private PageController $pageController;
	private PageController $pageController2;
	private SessionMapper $sessionMapper;
	private SessionService $sessionService;

	public static function setUpBeforeClass(): void {
		$userManager = Server::get(IUserManager::class);

		if ($userManager->get(self::USER_1) === null) {
			$userManager->createUser(self::USER_1, self::USER_PASSWORD);
			self::$user1CreatedByTest = true;
		}

		if ($userManager->get(self::USER_2) === null) {
			$userManager->createUser(self::USER_2, self::USER_PASSWORD);
			self::$user2CreatedByTest = true;
		}
	}

	protected function setUp(): void {
		$app = new Application();
		$container = $app->getContainer();

		$request = Server::get(IRequest::class);
		$logger = Server::get(LoggerInterface::class);
		$l10n = $container->get(IL10N::class);

		$this->sessionMapper = Server::get(SessionMapper::class);
		$this->sessionService = Server::get(SessionService::class);

		$this->pageController = new PageController(
			Application::APP_ID,
			$request,
			$logger,
			$l10n,
			$this->sessionMapper,
			$this->sessionService,
			Server::get(DeviceMapper::class),
			Server::get(PublicShareMapper::class),
			Server::get(ShareMapper::class),
			Server::get(GeofenceMapper::class),
			Server::get(ProximMapper::class),
			Server::get(PointMapper::class),
			$container->get(IInitialState::class),
			Server::get(IAppConfig::class),
			Server::get(IUserManager::class),
			Server::get(ToolsService::class),
			Server::get(MapService::class),
			Server::get(TileServerMapper::class),
			self::USER_1,
		);

		$this->pageController2 = new PageController(
			Application::APP_ID,
			$request,
			$logger,
			$l10n,
			$this->sessionMapper,
			$this->sessionService,
			Server::get(DeviceMapper::class),
			Server::get(PublicShareMapper::class),
			Server::get(ShareMapper::class),
			Server::get(GeofenceMapper::class),
			Server::get(ProximMapper::class),
			Server::get(PointMapper::class),
			$container->get(IInitialState::class),
			Server::get(IAppConfig::class),
			Server::get(IUserManager::class),
			Server::get(ToolsService::class),
			Server::get(MapService::class),
			Server::get(TileServerMapper::class),
			self::USER_2,
		);

		$this->deleteSessionsForUser(self::USER_1);
		$this->deleteSessionsForUser(self::USER_2);
	}

	protected function tearDown(): void {
		$this->deleteSessionsForUser(self::USER_1);
		$this->deleteSessionsForUser(self::USER_2);
	}

	public static function tearDownAfterClass(): void {
		$userManager = Server::get(IUserManager::class);

		if (self::$user1CreatedByTest) {
			$user = $userManager->get(self::USER_1);
			if ($user !== null) {
				$user->delete();
			}
		}

		if (self::$user2CreatedByTest) {
			$user = $userManager->get(self::USER_2);
			if ($user !== null) {
				$user->delete();
			}
		}
	}

	public function testCreateSession(): void {
		$sessionName = 'create-session-test';
		$response = $this->pageController->createSession($sessionName);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());

		$data = $response->getData();
		$this->assertEquals($sessionName, $data['name']);
		$this->assertEquals(self::USER_1, $data['user']);
		$this->assertArrayHasKey('id', $data);
		$this->assertTrue(is_int($data['id']) && $data['id'] > 0);
		$this->assertArrayHasKey('token', $data);
		$this->assertNotSame('', $data['token']);

		$session = $this->sessionMapper->getUserSessionById(self::USER_1, $data['id']);
		$this->assertEquals($sessionName, $session->getName());
	}

	public function testCreateSessionWithDuplicateNameReturnsBadRequest(): void {
		$sessionName = 'duplicate-session-test';

		$first = $this->pageController->createSession($sessionName);
		$this->assertEquals(Http::STATUS_OK, $first->getStatus());

		$second = $this->pageController->createSession($sessionName);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $second->getStatus());

		$data = $second->getData();
		$this->assertEquals('already_exists', $data['error']);
	}

	public function testDeleteSession(): void {
		$createResponse = $this->pageController->createSession('delete-session-test');
		$createdSession = $createResponse->getData();

		$deleteResponse = $this->pageController->deleteSession($createdSession['id']);
		$this->assertEquals(Http::STATUS_OK, $deleteResponse->getStatus());
		$this->assertEquals([], $deleteResponse->getData());

		$this->expectException(DoesNotExistException::class);
		$this->sessionMapper->getUserSessionById(self::USER_1, $createdSession['id']);
	}

	public function testDeleteSessionOfAnotherUserReturnsBadRequest(): void {
		$createResponse = $this->pageController2->createSession('other-user-session-test');
		$createdSession = $createResponse->getData();

		$deleteResponse = $this->pageController->deleteSession($createdSession['id']);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $deleteResponse->getStatus());

		$data = $deleteResponse->getData();
		$this->assertEquals('session_not_found', $data['error']);
	}

	public function testDeleteSessionThatDoesNotExistReturnsBadRequest(): void {
		$deleteResponse = $this->pageController->deleteSession(999999);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $deleteResponse->getStatus());

		$data = $deleteResponse->getData();
		$this->assertEquals('session_not_found', $data['error']);
	}

	private function deleteSessionsForUser(string $userId): void {
		$sessions = $this->sessionMapper->findByUser($userId);
		foreach ($sessions as $session) {
			$this->sessionService->deleteSession($session);
		}
	}
}
