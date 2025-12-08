<?php

namespace OCA\PhoneTrack\Service;

use Exception;
use OC\Files\Filesystem;
use OCA\PhoneTrack\AppInfo\Application;
use OCP\App\AppPathNotFoundException;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\Security\ICrypto;

class ToolsService {

	public function __construct(
		private ICrypto $crypto,
		private IConfig $config,
		private IAppManager $appManager,
	) {
	}

	public function getEncryptedUserValue(?string $userId, string $key): string {
		if ($userId === null) {
			return '';
		}
		$rawValue = $this->config->getUserValue($userId, Application::APP_ID, $key);
		if ($rawValue === '') {
			return '';
		}
		return $this->crypto->decrypt($rawValue);
	}

	public function setEncryptedUserValue(string $userId, string $key, string $value): void {
		if ($value === '') {
			$this->config->setUserValue($userId, Application::APP_ID, $key, '');
			return;
		}
		$encryptedValue = $this->crypto->encrypt($value);
		$this->config->setUserValue($userId, Application::APP_ID, $key, $encryptedValue);
	}

	public function getOptionsValues(string $userId): array {
		$ov = [];
		$keys = $this->config->getUserKeys($userId, Application::APP_ID);
		foreach ($keys as $key) {
			$value = $this->config->getUserValue($userId, Application::APP_ID, $key);
			$ov[$key] = $value;
		}
		return $ov;
	}

	public static function distance(float $lat1, float $long1, float $lat2, float $long2): float {
		if ($lat1 === $lat2 && $long1 === $long2) {
			return 0;
		}

		// Convert latitude and longitude to spherical coordinates in radians
		$degrees_to_radians = pi() / 180.0;

		// phi = 90 - latitude
		$phi1 = (90.0 - $lat1) * $degrees_to_radians;
		$phi2 = (90.0 - $lat2) * $degrees_to_radians;

		// theta = longitude
		$theta1 = $long1 * $degrees_to_radians;
		$theta2 = $long2 * $degrees_to_radians;

		$cos = (sin($phi1) * sin($phi2) * cos($theta1 - $theta2)
			+ cos($phi1) * cos($phi2));
		// why are some cosinuses > than 1?
		if ($cos > 1.0) {
			$cos = 1.0;
		}
		$arc = acos($cos);

		// Remember to multiply arc by the radius of the earth in your favorite set of units to get length
		return $arc * 6371000.0;
	}

	public static function DMStoDEC(string $dms, string $longLat): float {
		if ($longLat === 'latitude') {
			$deg = (int)substr($dms, 0, 3);
			$min = (float)substr($dms, 3, 8);
			$sec = 0;
		} else {
			$deg = (int)substr($dms, 0, 3);
			$min = (float)substr($dms, 3, 8);
			$sec = 0;
		}
		return $deg + ((($min * 60) + ($sec)) / 3600);
	}

	/**
	 * @param string $fileName
	 * @param string $color
	 * @return string
	 * @throws AppPathNotFoundException
	 */
	public function getSvgFromApp(string $fileName, string $color = 'ffffff'): string {
		$appPath = $this->appManager->getAppPath(Application::APP_ID);
		$path = $appPath . "/img/$fileName.svg";
		return $this->getSvg($path, $color, $fileName);
	}

	private function getSvg(string $path, string $color, string $fileName): string {
		if (!Filesystem::isValidPath($path)) {
			throw new Exception('not_found', Http::STATUS_NOT_FOUND);
		}

		if (!file_exists($path)) {
			throw new Exception('not_found', Http::STATUS_NOT_FOUND);
		}

		$svg = file_get_contents($path);

		if ($svg === false) {
			throw new Exception('not_found', Http::STATUS_NOT_FOUND);
		}

		return $this->colorizeSvg($svg, $color);
	}

	private function colorizeSvg(string $svg, string $color): string {
		if (!preg_match('/^[0-9a-f]{3,6}$/i', $color)) {
			// Prevent not-sane colors from being written into the SVG
			$color = '000';
		}

		// add fill (fill is not present on black elements)
		$fillRe = '/<((circle|rect|path)((?!fill)[a-z0-9 =".\-#():;,])+)\/>/mi';
		try {
			$colorizedSvg = preg_replace($fillRe, '<$1 fill="#' . $color . '"/>', $svg);

			// replace any fill or stroke colors
			$colorizedSvg = preg_replace('/stroke="#([a-z0-9]{3,6})"/mi', 'stroke="#' . $color . '"', $colorizedSvg);
			$colorizedSvg = preg_replace('/fill="#([a-z0-9]{3,6})"/mi', 'fill="#' . $color . '"', $colorizedSvg);
			return $colorizedSvg ?? $svg;
		} catch (\Exception|\Throwable $e) {
			return $svg;
		}
	}
}
