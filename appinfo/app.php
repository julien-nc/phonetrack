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

namespace OCA\PhoneTrack\AppInfo;

use OCP\AppFramework\App;
use OCA\PhoneTrack\Notification\Notifier;

$app = new Application();
$container = $app->getContainer();

$manager = \OC::$server->getNotificationManager();
$manager->registerNotifierService(Notifier::class);

#\OCP\Backgroundjob::addRegularTask('\OCA\PhoneTrack\Cron\AutoExport', 'run');

$container->query('OCP\INavigationManager')->add(function () use ($container) {
    $urlGenerator = $container->query('OCP\IURLGenerator');
    $l10n = $container->query('OCP\IL10N');
    return [
        'id' => 'phonetrack',

        'order' => 10,

        // the route that will be shown on startup
        'href' => $urlGenerator->linkToRoute('phonetrack.page.index'),

        // the icon that will be shown in the navigation
        // this file needs to exist in img/
        'icon' => $urlGenerator->imagePath('phonetrack', 'app.svg'),

        // the title of your application. This will be used in the
        // navigation or on the settings page of your app
        'name' => $l10n->t('PhoneTrack'),
    ];
});
