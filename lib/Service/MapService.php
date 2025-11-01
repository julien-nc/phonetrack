<?php

namespace OCA\PhoneTrack\Service;

use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use OCA\PhoneTrack\AppInfo\Application;
use OCA\PhoneTrack\Db\TileServer;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;
use Throwable;

class MapService {

	private IClient $client;

	public function __construct(
		IClientService $clientService,
		private LoggerInterface $logger,
		private IURLGenerator $urlGenerator,
		private IL10N $l10n,
	) {
		$this->client = $clientService->newClient();
	}

	/**
	 * @param ContentSecurityPolicy $csp
	 * @param TileServer[] $extraTileServers
	 * @return void
	 */
	public function addPageCsp(ContentSecurityPolicy $csp, array $extraTileServers): void {
		$csp
			// raster tiles
			->addAllowedConnectDomain('https://*.openstreetmap.org')
			->addAllowedConnectDomain('https://server.arcgisonline.com')
			->addAllowedConnectDomain('https://*.tile.thunderforest.com')
			->addAllowedConnectDomain('https://stamen-tiles.a.ssl.fastly.net')
			->addAllowedConnectDomain('https://tiles.stadiamaps.com')
			// vector tiles
			->addAllowedConnectDomain('https://api.maptiler.com')
			// for https://api.maptiler.com/resources/logo.svg
			->addAllowedImageDomain('https://api.maptiler.com')
			// nominatim (not needed, we proxy requests through the server)
			//->addAllowedConnectDomain('https://nominatim.openstreetmap.org')
			// maplibre-gl
			->addAllowedWorkerSrcDomain('blob:');

		foreach ($extraTileServers as $ts) {
			$type = $ts->getType();
			$url = $ts->getUrl();
			$domain = parse_url($url, PHP_URL_HOST);
			$scheme = parse_url($url, PHP_URL_SCHEME);
			// extra raster tile servers
			if ($type === Application::TILE_SERVER_RASTER) {
				$domain = str_replace('{s}', '*', $domain);
				if ($scheme === 'http') {
					$csp->addAllowedConnectDomain('http://' . $domain);
				} else {
					$csp->addAllowedConnectDomain('https://' . $domain);
				}
			} else {
				// extra vector tile servers
				if ($scheme === 'http') {
					$csp->addAllowedConnectDomain('http://' . $domain);
				} else {
					$csp->addAllowedConnectDomain('https://' . $domain);
				}
			}
		};
	}

	/**
	 * @param string $service
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @param string|null $s
	 * @return string|null
	 * @throws Exception
	 */
	public function getRasterTile(string $service, int $x, int $y, int $z, ?string $s = null): ?string {
		$options = [];
		if ($service === 'osm') {
			// $url = 'https://' . $s . '.tile.openstreetmap.org/' . $z . '/' . $x . '/' . $y . '.png';
			// recommended tile server: https://operations.osmfoundation.org/policies/tiles/
			$url = 'https://tile.openstreetmap.org/' . $z . '/' . $x . '/' . $y . '.png';
		} elseif ($service === 'ocm') {
			if ($s === null) {
				$s = 'abc'[mt_rand(0, 2)];
			}
			// https://{s}.tile.thunderforest.com/cycle/{z}/{x}/{y}.png
			$url = 'https://' . $s . '.tile.thunderforest.com/cycle/' . $z . '/' . $x . '/' . $y . '.png';
		} elseif ($service === 'osm-highres') {
			$url = 'https://tile.osmand.net/hd/' . $z . '/' . $x . '/' . $y . '.png';
		} elseif ($service === 'ocm-highres') {
			if ($s === null) {
				$s = 'abc'[mt_rand(0, 2)];
			}
			$url = 'https://' . $s . '.tile.thunderforest.com/cycle/' . $z . '/' . $x . '/' . $y . '@2x.png';
		} elseif ($service === 'esri-topo') {
			$url = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/' . $z . '/' . $y . '/' . $x;
		} elseif ($service === 'watercolor') {
			$url = 'https://tiles.stadiamaps.com/styles/stamen_watercolor/' . $z . '/' . $x . '/' . $y . '.jpg';
			// see https://docs.stadiamaps.com/authentication
			$options['headers'] = ['Origin' => 'https://nextcloud.local'];
			// old URLs that don't work
			// $url = 'http://' . $s . '.tile.stamen.com/watercolor/' . $z . '/' . $x . '/' . $y . '.jpg';
			// $url = 'https://stamen-tiles.' . $s . '.ssl.fastly.net/watercolor/' . $z . '/' . $x . '/' . $y . '.jpg';
		} else {
			$url = 'https://tile.openstreetmap.org/' . $z . '/' . $x . '/' . $y . '.png';
		}
		$body = $this->client->get($url, $options)->getBody();
		if (is_resource($body)) {
			$content = stream_get_contents($body);
			return $content === false
				? null
				: $content;
		}
		return $body;
	}

	private function getVectorProxyRequestOptions() {
		$instanceUrl = $this->urlGenerator->getBaseUrl();
		return [
			'headers' => [
				'Origin' => $instanceUrl,
			],
		];
	}

	/**
	 * @param string $version
	 * @param string|null $key
	 * @return array
	 * @throws Exception
	 */
	public function getMapTilerStyle(string $version, ?string $key = null): array {
		$url = 'https://api.maptiler.com/maps/' . $version . '/style.json';
		if ($key !== null) {
			$url .= '?key=' . $key;
		}
		$body = $this->client->get($url, $this->getVectorProxyRequestOptions())->getBody();
		if (is_resource($body)) {
			$content = stream_get_contents($body);
		} else {
			$content = $body;
		}
		$replacementUrl = $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.oldPage.index') . 'maptiler';
		$style = json_decode(preg_replace('/https:\/\/api\.maptiler\.com/', $replacementUrl, $content), true);
		foreach ($style['layers'] as $i => $layer) {
			if (is_array($layer['layout']) && empty($layer['layout'])) {
				$style['layers'][$i]['layout'] = (object)[];
			}
		}
		return $style;
	}

	/**
	 * @param string $fontstack
	 * @param string $range
	 * @param string|null $key
	 * @return string|null
	 * @throws Exception
	 */
	public function getMapTilerFont(string $fontstack, string $range, ?string $key = null): ?string {
		// https://api.maptiler.com/fonts/{fontstack}/{range}.pbf?key=' + apiKey
		$url = 'https://api.maptiler.com/fonts/' . $fontstack . '/' . $range . '.pbf';
		if ($key !== null) {
			$url .= '?key=' . $key;
		}
		$body = $this->client->get($url, $this->getVectorProxyRequestOptions())->getBody();
		if (is_resource($body)) {
			$content = stream_get_contents($body);
			return $content === false
				? null
				: $content;
		}
		return $body;
	}

	/**
	 * @param string $version
	 * @param string|null $key
	 * @return array
	 * @throws Exception
	 */
	public function getMapTilerTiles(string $version, ?string $key = null): array {
		$url = 'https://api.maptiler.com/tiles/' . $version . '/tiles.json';
		if ($key !== null) {
			$url .= '?key=' . $key;
		}
		$body = $this->client->get($url, $this->getVectorProxyRequestOptions())->getBody();
		if (is_resource($body)) {
			$content = stream_get_contents($body);
			if ($content === false) {
				throw new Exception('No content');
			}
		} else {
			$content = $body;
		}
		$replacementUrl = $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.oldPage.index') . 'maptiler';
		return json_decode(preg_replace('/https:\/\/api\.maptiler\.com/', $replacementUrl, $content), true);
	}

	/**
	 * @param string $version
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @param string $ext
	 * @param string|null $key
	 * @return array
	 * @throws Exception
	 */
	public function getMapTilerTile(string $version, int $x, int $y, int $z, string $ext, ?string $key = null): array {
		$url = 'https://api.maptiler.com/tiles/' . $version . '/' . $z . '/' . $x . '/' . $y . '.' . $ext;
		if ($key !== null) {
			$url .= '?key=' . $key;
		}
		$response = $this->client->get($url, $this->getVectorProxyRequestOptions());
		$body = $response->getBody();
		$headers = $response->getHeaders();
		return [
			'body' => $body,
			'headers' => $headers,
		];
	}

	/**
	 * @param string $version
	 * @param string $size
	 * @return array
	 * @throws Exception
	 */
	public function getMapTilerSpriteJson(string $version, string $size): array {
		$url = 'https://api.maptiler.com/maps/' . $version . '/sprite' . $size . '.json';
		$body = $this->client->get($url, $this->getVectorProxyRequestOptions())->getBody();
		if (is_resource($body)) {
			$content = stream_get_contents($body);
			if ($content === false) {
				throw new Exception('No content');
			}
			return json_decode($content, true);
		}
		return json_decode($body, true);
	}

	/**
	 * @param string $version
	 * @param string $size
	 * @param string $ext
	 * @return array
	 * @throws Exception
	 */
	public function getMapTilerSpriteImage(string $version, string $size, string $ext): array {
		$url = 'https://api.maptiler.com/maps/' . $version . '/sprite' . $size . '.' . $ext;
		$response = $this->client->get($url, $this->getVectorProxyRequestOptions());
		$body = $response->getBody();
		$headers = $response->getHeaders();
		return [
			'body' => $body,
			'headers' => $headers,
		];
	}

	/**
	 * @param string $name
	 * @return array
	 * @throws Exception
	 */
	public function getMapTilerResource(string $name): array {
		$url = 'https://api.maptiler.com/resources/' . $name;
		$response = $this->client->get($url, $this->getVectorProxyRequestOptions());
		$body = $response->getBody();
		$headers = $response->getHeaders();
		return [
			'body' => $body,
			'headers' => $headers,
		];
	}

	/**
	 * Search items
	 *
	 * @param string $userId
	 * @param string $query
	 * @param string $format
	 * @param array $extraParams
	 * @param int $offset
	 * @param int $limit
	 * @return array request result
	 */
	public function searchLocation(string $userId, string $query, string $format = 'json', array $extraParams = [], int $offset = 0, int $limit = 5): array {
		// no pagination...
		$limitParam = $offset + $limit;
		$params = [
			'q' => $query,
			'format' => $format,
			'limit' => $limitParam,
		];
		foreach ($extraParams as $k => $v) {
			if ($v !== null) {
				$params[$k] = $v;
			}
		}
		$result = $this->request($userId, 'search', $params);
		if (!isset($result['error'])) {
			return array_slice($result, $offset, $limit);
		}
		return $result;
	}

	/**
	 * Make an HTTP request to the Osm API
	 *
	 * @param string|null $userId
	 * @param string $endPoint The path to reach in https://nominatim.openstreetmap.org
	 * @param array $params Query parameters (key/val pairs)
	 * @param string $method HTTP query method
	 * @param bool $rawResponse
	 * @return array decoded request result or error
	 */
	public function request(?string $userId, string $endPoint, array $params = [], string $method = 'GET', bool $rawResponse = false): array {
		try {
			$url = 'https://nominatim.openstreetmap.org/' . $endPoint;
			$options = [
				'headers' => [
					'User-Agent' => 'Nextcloud OpenStreetMap integration',
					//					'Authorization' => 'MediaBrowser Token="' . $token . '"',
					'Content-Type' => 'application/json',
				],
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					$paramsContent = http_build_query($params);
					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = json_encode($params);
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} elseif ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} elseif ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} elseif ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				if ($rawResponse) {
					return [
						'body' => $body,
						'headers' => $response->getHeaders(),
					];
				} else {
					return json_decode($body, true) ?: [];
				}
			}
		} catch (ClientException|ServerException $e) {
			$responseBody = $e->getResponse()->getBody();
			$parsedResponseBody = json_decode($responseBody, true);
			if ($e->getResponse()->getStatusCode() === 404) {
				// Only log inaccessible github links as debug
				$this->logger->debug('Osm API error : ' . $e->getMessage(), ['response_body' => $parsedResponseBody, 'app' => Application::APP_ID]);
			} else {
				$this->logger->warning('Osm API error : ' . $e->getMessage(), ['response_body' => $parsedResponseBody, 'app' => Application::APP_ID]);
			}
			return [
				'error' => $e->getMessage(),
				'body' => $parsedResponseBody,
			];
		} catch (Exception|Throwable $e) {
			$this->logger->warning('Osm API error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}
}
