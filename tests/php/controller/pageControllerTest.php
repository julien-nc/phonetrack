<?php
/**
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\PhoneTrack\Controller;

use \OCA\PhoneTrack\AppInfo\Application;

class PageNLogControllerTest extends \PHPUnit\Framework\TestCase {

    private $appName;
    private $request;
    private $contacts;

    private $container;
    private $app;

    private $pageController;
    private $pageController2;
    private $logController;
    private $utilsController;

    private $testSessionToken;
    private $testSessionToken2;
    private $testSessionToken3;
    private $testSessionToken4;
    private $testSessionToken5;

    public function setUp() {
        $this->appName = 'phonetrack';
        $this->request = $this->getMockBuilder('\OCP\IRequest')
            ->disableOriginalConstructor()
            ->getMock();
        $this->contacts = $this->getMockBuilder('OCP\Contacts\IManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->app = new Application();
        $this->container = $this->app->getContainer();
        $c = $this->container;

        // CREATE DUMMY USERS
        $c->getServer()->getUserManager()->createUser('test', 'T0T0T0');
        $c->getServer()->getUserManager()->createUser('test2', 'T0T0T0');
        $c->getServer()->getUserManager()->createUser('test3', 'T0T0T0');

        $this->pageController = new PageController(
            $this->appName,
            $this->request,
            'test',
            $c->query('ServerContainer')->getUserFolder('test'),
            $c->query('ServerContainer')->getConfig(),
            $c->getServer()->getShareManager(),
            $c->getServer()->getAppManager(),
            $c->getServer()->getUserManager(),
            $c->query('ServerContainer')->getLogger()
        );

        $this->pageController2 = new PageController(
            $this->appName,
            $this->request,
            'test2',
            $c->query('ServerContainer')->getUserFolder('test2'),
            $c->query('ServerContainer')->getConfig(),
            $c->getServer()->getShareManager(),
            $c->getServer()->getAppManager(),
            $c->getServer()->getUserManager(),
            $c->query('ServerContainer')->getLogger()
        );

        $this->logController = new LogController(
            $this->appName,
            $this->request,
            'test',
            $c->query('ServerContainer')->getUserFolder('test'),
            $c->query('ServerContainer')->getConfig(),
            $c->getServer()->getShareManager(),
            $c->getServer()->getAppManager()
        );

        $this->utilsController = new UtilsController(
            $this->appName,
            $this->request,
            'test',
            $c->query('ServerContainer')->getUserFolder('test'),
            $c->query('ServerContainer')->getConfig(),
            $c->getServer()->getAppManager()
        );
    }

    public function tearDown() {
        $user = $this->container->getServer()->getUserManager()->get('test');
        $user->delete();
        $user = $this->container->getServer()->getUserManager()->get('test2');
        $user->delete();
        $user = $this->container->getServer()->getUserManager()->get('test3');
        $user->delete();
        // in case there was a failure and session was not deleted
        $this->pageController->deleteSession($this->testSessionToken);
        $this->pageController->deleteSession($this->testSessionToken2);
        $this->pageController->deleteSession($this->testSessionToken3);
        $this->pageController->deleteSession($this->testSessionToken4);
        $this->pageController->deleteSession($this->testSessionToken5);
    }

    public function testUtils() {
        // DELETE OPTIONS VALUES
        $resp = $this->utilsController->deleteOptionsValues();
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // SET OPTIONS
        $resp = $this->utilsController->saveOptionsValues('{"lala": "lolo"}');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // GET OPTIONS
        $resp = $this->utilsController->getOptionsValues();
        $data = $resp->getData();
        $values = $data['values'];
        $this->assertEquals($values, '{"lala": "lolo"}');

        // ADD TILE SERVER
        $resp = $this->utilsController->deleteTileServer('serv', 'tile');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        $resp = $this->utilsController->addTileServer(
            'serv', 'https://tile.server/x/y/z', 'tile',
            '', '', '', 0.9, True,
            10, 16, 'owyeah'
        );
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        $resp = $this->utilsController->addTileServer(
            'serv', 'https://tile.server/x/y/z', 'tile',
            '', '', '', 0.9, True,
            10, 16, 'owyeah'
        );
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 0);

        $resp = $this->utilsController->deleteTileServer('serv', 'tile');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // SQL INJECTION
        $resp = $this->utilsController->deleteTileServer('serv', 'tile; DELETE FROM oc_phonetrack_options WHERE 1');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        $resp = $this->utilsController->getOptionsValues();
        $data = $resp->getData();
        $values = $data['values'];
        $this->assertEquals($values, '{"lala": "lolo"}');
    }

    public function testLog() {
        // CLEAR OPTIONS
        $resp = $this->utilsController->saveOptionsValues('');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // CREATE SESSION
        $resp = $this->pageController->createSession('logSession');
        $data = $resp->getData();
        $token = $data['token'];
        $this->testSessionToken4 = $token;
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // LOG
        $this->logController->logOsmand($token, 'dev1', 44.4, 3.33, 450, 60, 10, 200, 199);
        $this->logController->logGpsloggerGet($token, 'dev1', 44.5, 3.34, 460, 55, 10, 200, 198);
        $this->logController->logOwntracks($token, 'dev1', 'dev1', 44.6, 3.35, 197, 470, 200, 50);
        $this->logController->logUlogger($token, 'dev1', 'tid', 44.7, 3.36, 480, 200, 196, 'pwd', 'user', 'addpos');
        $this->logController->logTraccar($token, 'dev1', 'id', 44.6, 3.35, 470, 200, 195, 45);
        $gprmc = '$GPRMC,081836,A,3751.65,S,14507.36,E,000.0,360.0,130998,011.3,E*62';
        $this->logController->logOpengts($token, 'dev1', 'dev1', 'dev1', 'whateverthatis', '195', 40, $gprmc);
        $this->logController->logGpsloggerPost($token, 'dev1', 44.5, 3.34, 200, 490, 35, 10, 199);
        $this->logController->logGet($token, 'dev1', 44.5, 3.344, 499, 25, 10, 200, 198);

        $this->logController->logOpengtsPost($token, 'dev1', 44.5, 3.344, 499, 25, 10, 200);

        // get deviceid
        $sessions = array(array($token, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $this->assertEquals(count($respSession), 1);
        $deviceid = null;
        foreach ($respSession[$token] as $k => $v) {
            $deviceid = $k;
        }

        // save options
        $resp = $this->utilsController->saveOptionsValues('{"updateinterval":"45","linewidth":"4","colortheme":"bright","pointlinealpha":"0.8","pointradius":"8","autoexportpath":"/plop","viewmove":true,"autozoom":false,"showtime":false,"dragcheck":true,"tooltipshowaccuracy":true,"tooltipshowsatellites":true,"tooltipshowbattery":true,"tooltipshowelevation":true,"tooltipshowuseragent":true,"acccirclecheck":true,"tilelayer":"OpenStreetMap","showsidebar":true,"hourmin":"","minutemin":"","secondmin":"","hourmax":"","minutemax":"","secondmax":"","lastdays":"3","lasthours":"4","lastmins":"3","accuracymin":"","accuracymax":"","elevationmin":"","elevationmax":"","batterymin":"","batterymax":"","satellitesmin":"","satellitesmax":"","datemin":8000,"datemax":1516748400,"applyfilters":false,"activeSessions":{"'.$token.'":{"'.$deviceid.'":{"zoom":false,"line":true,"point":true},"2":{"zoom":false,"line":true,"point":true},"582":{"zoom":false,"line":true,"point":false}}}}');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // TRACK
        $sessions = array(array($token, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $this->assertEquals(count($respSession), 1);
        foreach ($respSession[$token] as $k => $v) {
            $pointList = $v;
            $this->assertEquals(count($pointList), 8);
            $this->assertEquals($pointList[0][7], 60);
        }

        // STRESS LOG
        // empty sessionid
        $this->logController->logOsmand('', 'dev1', 44.4, 3.33, 450, 60, 10, 200, 199);
        $resp = $this->pageController->getSessions();
        $data = $resp->getData();
        $this->assertEquals(count($data['sessions']), 1);
        $sessions = array(array($token, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $this->assertEquals(count($respSession), 1);
        foreach ($respSession[$token] as $k => $v) {
            $pointList = $v;
            $this->assertEquals(count($pointList), 8);
            $this->assertEquals($pointList[0][7], 60);
        }

        // empty lat
        $this->logController->logOsmand($token, 'dev1', '', 3.33, 450, 60, 10, 200, 199);
        $sessions = array(array($token, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $this->assertEquals(count($respSession), 1);
        foreach ($respSession[$token] as $k => $v) {
            $pointList = $v;
            $this->assertEquals(count($pointList), 8);
            $this->assertEquals($pointList[0][7], 60);
        }

        // empty lon
        $this->logController->logOsmand($token, 'dev1', 4.44, '', 450, 60, 10, 200, 199);
        $sessions = array(array($token, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $this->assertEquals(count($respSession), 1);
        foreach ($respSession[$token] as $k => $v) {
            $pointList = $v;
            $this->assertEquals(count($pointList), 8);
            $this->assertEquals($pointList[0][7], 60);
        }

        // empty timestamp
        $this->logController->logOsmand($token, 'dev1', 4.44, 3.33, '', 60, 10, 200, 199);
        $sessions = array(array($token, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $this->assertEquals(count($respSession), 1);
        foreach ($respSession[$token] as $k => $v) {
            $pointList = $v;
            $this->assertEquals(count($pointList), 8);
            $this->assertEquals($pointList[0][7], 60);
        }

        // empty battery, sat, acc, alt and too big timestamp
        $this->logController->logOsmand($token, 'dev1', 4.44, 3.33, 10000000001, '', '', '', '');
        $sessions = array(array($token, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $this->assertEquals(count($respSession), 1);
        foreach ($respSession[$token] as $k => $v) {
            $pointList = $v;
            $this->assertEquals(count($pointList), 9);
            $this->assertEquals($pointList[0][7], 60);
        }

        // empty user agent
        $this->logController->logPost($token, 'dev1', 4.44, 3.33, 100, 470, 60, 10, 200, '');
        $sessions = array(array($token, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $this->assertEquals(count($respSession), 1);
        foreach ($respSession[$token] as $k => $v) {
            $pointList = $v;
            $this->assertEquals(count($pointList), 10);
            $this->assertEquals($pointList[0][7], 60);
        }

        // wrong session and logGet
        $this->logController->logOsmand($token.'a', 'dev1', 44.4, 3.33, 450, 60, 10, 200, 199);
        $resp = $this->pageController->getSessions();
        $data = $resp->getData();
        $this->assertEquals(count($data['sessions']), 1);

        // SQL INJECTION
        // using device name
        $this->logController->logOsmand($token, 'dev1; DELETE FROM oc_phonetrack_points WHERE deviceid='.$deviceid.';', '44.9', 3.33, 450, 60, 10, 200, 199);
        $sessions = array(array($token, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $this->assertEquals(count($respSession), 1);
        foreach ($respSession[$token] as $k => $v) {
            if ($k === $deviceid) {
                $pointList = $v;
                $this->assertEquals(count($pointList), 10);
                $this->assertEquals($pointList[0][7], 60);
            }
        }

        // SQL INJECTION
        // with token
        $this->logController->logOsmand($token.'; DELETE FROM oc_phonetrack_points WHERE deviceid='.$deviceid.';', 'dev1', '44.9', 3.33, 450, 60, 10, 200, 199);
        $sessions = array(array($token, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $this->assertEquals(count($respSession), 1);
        foreach ($respSession[$token] as $k => $v) {
            if ($k === $deviceid) {
                $pointList = $v;
                $this->assertEquals(count($pointList), 10);
                $this->assertEquals($pointList[0][7], 60);
            }
        }

        // CHECK NAME RESERVATION
        $resp = $this->pageController->addNameReservation($token, 'resName');
        $data = $resp->getData();
        $done = $data['done'];
        $reservToken = $data['nametoken'];
        $this->assertEquals($done, 1);

        // then try to log, number of devices should still be 1
        $this->logController->logOsmand($token, 'resName', 4.44, 3.33, 500, 60, 10, 200, 199);
        $sessions = array(array($token, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $this->assertEquals(count($respSession[$token]), 2);

        // then try to log with name token, there should be two devices
        $this->logController->logOsmand($token, $reservToken, 4.44, 3.33, 500, 60, 10, 200, 199);
        $sessions = array(array($token, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $this->assertEquals(count($respSession[$token]), 3);

        // empty deviceid => log works, device name is 'unknown'
        $this->logController->logOsmand($token, '', 44.4, 3.33, 450, 60, 10, 200, 199);
        $sessions = array(array($token, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $this->assertEquals(count($respSession), 1);
        $this->assertEquals(count($respSession[$token]), 4);

        // no device name but one tid
        $this->logController->logOwntracks($token, '', 'dev1', 44.6, 3.35, 197, 470, 200, 50);

        // no device name but one tid
        $this->logController->logPost($token, 'dev1', 44.6, 3.35, 197, 470, 200, 50, 10, 'browser');

        // GPRMC
        $gprmc = '$GPRMC,081839,A,3751.65,S,14507.36,W,000.0,360.0,130998,011.3,E*62';
        $this->logController->logOpengts($token, 'dev1', 'dev1', 'dev1', 'whateverthatis', '195', 40, $gprmc);

    }

    public function testPage() {
        // CLEAR OPTIONS
        $resp = $this->utilsController->saveOptionsValues('');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // CREATE SESSION
        $resp = $this->pageController->createSession('testSession');

        $data = $resp->getData();
        $token = $data['token'];
        $this->testSessionToken = $token;
        $done = $data['done'];

        $this->assertEquals($done, 1);

        $resp = $this->pageController->createSession('otherSession');

        $data = $resp->getData();
        $token2 = $data['token'];
        $this->testSessionToken2 = $token2;
        $done = $data['done'];

        $this->assertEquals($done, 1);

        // AUTO EXPORT
        $resp = $this->pageController->setSessionAutoExport($token, 'monthly');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        $resp = $this->pageController->setSessionAutoExport($token.'a', 'monthly');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);

        // AUTO PURGE
        $resp = $this->pageController->setSessionAutoPurge($token, 'day');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        $resp = $this->pageController->setSessionAutoPurge($token.'a', 'monthly');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);

        // STRESS CREATE SESSION
        $resp = $this->pageController->createSession('testSession');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);
        $resp = $this->pageController->createSession('');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);

        // SHARE SESSION
        $resp = $this->pageController->addUserShare($token, 'test3');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);
        $resp = $this->pageController->addUserShare($token, 'test2');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // STRESS SHARE SESSION
        $resp = $this->pageController->addUserShare($token, 'test2doesnotexist');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 4);
        $resp = $this->pageController->addUserShare($token, '');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 4);
        $resp = $this->pageController->addUserShare('dummytoken', 'test2');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 3);
        $resp = $this->pageController->addUserShare('', 'test2');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 3);
        $resp = $this->pageController->addUserShare(null, 'test2');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 3);
        $resp = $this->pageController->addUserShare($token, 'test2');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);

        // UNSHARE SESSION
        $resp = $this->pageController->deleteUserShare($token, 'test3');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // STRESS UNSHARE SESSION
        $resp = $this->pageController->deleteUserShare($token, 'test3');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);
        $resp = $this->pageController->deleteUserShare($token, null);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);
        $resp = $this->pageController->deleteUserShare($token, '');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);
        $resp = $this->pageController->deleteUserShare($token, 'dummy');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);
        $resp = $this->pageController->deleteUserShare('dummytoken', 'test2');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 3);
        $resp = $this->pageController->deleteUserShare(null, 'test2');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 3);
        $resp = $this->pageController->deleteUserShare('', 'test2');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 3);

        // ADD POINTS
        $resp = $this->pageController->addPoint($token, 'testDev', 45.5, 3.4, 111, 456, 100, 80, 12, 'tests');
        $data = $resp->getData();
        $done = $data['done'];
        $pointid = $data['pointid'];
        $deviceid = $data['deviceid'];
        $this->assertEquals($done, 1);
        $this->assertEquals(intval($pointid) > 0, True);
        $this->assertEquals(intval($deviceid) > 0, True);

        $resp = $this->pageController->addPoint($token, 'testDev', 45.6, 3.5, 200, 460, 100, 75, 14, 'tests');
        $resp = $this->pageController->addPoint($token, 'testDev', 45.7, 3.6, 220, 470, 100, 70, 11, 'tests');

        // STRESS ADD POINT
        $resp = $this->pageController->addPoint($token, '', 45.5, 3.4, 111, 456, 100, 80, 12, 'tests');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);
        $resp = $this->pageController->addPoint('', '', 45.5, 3.4, 111, 456, 100, 80, 12, 'tests');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);
        $resp = $this->pageController->addPoint('dummytoken', 'testDev', 45.5, 3.4, 111, 456, 100, 80, 12, 'tests');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 3);

        // GET SESSIONS
        $resp = $this->pageController->getSessions();

        $data = $resp->getData();
        $name = $data['sessions'][0][0];

        $this->assertEquals($name, 'testSession');

        // CHECK SESSION IS SHARED WITH A USER
        $cond = ($data['sessions'][0][1] === $token and count($data['sessions'][0][4]) > 0 and $data['sessions'][0][4][0] === 'test2') or
                ($data['sessions'][1][1] === $token and count($data['sessions'][1][4]) > 0 and $data['sessions'][1][4][0] === 'test2');
        $this->assertEquals($cond, True);

        // save options
        $resp = $this->utilsController->saveOptionsValues('{"updateinterval":"45","linewidth":"4","colortheme":"bright","pointlinealpha":"0.8","pointradius":"8","autoexportpath":"/plop","viewmove":true,"autozoom":false,"showtime":false,"dragcheck":true,"tooltipshowaccuracy":true,"tooltipshowsatellites":true,"tooltipshowbattery":true,"tooltipshowelevation":true,"tooltipshowuseragent":true,"acccirclecheck":true,"tilelayer":"OpenStreetMap","showsidebar":true,"hourmin":"","minutemin":"","secondmin":"","hourmax":"","minutemax":"","secondmax":"","lastdays":"3","lasthours":"4","lastmins":"3","accuracymin":"","accuracymax":"","elevationmin":"","elevationmax":"","batterymin":"","batterymax":"","satellitesmin":"","satellitesmax":"","datemin":8000,"datemax":1516748400,"applyfilters":false,"activeSessions":{"'.$token.'":{"'.$deviceid.'":{"zoom":false,"line":true,"point":true},"2":{"zoom":false,"line":true,"point":true},"582":{"zoom":false,"line":true,"point":false}}}}');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // TRACK
        $sessions = array(array($token, array($deviceid => 400), array($deviceid => 1)));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $pointList = $respSession[$token][$deviceid];

        $this->assertEquals(count($pointList), 3);
        $this->assertEquals($pointList[2][7], 70);
        $lastPointID = $pointList[2][0];

        // no first point
        $sessions = array(array($token, array($deviceid => 400), null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $pointList = $respSession[$token][$deviceid];
        $this->assertEquals(count($pointList) > 0, True);

        // PUBLIC WEB LOG TRACK
        $sessions = array(array($token, array($deviceid => 400), null));
        $resp = $this->pageController->publicWebLogTrack($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $this->assertEquals(count($respSession), 1);

        $sessions = array(array($token, null, null));
        $resp = $this->pageController->publicWebLogTrack($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $this->assertEquals(count($respSession), 1);

        // STRESS TRACK
        $sessions = null;
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $respColors = $data['colors'];
        $respNames = $data['names'];
        $this->assertEquals(count($respSession), 0);
        $this->assertEquals(count($respColors), 0);
        $this->assertEquals(count($respNames), 0);

        $sessions = array(array('', null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $respColors = $data['colors'];
        $respNames = $data['names'];
        $this->assertEquals(count($respSession), 0);
        $this->assertEquals(count($respColors), 0);
        $this->assertEquals(count($respNames), 0);

        $sessions = array(array($token, array($deviceid => 1000), array($deviceid => 1)));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $respColors = $data['colors'];
        $respNames = $data['names'];
        $this->assertEquals(count($respSession), 1);
        $this->assertEquals(count($respColors), 0);
        $this->assertEquals(count($respNames), 0);
        $this->assertEquals(count($respSession[$token]), 0);

        // UPDATE POINT
        $resp = $this->pageController->updatePoint($token, $deviceid, $lastPointID,
            45.11, 3.11, 210, 480, 99, 65, 10, 'tests_modif');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // STRESS UPDATE POINT
        $resp = $this->pageController->updatePoint($token, $deviceid, 666,
            45.11, 3.11, 210, 480, 99, 65, 10, 'tests_modif');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);

        $resp = $this->pageController->updatePoint($token, 666, $lastPointID,
            45.11, 3.11, 210, 480, 99, 65, 10, 'tests_modif');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 3);

        $resp = $this->pageController->updatePoint('dumdum', $deviceid, $lastPointID,
            45.11, 3.11, 210, 480, 99, 65, 10, 'tests_modif');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 4);

        // TRACK AGAIN
        $sessions = array(array($token, array($deviceid => 400), array($deviceid => 1)));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $pointList = $respSession[$token][$deviceid];

        $this->assertEquals(count($pointList), 3);
        $this->assertEquals($pointList[2][7], 65);
        $this->assertEquals($pointList[2][8], 'tests_modif');
        $this->assertEquals($pointList[2][4], 99);
        $this->assertEquals($pointList[2][3], 480);
        $this->assertEquals($pointList[2][6], 210);
        $this->assertEquals($pointList[2][5], 10);

        //DELETE POINT
        $resp = $this->pageController->deletePoints($token, $deviceid, array($pointid));
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // STRESS DELETE POINT
        $resp = $this->pageController->deletePoints($token, $deviceid, array(666));
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        $resp = $this->pageController->deletePoints($token, $deviceid, array());
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);

        $resp = $this->pageController->deletePoints($token, 666, array($pointid));
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 3);

        $resp = $this->pageController->deletePoints('dumdum', $deviceid, array($pointid));
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 4);

        // TRACK AFTER DELETE POINT
        $sessions = array(array($token, array($deviceid => 400), array($deviceid => 1)));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $pointList = $respSession[$token][$deviceid];

        $this->assertEquals(count($pointList), 2);

        // RENAME SESSION
        $resp = $this->pageController->renameSession($token, 'renamedTestSession');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // STRESS RENAME SESSION
        $resp = $this->pageController->renameSession('dumdum', 'yeyeah');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);

        $resp = $this->pageController->renameSession($token, '');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 3);

        $resp = $this->pageController->renameSession($token, null);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 3);

        // GET SESSIONS TO CHECK NAME
        $resp = $this->pageController->getSessions();

        $data = $resp->getData();
        $name = $data['sessions'][0][0];

        $this->assertEquals($name, 'renamedTestSession');

        // RENAME DEVICE
        $resp = $this->pageController->renameDevice($token, $deviceid, 'renamedTestDev');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // STRESS RENAME DEVICE
        $resp = $this->pageController->renameDevice($token, 666, 'renamedTestDev');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);

        $resp = $this->pageController->renameDevice('dumdum', $deviceid, 'renamedTestDev');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 3);

        $resp = $this->pageController->renameDevice($token, $deviceid, '');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 4);

        // get device name
        $sessions = array(array($token, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $respNames = $data['names'];

        $this->assertEquals($respNames[$token][$deviceid], 'renamedTestDev');

        // REAFFECT DEVICE
        $resp = $this->pageController->reaffectDevice($token, $deviceid, $token2);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // STRESS REAFFECT DEVICE
        $resp = $this->pageController->reaffectDevice('dumdum', $deviceid, $token2);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);

        $resp = $this->pageController->reaffectDevice($token, 666, $token2);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 4);

        $resp = $this->pageController->reaffectDevice($token, $deviceid, 'dumdum');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 5);

        // create session with a device with same name
        $resp = $this->pageController->createSession('stressReaffect');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);
        $stressReafToken = $data['token'];
        $this->testSessionToken3 = $stressReafToken;
        $resp = $this->pageController->addPoint($stressReafToken, 'renamedTestDev', 25.6, 2.5, 100, 560, 100, 35, 4, 'testsReaf');

        $resp = $this->pageController->reaffectDevice($token2, $deviceid, $stressReafToken);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 3);

        $resp = $this->pageController->deleteSession($stressReafToken);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // get device name to check reaffect
        $sessions = array(array($token2, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $respNames = $data['names'];

        $this->assertEquals($respNames[$token2][$deviceid], 'renamedTestDev');

        // SET DEVICE COLOR
        $resp = $this->pageController->setDeviceColor($token2, $deviceid, '#96ff00');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // STRESS SET DEVICE COLOR
        $resp = $this->pageController->setDeviceColor('dumdum', $deviceid, '#96ff00');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);

        $resp = $this->pageController->setDeviceColor($token2, 666, '#96ff00');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 3);

        // get device color
        $sessions = array(array($token2, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $respNames = $data['names'];
        $respColors = $data['colors'];

        $this->assertEquals($respColors[$token2][$deviceid], '#96ff00');

        // TRACK PUBLIC SESSION
        // get second session's public token
        $resp = $this->pageController->getSessions();

        $data = $resp->getData();
        $sharetoken2 = null;
        foreach ($data['sessions'] as $s) {
            $name = $s[0];
            if ($name == 'otherSession') {
                $sharetoken2 = $s[2];
            }
        }

        $this->assertEquals(($sharetoken2 !== null), True);

        // PUBLIC VIEW TRACK
        $sessions = array(array($sharetoken2, null, null));
        $resp = $this->pageController->publicViewTrack($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $respNames = $data['names'];
        $respColors = $data['colors'];
        $pointList = $respSession[$sharetoken2][$deviceid];

        $this->assertEquals(count($pointList), 2);

        // API
        $resp = $this->pageController->APIgetLastPositions($sharetoken2);
        $data = $resp->getData();

        $this->assertEquals((count($data[$sharetoken2]) > 0), True);
        $this->assertEquals($data[$sharetoken2]['renamedTestDev']['timestamp'], 480);

        // SET SESSION PRIVATE
        $resp = $this->pageController->setSessionPublic($token2, 0);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // STRESS SET SESSION PRIVATE
        $resp = $this->pageController->setSessionPublic('dumdum', 0);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);

        $resp = $this->pageController->setSessionPublic($token2, 33);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 3);

        // CHECK PUBLIC VIEW TRACK ON PRIVATE SESSION
        $sessions = array(array($sharetoken2, null, null));
        $resp = $this->pageController->publicViewTrack($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $respNames = $data['names'];
        $respColors = $data['colors'];

        $this->assertEquals(count($respSession), 0);

        // API
        $resp = $this->pageController->APIgetLastPositions($sharetoken2);
        $data = $resp->getData();

        $this->assertEquals((count($data) === 0), True);

        // ADD PUBLIC SHARE
        $resp = $this->pageController->addPublicShare($token2.'a');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 3);

        $resp = $this->pageController->addPublicShare($token2);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);
        $publictoken1 = $data['sharetoken'];
        $this->assertEquals(count($publictoken1) > 0, True);

        // SET device restriction for this public share
        $resp = $this->pageController->setPublicShareDevice($token2, $publictoken1, 'plop');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        $resp = $this->pageController->setPublicShareDevice($token2.'a', $publictoken1, 'plop2');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);

        $resp = $this->pageController->setPublicShareDevice($token2, $publictoken1, '');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // watch this public share
        $resp = $this->pageController->publicSessionWatch($publictoken1);

        $resp = $this->pageController->addPublicShare($token2);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);
        $publictoken2 = $data['sharetoken'];
        $this->assertEquals(count($publictoken2) > 0, True);

        $resp = $this->utilsController->saveOptionsValues('{"updateinterval":"45","linewidth":"4","colortheme":"bright","pointlinealpha":"0.8","pointradius":"8","autoexportpath":"/plop","viewmove":true,"autozoom":false,"showtime":false,"dragcheck":true,"tooltipshowaccuracy":true,"tooltipshowsatellites":true,"tooltipshowbattery":true,"tooltipshowelevation":true,"tooltipshowuseragent":true,"acccirclecheck":true,"tilelayer":"OpenStreetMap","showsidebar":true,"hourmin":"","minutemin":"","secondmin":"","hourmax":"","minutemax":"","secondmax":"","lastdays":"3","lasthours":"4","lastmins":"3","accuracymin":"","accuracymax":"","elevationmin":"","elevationmax":"","batterymin":"","batterymax":"","satellitesmin":"","satellitesmax":"","datemin":8000,"datemax":1516748400,"applyfilters":true,"activeSessions":{"9500c72c6825c160bab732df219dec6a":{"1":{"zoom":false,"line":true,"point":true},"2":{"zoom":false,"line":true,"point":true},"582":{"zoom":false,"line":true,"point":false}}}}');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // to improve coverage, add share when there are filters
        $resp = $this->pageController->addPublicShare($token2);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);
        $publictoken3 = $data['sharetoken'];
        $this->assertEquals(count($publictoken3) > 0, True);

        $resp = $this->utilsController->saveOptionsValues('{}');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // DELETE PUBLIC SHARE
        $resp = $this->pageController->deletePublicShare($token2, $publictoken2);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        $resp = $this->pageController->deletePublicShare($token2.'a', $publictoken2);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 3);

        // CHECK PUBLIC SHARE
        $resp = $this->pageController->getSessions();

        $data = $resp->getData();
        $checkpublictoken = null;
        foreach ($data['sessions'] as $s) {
            $name = $s[0];
            if ($name == 'otherSession') {
                if (count($s[6]) > 0) {
                    $checkpublictoken = $s[6][0]['token'];
                }
            }
        }
        $this->assertEquals($checkpublictoken === $publictoken1, True);

        // for coverage of publicViewTrack
        $resp = $this->pageController->addNameReservation($token2, 'plop');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // PUBLIC VIEW TRACK FOR PUBLIC SHARE
        $sessions = array(array($publictoken1, array($deviceid=>10), null));
        $resp = $this->pageController->publicViewTrack($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $respNames = $data['names'];
        $respColors = $data['colors'];
        $pointList = $respSession[$publictoken1][$deviceid];
        $this->assertEquals(count($pointList), 2);

        $resp = $this->pageController->setPublicShareDevice($token2, $publictoken1, 'renamedTestDev');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        $sessions = array(array($publictoken1, array($deviceid=>10), null));
        $resp = $this->pageController->publicViewTrack($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $respNames = $data['names'];
        $respColors = $data['colors'];
        $pointList = $respSession[$publictoken1][$deviceid];
        $this->assertEquals(count($pointList), 2);

        $resp = $this->pageController->setPublicShareDevice($token2, $publictoken1, '');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // DELETE DEVICE
        // create a device
        $resp = $this->pageController->addPoint($token, 'delDev', 25.6, 2.5, 100, 560, 100, 35, 4, 'tests');
        $data = $resp->getData();
        $deldeviceid = $data['deviceid'];
        $resp = $this->pageController->addPoint($token, 'delDev', 25.7, 2.6, 120, 570, 100, 30, 11, 'tests');

        // get sessions to check device is there
        $sessions = array(array($token, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $respNames = $data['names'];
        $respColors = $data['colors'];

        $cond = array_key_exists($token, $data['names']) and array_key_exists($deldeviceid, $data['names'][$token]);
        $this->assertEquals($cond, True);
        $this->assertEquals($data['names'][$token][$deldeviceid], 'delDev');

        // actually delete
        $resp = $this->pageController->deleteDevice($token, $deldeviceid);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // stress delete
        $resp = $this->pageController->deleteDevice('dumdum', $deldeviceid);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);
        $resp = $this->pageController->deleteDevice($token, 666);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 3);

        // check if the device is gone
        $sessions = array(array($token, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $respNames = $data['names'];
        $respColors = $data['colors'];

        $cond = (!array_key_exists($token, $data['names'])) or (!array_key_exists($deldeviceid, $data['names'][$token]));
        $this->assertEquals($cond, True);

        // NAME RESERVATION
        $resp = $this->pageController->addNameReservation($token, 'resName');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // reserved name should not be given
        $sessions = array(array($token, null, null));
        $resp = $this->pageController->publicWebLogTrack($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $respNames = $data['names'];
        $this->assertEquals(count($respSession), 1);
        $this->assertEquals(array_key_exists($token, $respNames), True);
        $this->assertEquals(in_array('resName', $respNames[$token]), False);

        // STRESS NAME RESERVATION
        $resp = $this->pageController->addNameReservation($token, '');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 4);

        $resp = $this->pageController->addNameReservation('dumdum', 'lala');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 3);

        $resp = $this->pageController->addNameReservation($token, 'resName');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);

        // CHECK NAME RESERVATION
        $resp = $this->pageController->getSessions();

        $data = $resp->getData();
        $reservedList = null;
        foreach ($data['sessions'] as $s) {
            $name = $s[0];
            if ($name == 'renamedTestSession') {
                $reservedList = $s[5];
            }
        }

        $cond = ($reservedList !== null and count($reservedList) > 0 and $reservedList[0]['name'] === 'resName');
        $this->assertEquals($cond, True);

        // REMOVE NAME RESERVATION
        $resp = $this->pageController->deleteNameReservation($token, 'resName');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // STRESS REMOVE NAME RESERVATION
        $resp = $this->pageController->deleteNameReservation($token, '');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 5);

        $resp = $this->pageController->deleteNameReservation('dumdum', 'resName');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 4);

        $resp = $this->pageController->deleteNameReservation($token, 'idontexist');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);

        $resp = $this->pageController->deleteNameReservation($token, 'resName');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 3);

        // CHECK REMOVE NAME RESERVATION
        $resp = $this->pageController->getSessions();

        $data = $resp->getData();
        $reservedList = null;
        foreach ($data['sessions'] as $s) {
            $name = $s[0];
            if ($name == 'renamedTestSession') {
                $reservedList = $s[5];
            }
        }

        $cond = ($reservedList !== null and count($reservedList) === 0);
        $this->assertEquals($cond, True);

        // CREATE SESSION for user2 and share it with user1
        $resp = $this->pageController2->createSession('super');
        $data = $resp->getData();
        $tokenu2 = $data['token'];
        $this->testSessionToken5 = $tokenu2;
        $done = $data['done'];
        $this->assertEquals($done, 1);

        $resp = $this->pageController2->addUserShare($tokenu2, 'test');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        $resp = $this->pageController->getSessions();
        $data = $resp->getData();
        $this->assertEquals(count($data['sessions']), 3);

        // find share token of shared session
        $sname = '';
        $stoken = '';
        foreach ($data['sessions'] as $ses) {
            if ($ses[0] === 'super') {
                $sname = $ses[0];
                $stoken = $ses[1];
            }
        }
        $this->assertEquals($stoken === '', False);

        // TRACK AND FIND SHARED SESSION
        $sessions = array(array($stoken, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $this->assertEquals(count($respSession), 1);

        // DELETE SHARED SESSION

        $resp = $this->pageController2->deleteSession($tokenu2);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        $resp = $this->pageController->getSessions();
        $data = $resp->getData();
        $this->assertEquals(count($data['sessions']), 2);

        // OPTIONS
        $resp = $this->utilsController->saveOptionsValues('{"updateinterval":"45","linewidth":"4","colortheme":"bright","pointlinealpha":"0.8","pointradius":"8","autoexportpath":"/plop","viewmove":true,"autozoom":false,"showtime":false,"dragcheck":true,"tooltipshowaccuracy":true,"tooltipshowsatellites":true,"tooltipshowbattery":true,"tooltipshowelevation":true,"tooltipshowuseragent":true,"acccirclecheck":true,"tilelayer":"OpenStreetMap","showsidebar":true,"hourmin":"","minutemin":"","secondmin":"","hourmax":"","minutemax":"","secondmax":"","lastdays":"3","lasthours":"","lastmins":"","accuracymin":"","accuracymax":"","elevationmin":"","elevationmax":"","batterymin":"","batterymax":"","satellitesmin":"","satellitesmax":"","datemin":1515798000,"datemax":1516748400,"applyfilters":true,"activeSessions":{"9500c72c6825c160bab732df219dec6a":{"1":{"zoom":false,"line":true,"point":true},"2":{"zoom":false,"line":true,"point":true},"582":{"zoom":false,"line":true,"point":false}}}}');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        $sessions = array(array($token, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $this->assertEquals(count($respSession), 1);

        $resp = $this->utilsController->saveOptionsValues('{"updateinterval":"45","linewidth":"4","colortheme":"bright","pointlinealpha":"0.8","pointradius":"8","autoexportpath":"/plop","viewmove":true,"autozoom":false,"showtime":false,"dragcheck":true,"tooltipshowaccuracy":true,"tooltipshowsatellites":true,"tooltipshowbattery":true,"tooltipshowelevation":true,"tooltipshowuseragent":true,"acccirclecheck":true,"tilelayer":"OpenStreetMap","showsidebar":true,"hourmin":"","minutemin":"","secondmin":"","hourmax":"","minutemax":"","secondmax":"","lastdays":"","lasthours":"","lastmins":"","accuracymin":"","accuracymax":"","elevationmin":"","elevationmax":"","batterymin":"","batterymax":"","satellitesmin":"","satellitesmax":"","datemin":"","datemax":1516748400,"applyfilters":true,"activeSessions":{"9500c72c6825c160bab732df219dec6a":{"1":{"zoom":false,"line":true,"point":true},"2":{"zoom":false,"line":true,"point":true},"582":{"zoom":false,"line":true,"point":false}}}}');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        $sessions = array(array($token, null, null));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $this->assertEquals(count($respSession), 1);

        $resp = $this->utilsController->saveOptionsValues('');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // PUBLIC VIEW PAGE
        $resp = $this->pageController->getSessions();
        $data = $resp->getData();
        $publicviewtoken = $data['sessions'][0][2];

        $resp = $this->pageController->publicSessionWatch('');
        $this->assertEquals(is_string($resp), True);
        $resp = $this->pageController->publicSessionWatch('blabla');
        $this->assertEquals(is_string($resp), True);

        $resp = $this->pageController->publicSessionWatch($publicviewtoken);

        // COVERAGE OF addNameReservation
        $resp = $this->pageController->addPoint($token, 'futurRes', 45.5, 3.4, '', 10000000001, '', '', '', '');
        $resp = $this->pageController->addPoint($token, 'futurRes', 45.5, 3.4, '', 10000000001, '', '', '', 'browser');
        $resp = $this->pageController->addPoint($token, 'futurRes', '', 3.4, '', 10000000001, '', '', '', 'browser');
        $resp = $this->pageController->addNameReservation($token, 'futurRes');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // DELETE SESSION
        $resp = $this->pageController->deleteSession($token);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // STRESS DELETE SESSION
        $resp = $this->pageController->deleteSession('dumdum');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);

        $resp = $this->pageController->deleteSession(null);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);

        $resp = $this->pageController->deleteSession('');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 2);

        // JUST to increase coverage
        $resp = $this->utilsController->addTileServer(
            'serv', 'https://tile.server/x/y/z', 'tile',
            '', '', '', 0.9, True,
            10, 16, 'owyeah'
        );
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // INDEX
        $resp = $this->pageController->index();

        $resp = $this->utilsController->deleteTileServer('serv', 'tile');
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        // PUBLIC WEB LOG with non existent session
        $resp = $this->pageController->publicWebLog('', '');
        $this->assertEquals(is_string($resp), True);
    }

}
