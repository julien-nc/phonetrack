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

class PageControllerTest extends \PHPUnit_Framework_TestCase {

	private $appName;
	private $request;
	private $contacts;

	private $container;
	private $app;

	private $controller;

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

        $this->controller = new PageController(
            $this->appName,
            $this->request,
            'test',
            $c->query('ServerContainer')->getUserFolder($c->query('UserId')),
            $c->query('ServerContainer')->getConfig(),
            $c->getServer()->getShareManager(),
            $c->getServer()->getAppManager(),
            $c->getServer()->getUserManager()
        );
	}

	public function tearDown() {
        $user = $this->container->getServer()->getUserManager()->get('test');
        $user->delete();
        $user = $this->container->getServer()->getUserManager()->get('test2');
        $user->delete();
    }

	public function testSession() {
        // CREATE SESSION
        $resp = $this->controller->createSession('testSession');

        $data = $resp->getData();
        $token = $data['token'];
        $done = $data['done'];

		$this->assertEquals($done, 1);

        // SHARE SESSION
        $resp = $this->controller->addUserShare($token, 'test2');

        $data = $resp->getData();
        $done = $data['done'];

		$this->assertEquals($done, 1);

        // ADD POINTS
        $resp = $this->controller->addPoint($token, 'testDev', 45.5, 3.4, 111, 456, 100, 80, 12, 'tests');

        $data = $resp->getData();
        $done = $data['done'];
        $pointid = $data['pointid'];
        $deviceid = $data['deviceid'];

		$this->assertEquals($done, 1);
		$this->assertEquals(intval($pointid) > 0, True);
		$this->assertEquals(intval($deviceid) > 0, True);

        $resp = $this->controller->addPoint($token, 'testDev', 45.6, 3.5, 200, 460, 100, 75, 14, 'tests');
        $resp = $this->controller->addPoint($token, 'testDev', 45.7, 3.6, 220, 470, 100, 70, 11, 'tests');

        // GET SESSIONS
        $resp = $this->controller->getSessions();

        $data = $resp->getData();
        $name = $data['sessions'][0][0];

		$this->assertEquals($name, 'testSession');

        // TRACK
        $sessions = array(array($token, 400, 1));
        $resp = $this->controller->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $pointList = $respSession[$token][$deviceid];

		$this->assertEquals(count($pointList), 3);

        //DELETE POINT
        $resp = $this->controller->deletePoint($token, $deviceid, $pointid);

        $data = $resp->getData();
        $done = $data['done'];

		$this->assertEquals($done, 1);

        // TRACK AFTER DELETE POINT
        $sessions = array(array($token, 400, 1));
        $resp = $this->controller->track($sessions);
        $data = $resp->getData();
        $respSession = $data['sessions'];
        $pointList = $respSession[$token][$deviceid];

		$this->assertEquals(count($pointList), 2);

        // DELETE SESSION
        $resp = $this->controller->deleteSession($token);

        $data = $resp->getData();
        $done = $data['done'];

		$this->assertEquals($done, 1);
	}

}
