<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// prevent loading all apps because loading files_external causes oc_external_mounts to be queried
// but it does not exists because this app is not necessarily enabled in the local test environments
putenv('TEST_DONT_LOAD_APPS=1');
require_once __DIR__ . '/../../../tests/bootstrap.php';

use OCA\PhoneTrack\AppInfo\Application;
use OCP\App\IAppManager;

\OC::$server->get(IAppManager::class)->loadApp(Application::APP_ID);
OC_Hook::clear();
