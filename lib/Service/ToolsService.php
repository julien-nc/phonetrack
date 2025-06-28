<?php

namespace OCA\PhoneTrack\Service;

use OCA\PhoneTrack\AppInfo\Application;
use OCP\IConfig;
use OCP\Security\ICrypto;

class ToolsService {

	public function __construct(
		private ICrypto $crypto,
		private IConfig $config,
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

		$cos = (sin($phi1) * sin($phi2) * cos($theta1 - $theta2) +
			cos($phi1) * cos($phi2));
		// why are some cosinuses > than 1?
		if ($cos > 1.0) {
			$cos = 1.0;
		}
		$arc = acos($cos);

		// Remember to multiply arc by the radius of the earth in your favorite set of units to get length
		return $arc * 6371000.0;
	}
}
