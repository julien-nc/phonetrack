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
    private $logController;

    private $testSessionToken;
    private $testSessionToken2;

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
            $c->getServer()->getUserManager()
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
    }

    //public function testLog() {

    //}

    public function testPage() {
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

        // SHARE SESSION
        $resp = $this->pageController->addUserShare($token, 'test3');
        $resp = $this->pageController->addUserShare($token, 'test2');

        $data = $resp->getData();
        $done = $data['done'];

        $this->assertEquals($done, 1);

        // UNSHARE SESSION
        $resp = $this->pageController->deleteUserShare($token, 'test3');

        $data = $resp->getData();
        $done = $data['done'];

        $this->assertEquals($done, 1);

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

        // GET SESSIONS
        $resp = $this->pageController->getSessions();

        $data = $resp->getData();
        $name = $data['sessions'][0][0];

        $this->assertEquals($name, 'testSession');

        // CHECK SESSION IS SHARED WITH A USER
        $cond = ($data['sessions'][0][1] === $token and count($data['sessions'][0][4]) > 0 and $data['sessions'][0][4][0] === 'test2') or
                ($data['sessions'][1][1] === $token and count($data['sessions'][1][4]) > 0 and $data['sessions'][1][4][0] === 'test2');
        $this->assertEquals($cond, True);

        // TRACK
        $sessions = array(array($token, 400, 1));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $pointList = $respSession[$token][$deviceid];

        $this->assertEquals(count($pointList), 3);
        $this->assertEquals($pointList[2]['batterylevel'], 70);
        $lastPointID = $pointList[2]['id'];

        // UPDATE POINT
        $resp = $this->pageController->updatePoint($token, $deviceid, $lastPointID,
            45.11, 3.11, 210, 480, 99, 65, 10, 'tests_modif');

        $data = $resp->getData();
        $done = $data['done'];

        $this->assertEquals($done, 1);

        // TRACK AGAIN
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $pointList = $respSession[$token][$deviceid];

        $this->assertEquals(count($pointList), 3);
        $this->assertEquals($pointList[2]['batterylevel'], 65);
        $this->assertEquals($pointList[2]['useragent'], 'tests_modif');
        $this->assertEquals($pointList[2]['accuracy'], 99);
        $this->assertEquals($pointList[2]['timestamp'], 480);
        $this->assertEquals($pointList[2]['altitude'], 210);
        $this->assertEquals($pointList[2]['satellites'], 10);

        //DELETE POINT
        $resp = $this->pageController->deletePoint($token, $deviceid, $pointid);

        $data = $resp->getData();
        $done = $data['done'];

        $this->assertEquals($done, 1);

        // TRACK AFTER DELETE POINT
        $sessions = array(array($token, 400, 1));
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

        // get device name
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

        // get device name
        $sessions = array(array($token2, 400, 1));
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

        // get device color
        $sessions = array(array($token2, 400, 1));
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
        $sessions = array(array($sharetoken2, 400, 1));
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

        // CHECK PUBLIC VIEW TRACK ON PRIVATE SESSION
        $sessions = array(array($sharetoken2, 400, 1));
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
        $resp = $this->pageController->addPublicShare($token2);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);
        $publictoken1 = $data['sharetoken'];
        $this->assertEquals(count($publictoken1) > 0, True);

        $resp = $this->pageController->addPublicShare($token2);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);
        $publictoken2 = $data['sharetoken'];
        $this->assertEquals(count($publictoken2) > 0, True);

        // DELETE PUBLIC SHARE
        $resp = $this->pageController->deletePublicShare($token2, $publictoken2);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

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

        // PUBLIC VIEW TRACK FOR PUBLIC SHARE
        $sessions = array(array($publictoken1, 400, 1));
        $resp = $this->pageController->publicViewTrack($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $respNames = $data['names'];
        $respColors = $data['colors'];
        $pointList = $respSession[$publictoken1][$deviceid];

        $this->assertEquals(count($pointList), 2);

        // DELETE DEVICE
        $resp = $this->pageController->addPoint($token, 'delDev', 25.6, 2.5, 100, 560, 100, 35, 4, 'tests');
        $data = $resp->getData();
        $deldeviceid = $data['deviceid'];
        $resp = $this->pageController->addPoint($token, 'delDev', 25.7, 2.6, 120, 570, 100, 30, 11, 'tests');

        $sessions = array(array($token, 400, 1));
        $resp = $this->pageController->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $respNames = $data['names'];
        $respColors = $data['colors'];

        $cond = array_key_exists($token, $data['names']) and array_key_exists($deldeviceid, $data['names'][$token]);
        $this->assertEquals($cond, True);
        $this->assertEquals($data['names'][$token][$deldeviceid], 'delDev');

        $resp = $this->pageController->deleteDevice($token, $deldeviceid);
        $data = $resp->getData();
        $done = $data['done'];
        $this->assertEquals($done, 1);

        $sessions = array(array($token, 400, 1));
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

        // DELETE SESSION
        $resp = $this->pageController->deleteSession($token);

        $data = $resp->getData();
        $done = $data['done'];

        $this->assertEquals($done, 1);
    }

}
