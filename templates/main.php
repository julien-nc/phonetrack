<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCP\Util;

$appId = OCA\Phonetrack\AppInfo\Application::APP_ID;
Util::addScript($appId, $appId . '-phonetrack');

// fontawesome/fortawesome
Util::addStyle($appId, 'fontawesome-free/css/all.min');
Util::addStyle($appId, 'style');

?>

<div id="app">
	<div id="app-content">
			<?php print_unescaped($this->inc('maincontent')); ?>
	</div>
</div>
