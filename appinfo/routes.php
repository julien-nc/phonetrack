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
        ['name' => 'page#publicWebLog', 'url' => '/publicWebLog/{token}/{devicename}', 'verb' => 'GET'],
        ['name' => 'page#publicSessionWatch', 'url' => '/publicSessionWatch/{publicviewtoken}', 'verb' => 'GET'],
        ['name' => 'page#createSession', 'url' => '/createSession', 'verb' => 'POST'],
        ['name' => 'page#deleteSession', 'url' => '/deleteSession', 'verb' => 'POST'],
        ['name' => 'page#deletePoints', 'url' => '/deletePoints', 'verb' => 'POST'],
        ['name' => 'page#updatePoint', 'url' => '/updatePoint', 'verb' => 'POST'],
        ['name' => 'page#addPoint', 'url' => '/addPoint', 'verb' => 'POST'],
        ['name' => 'page#getSessions', 'url' => '/getSessions', 'verb' => 'POST'],
        ['name' => 'page#APIgetLastPositions', 'url' => '/APIgetLastPositions/{sessionid}', 'verb' => 'GET'],
        ['name' => 'page#importSession', 'url' => '/importSession', 'verb' => 'POST'],
        ['name' => 'page#renameSession', 'url' => '/renameSession', 'verb' => 'POST'],
        ['name' => 'page#renameDevice', 'url' => '/renameDevice', 'verb' => 'POST'],
        ['name' => 'page#setDeviceAlias', 'url' => '/setDeviceAlias', 'verb' => 'POST'],
        ['name' => 'page#reaffectDevice', 'url' => '/reaffectDevice', 'verb' => 'POST'],
        ['name' => 'page#setDeviceColor', 'url' => '/setDeviceColor', 'verb' => 'POST'],
        ['name' => 'log#logGet', 'url' => '/logGet/{token}/{devicename}', 'verb' => 'GET'],
        ['name' => 'log#logPost', 'url' => '/logPost/{token}/{devicename}', 'verb' => 'POST'],
        ['name' => 'log#logOsmand', 'url' => '/log/osmand/{token}/{devicename}', 'verb' => 'GET'],
        ['name' => 'log#logGpsloggerGet', 'url' => '/log/gpslogger/{token}/{devicename}', 'verb' => 'GET'],
        ['name' => 'log#logGpsloggerPost', 'url' => '/log/gpslogger/{token}/{devicename}', 'verb' => 'POST'],
        ['name' => 'log#logOwntracks', 'url' => '/log/owntracks/{token}/{devicename}', 'verb' => 'POST', 'defaults' => array('devicename' => '')],
        ['name' => 'log#logUlogger', 'url' => '/log/ulogger/{token}/{devicename}/client/index.php', 'verb' => 'POST'],
        ['name' => 'log#logTraccar', 'url' => '/log/traccar/{token}/{devicename}', 'verb' => 'POST', 'defaults' => array('devicename' => '')],
        ['name' => 'log#logOpengts', 'url' => '/log/opengts/{token}/{devicename}', 'verb' => 'GET', 'defaults' => array('devicename' => '')],
        ['name' => 'log#logOpengtsPost', 'url' => '/log/opengts/{token}/{devicename}', 'verb' => 'POST'],
        ['name' => 'page#track', 'url' => '/track', 'verb' => 'POST'],
        ['name' => 'page#publicWebLogTrack', 'url' => '/publicWebLogTrack', 'verb' => 'POST'],
        ['name' => 'page#publicViewTrack', 'url' => '/publicViewTrack', 'verb' => 'POST'],
        ['name' => 'page#setSessionPublic', 'url' => '/setSessionPublic', 'verb' => 'POST'],
        ['name' => 'page#setSessionAutoExport', 'url' => '/setSessionAutoExport', 'verb' => 'POST'],
        ['name' => 'page#setSessionAutoPurge', 'url' => '/setSessionAutoPurge', 'verb' => 'POST'],
        ['name' => 'page#addUserShare', 'url' => '/addUserShare', 'verb' => 'POST'],
        ['name' => 'page#deleteUserShare', 'url' => '/deleteUserShare', 'verb' => 'POST'],
        ['name' => 'page#addPublicShare', 'url' => '/addPublicShare', 'verb' => 'POST'],
        ['name' => 'page#deletePublicShare', 'url' => '/deletePublicShare', 'verb' => 'POST'],
        ['name' => 'page#setPublicShareDevice', 'url' => '/setPublicShareDevice', 'verb' => 'POST'],
        ['name' => 'page#setPublicShareLastOnly', 'url' => '/setPublicShareLastOnly', 'verb' => 'POST'],
        ['name' => 'page#setPublicShareGeofencify', 'url' => '/setPublicShareGeofencify', 'verb' => 'POST'],
        ['name' => 'page#addNameReservation', 'url' => '/addNameReservation', 'verb' => 'POST'],
        ['name' => 'page#deleteNameReservation', 'url' => '/deleteNameReservation', 'verb' => 'POST'],
        ['name' => 'page#addGeofence', 'url' => '/addGeofence', 'verb' => 'POST'],
        ['name' => 'page#deleteGeofence', 'url' => '/deleteGeofence', 'verb' => 'POST'],
        ['name' => 'page#export', 'url' => '/export', 'verb' => 'POST'],
        ['name' => 'page#deleteDevice', 'url' => '/deleteDevice', 'verb' => 'POST'],
        ['name' => 'page#getUserList', 'url' => '/getUserList', 'verb' => 'POST'],
        ['name' => 'utils#addTileServer', 'url' => '/addTileServer', 'verb' => 'POST'],
        ['name' => 'utils#deleteTileServer', 'url' => '/deleteTileServer', 'verb' => 'POST'],
        ['name' => 'utils#getOptionsValues', 'url' => '/getOptionsValues', 'verb' => 'POST'],
        ['name' => 'utils#saveOptionsValues', 'url' => '/saveOptionsValues', 'verb' => 'POST'],
    ]
];
