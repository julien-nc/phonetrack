<?php

/**
 * Nextcloud - phonetrack
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2017
 */

namespace OCA\PhoneTrack\Controller;

use OCA\PhoneTrack\AppInfo\Application;
use OCA\PhoneTrack\Service\ToolsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequest;

class UtilsController extends Controller {

	private string $dbDoubleQuotes;

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IDBConnection $dbConnection,
		private ToolsService $toolsService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
		$dbType = $config->getSystemValue('dbtype');
		if ($dbType === 'pgsql') {
			$this->dbDoubleQuotes = '"';
		} else {
			$this->dbDoubleQuotes = '';
		}
	}

	/*
	 * quote and choose string escape function depending on database used
	 */
	private function db_quote_escape_string($str) {
		return $this->dbConnection->quote($str);
	}

	/**
	 * set global point quota
	 */
	public function setPointQuota($quota) {
		$this->config->setAppValue('phonetrack', 'pointQuota', $quota);
		return new DataResponse(['done' => '1']);
	}

	/**
	 * Add one tile server to the DB for current user
	 */
	#[NoAdminRequired]
	public function addTileServer(string $servername, string $serverurl, string $type, string $token = '',
		string $layers = '', string $version = '', string $tformat = '', string $opacity = '', bool $transparent = false,
		int $minzoom = 1, int $maxzoom = 18, string $attribution = '') {
		// first we check it does not already exist
		$sqlts = '
			SELECT servername
			FROM *PREFIX*phonetrack_tileserver
			WHERE ' . $this->dbDoubleQuotes . 'user' . $this->dbDoubleQuotes . '=' . $this->db_quote_escape_string($this->userId) . '
				  AND servername=' . $this->db_quote_escape_string($servername) . '
				  AND type=' . $this->db_quote_escape_string($type) . ' ;';
		$req = $this->dbConnection->prepare($sqlts);
		$req->execute();
		$ts = null;
		while ($row = $req->fetch()) {
			$ts = $row['servername'];
			break;
		}
		$req->closeCursor();

		// then if not, we insert it
		if ($ts === null) {
			$sql = '
				INSERT INTO *PREFIX*phonetrack_tileserver
				(' . $this->dbDoubleQuotes . 'user' . $this->dbDoubleQuotes . ', type, servername, url, token, layers, version, format, opacity, transparent, minzoom, maxzoom, attribution)
				VALUES ('
					. $this->db_quote_escape_string($this->userId) . ','
					. $this->db_quote_escape_string($type) . ','
					. $this->db_quote_escape_string($servername) . ','
					. $this->db_quote_escape_string($serverurl) . ','
					. $this->db_quote_escape_string($token) . ','
					. $this->db_quote_escape_string($layers) . ','
					. $this->db_quote_escape_string($version) . ','
					. $this->db_quote_escape_string($tformat) . ','
					. $this->db_quote_escape_string($opacity) . ','
					. $this->db_quote_escape_string($transparent) . ','
					. $this->db_quote_escape_string($minzoom) . ','
					. $this->db_quote_escape_string($maxzoom) . ','
					. $this->db_quote_escape_string($attribution) . '
				) ;';
			$req = $this->dbConnection->prepare($sql);
			$req->execute();
			$req->closeCursor();
			$ok = 1;
		} else {
			$ok = 0;
		}

		return new DataResponse(['done' => $ok]);
	}

	/**
	 * Delete one tile server entry from DB for current user
	 */
	#[NoAdminRequired]
	public function deleteTileServer($servername, $type) {
		$sqldel = '
			DELETE FROM *PREFIX*phonetrack_tileserver
			WHERE ' . $this->dbDoubleQuotes . 'user' . $this->dbDoubleQuotes . '=' . $this->db_quote_escape_string($this->userId) . '
				  AND servername=' . $this->db_quote_escape_string($servername) . '
				  AND type=' . $this->db_quote_escape_string($type) . ' ;';
		$req = $this->dbConnection->prepare($sqldel);
		$req->execute();
		$req->closeCursor();

		return new DataResponse(['done' => 1]);
	}

	/**
	 * Delete user options
	 */
	#[NoAdminRequired]
	public function deleteOptionsValues() {
		$keys = $this->config->getUserKeys($this->userId, 'phonetrack');
		foreach ($keys as $key) {
			$this->config->deleteUserValue($this->userId, 'phonetrack', $key);
		}
		return new DataResponse(['done' => 1]);
	}

	/**
	 * Save options values to the DB for current user
	 */
	#[NoAdminRequired]
	public function saveOptionValue($options) {
		foreach ($options as $key => $value) {
			if (is_bool($value)) {
				$value = $value ? 'true' : 'false';
			}
			$this->config->setUserValue($this->userId, 'phonetrack', $key, $value);
		}
		return new DataResponse(['done' => true]);
	}

	/**
	 * Save options values to the DB for current user
	 */
	#[NoAdminRequired]
	public function saveOptionValues(array $values): DataResponse {
		foreach ($values as $key => $value) {
			if (is_bool($value)) {
				$value = $value ? '1' : '0';
			}
			if ($key === 'maptiler_api_key' && $value !== '') {
				$this->toolsService->setEncryptedUserValue($this->userId, $key, $value);
			} else {
				$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
			}
		}

		return new DataResponse('');
	}

	/**
	 * get options values from the config for current user
	 */
	#[NoAdminRequired]
	public function getOptionsValues() {
		$ov = [];
		$keys = $this->config->getUserKeys($this->userId, 'phonetrack');
		foreach ($keys as $key) {
			$value = $this->config->getUserValue($this->userId, 'phonetrack', $key);
			$ov[$key] = $value;
		}
		return new DataResponse(['values' => $ov]);
	}
}
