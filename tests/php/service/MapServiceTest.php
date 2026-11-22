<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PhoneTrack\Service;

use Exception;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MapServiceTest extends TestCase {

	private IClient&MockObject $client;
	private MapService $mapService;

	protected function setUp(): void {
		$this->client = $this->createMock(IClient::class);
		$clientService = $this->createStub(IClientService::class);
		$clientService->method('newClient')->willReturn($this->client);

		$this->mapService = new MapService(
			$clientService,
			$this->createStub(LoggerInterface::class),
			$this->createStub(IURLGenerator::class),
			$this->createStub(IL10N::class),
		);
	}

	public static function thunderforestServiceProvider(): array {
		return [
			'ocm' => ['ocm', 'https://b.tile.thunderforest.com/cycle/3/1/2.png'],
			'ocm-highres' => ['ocm-highres', 'https://b.tile.thunderforest.com/cycle/3/1/2@2x.png'],
		];
	}

	#[DataProvider('thunderforestServiceProvider')]
	public function testRasterTileAllowedSubdomain(string $service, string $expectedUrl): void {
		$response = $this->createStub(IResponse::class);
		$response->method('getBody')->willReturn('tile');
		$response->method('getHeader')->willReturn('image/png');

		$this->client->expects($this->once())
			->method('get')
			->with($expectedUrl, [])
			->willReturn($response);

		$tile = $this->mapService->getRasterTile($service, 1, 2, 3, 'b');
		$this->assertSame('tile', $tile['body']);
		$this->assertSame('image/png', $tile['content-type']);
	}

	public static function invalidSubdomainProvider(): array {
		$values = [
			'host with path' => 'example.org/x?',
			'host with fragment' => 'example.org#',
			'userinfo' => 'user@example.org/',
			'empty' => '',
			'unknown letter' => 'd',
			'uppercase' => 'A',
		];
		$cases = [];
		foreach (['ocm', 'ocm-highres'] as $service) {
			foreach ($values as $label => $value) {
				$cases[$service . ' ' . $label] = [$service, $value];
			}
		}
		return $cases;
	}

	#[DataProvider('invalidSubdomainProvider')]
	public function testRasterTileRejectsInvalidSubdomain(string $service, string $s): void {
		$this->client->expects($this->never())->method('get');

		$this->expectException(Exception::class);
		$this->mapService->getRasterTile($service, 1, 2, 3, $s);
	}
}
