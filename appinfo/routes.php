<?php
/**
 * ownCloud - phonetrack
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@gmx.fr>
 * @copyright Julien Veyssier 2017
 */

/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\PhoneTrack\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#publicWebTrack', 'url' => '/publicWebTrack/{token}/{deviceid}', 'verb' => 'GET'],
        ['name' => 'page#publicSessionWatch', 'url' => '/publicSessionWatch/{token}', 'verb' => 'GET'],
        ['name' => 'page#createSession', 'url' => '/createSession', 'verb' => 'POST'],
        ['name' => 'page#deleteSession', 'url' => '/deleteSession', 'verb' => 'POST'],
        ['name' => 'page#getSessions', 'url' => '/getSessions', 'verb' => 'POST'],
        ['name' => 'page#renameSession', 'url' => '/renameSession', 'verb' => 'POST'],
        ['name' => 'page#logGet', 'url' => '/logGet/{token}/{deviceid}', 'verb' => 'GET'],
        ['name' => 'page#logPost', 'url' => '/logPost/{token}/{deviceid}', 'verb' => 'POST'],
        ['name' => 'page#logOsmand', 'url' => '/log/osmand/{token}/{deviceid}', 'verb' => 'GET'],
        ['name' => 'page#logGpsloggerGet', 'url' => '/log/gpslogger/{token}/{deviceid}', 'verb' => 'GET'],
        ['name' => 'page#logGpsloggerPost', 'url' => '/log/gpslogger/{token}/{deviceid}', 'verb' => 'POST'],
        ['name' => 'page#logOwntracks', 'url' => '/log/owntracks/{token}/{deviceid}', 'verb' => 'POST', 'defaults' => array('deviceid' => '')],
        ['name' => 'page#logUlogger', 'url' => '/log/ulogger/{token}/{deviceid}/client/index.php', 'verb' => 'POST'],
        ['name' => 'page#logTraccar', 'url' => '/log/traccar/{token}/{deviceid}', 'verb' => 'POST', 'defaults' => array('deviceid' => '')],
        ['name' => 'page#logOpengts', 'url' => '/log/opengts/{token}/{deviceid}', 'verb' => 'GET', 'defaults' => array('deviceid' => '')],
        ['name' => 'page#logOpengtsPost', 'url' => '/log/opengts/{token}/{deviceid}', 'verb' => 'POST'],
        ['name' => 'page#track', 'url' => '/track', 'verb' => 'POST'],
        ['name' => 'page#export', 'url' => '/export', 'verb' => 'POST'],
        ['name' => 'page#deleteDevice', 'url' => '/deleteDevice', 'verb' => 'POST'],
        ['name' => 'utils#addTileServer', 'url' => '/addTileServer', 'verb' => 'POST'],
        ['name' => 'utils#deleteTileServer', 'url' => '/deleteTileServer', 'verb' => 'POST'],
        ['name' => 'utils#getOptionsValues', 'url' => '/getOptionsValues', 'verb' => 'POST'],
        ['name' => 'utils#saveOptionsValues', 'url' => '/saveOptionsValues', 'verb' => 'POST'],
    ]
];
