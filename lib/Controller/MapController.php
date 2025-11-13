<?php

namespace OCA\PhoneTrack\Controller;

use OCA\PhoneTrack\Service\MapService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\DB\Exception;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use Throwable;

class MapController extends Controller {

	public function __construct(
		$appName,
		IRequest $request,
		private MapService $mapService,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}


	/**
	 * @param string $service
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @param string|null $s
	 * @return DataDisplayResponse
	 * @throws \Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getRasterTile(string $service, int $x, int $y, int $z, ?string $s = null): DataDisplayResponse {
		try {
			$response = new DataDisplayResponse($this->mapService->getRasterTile($service, $x, $y, $z, $s));
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (Exception|Throwable $e) {
			$this->logger->debug('Raster tile not found', ['exception' => $e]);
			return new DataDisplayResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @param string $version
	 * @param string|null $key
	 * @return Response
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTilerStyle(string $version, ?string $key = null): Response {
		try {
			$response = new JSONResponse($this->mapService->getMapTilerStyle($version, $key));
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (Exception|Throwable $e) {
			$this->logger->debug('Style not found', ['exception' => $e]);
			return new JSONResponse(['exception' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @param string $fontstack
	 * @param string $range
	 * @param string|null $key
	 * @return Response
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTilerFont(string $fontstack, string $range, ?string $key = null): Response {
		try {
			$response = new DataDisplayResponse($this->mapService->getMapTilerFont($fontstack, $range, $key));
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (Exception|Throwable $e) {
			$this->logger->debug('Font not found', ['exception' => $e]);
			return new JSONResponse(['exception' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @param string $version
	 * @param string|null $key
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTilerTiles(string $version, ?string $key = null): JSONResponse {
		try {
			$response = new JSONResponse($this->mapService->getMapTilerTiles($version, $key));
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (Exception|Throwable $e) {
			$this->logger->debug('Tiles not found', ['exception' => $e]);
			return new JSONResponse(['exception' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @param array $headers
	 * @param string $defaultType
	 * @return string
	 */
	private function getContentTypeFromHeaders(array $headers, string $defaultType): string {
		if (isset($headers['Content-Type'])) {
			if (is_string($headers['Content-Type'])) {
				return $headers['Content-Type'];
			} elseif (is_array($headers['Content-Type'])
				&& count($headers['Content-Type']) > 0
				&& is_string($headers['Content-Type'][0])
			) {
				return $headers['Content-Type'][0];
			}
		}
		return $defaultType;
	}

	/**
	 * @param string $version
	 * @param int $z
	 * @param int $x
	 * @param int $y
	 * @param string $ext
	 * @param string|null $key
	 * @return Response
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTilerTile(string $version, int $z, int $x, int $y, string $ext, ?string $key = null): Response {
		try {
			$tileResponse = $this->mapService->getMapTilerTile($version, $x, $y, $z, $ext, $key);
			$response = new DataDisplayResponse(
				$tileResponse['body'],
				Http::STATUS_OK,
				['Content-Type' => $this->getContentTypeFromHeaders($tileResponse['headers'], 'image/jpeg')],
			);
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (Exception|Throwable $e) {
			$this->logger->debug('Tile not found', ['exception' => $e]);
			return new JSONResponse(['exception' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTilerSpriteNoSize(string $version, string $ext): Response {
		return $this->getMapTilerSprite($version, $ext);
	}

	/**
	 * @param string $version
	 * @param string $ext
	 * @param string $size
	 * @return Response
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTilerSprite(string $version, string $ext, string $size = ''): Response {
		try {
			if ($ext === 'json') {
				$sprite = $this->mapService->getMapTilerSpriteJson($version, $size);
				$response = new JSONResponse($sprite);
			} else {
				$sprite = $this->mapService->getMapTilerSpriteImage($version, $size, $ext);
				$response = new DataDisplayResponse(
					$sprite['body'],
					Http::STATUS_OK,
					['Content-Type' => $this->getContentTypeFromHeaders($sprite['headers'], 'image/png')],
				);
			}
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (Exception|Throwable $e) {
			$this->logger->debug('Sprite not found', ['exception' => $e]);
			return new JSONResponse(['exception' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @param string $name
	 * @return Response
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTilerResource(string $name): Response {
		try {
			$resourceResponse = $this->mapService->getMapTilerResource($name);
			$response = new DataDisplayResponse(
				$resourceResponse['body'],
				Http::STATUS_OK,
				['Content-Type' => $this->getContentTypeFromHeaders($resourceResponse['headers'], 'image/png')],
			);
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (Exception|Throwable $e) {
			$this->logger->debug('Resource not found', ['exception' => $e]);
			return new JSONResponse(['exception' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @param string $q
	 * @param string $rformat
	 * @param int|null $polygon_geojson
	 * @param int|null $addressdetails
	 * @param int|null $namedetails
	 * @param int|null $extratags
	 * @param int $limit
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function nominatimSearch(
		string $q, string $rformat = 'json', ?int $polygon_geojson = null, ?int $addressdetails = null,
		?int $namedetails = null, ?int $extratags = null, int $limit = 10,
	): DataResponse {
		$extraParams = [
			'polygon_geojson' => $polygon_geojson,
			'addressdetails' => $addressdetails,
			'namedetails' => $namedetails,
			'extratags' => $extratags,
		];
		$searchResults = $this->mapService->searchLocation($this->userId, $q, $rformat, $extraParams, 0, $limit);
		if (isset($searchResults['error'])) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}
		$response = new DataResponse($searchResults);
		$response->cacheFor(60 * 60 * 24, false, true);
		return $response;
	}
}
