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
}
