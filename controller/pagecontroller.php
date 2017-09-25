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

namespace OCA\PhoneTrack\Controller;

use OCP\App\IAppManager;

use OCP\IURLGenerator;
use OCP\IConfig;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

class PageController extends Controller {

    private $userId;
    private $userfolder;
    private $config;
    private $appVersion;
    private $userAbsoluteDataPath;
    private $shareManager;
    private $dbconnection;
    private $dbtype;
    private $dbdblquotes;
    private $appPath;
    private $defaultDeviceId;

    public function __construct($AppName, IRequest $request, $UserId,
                                $userfolder, $config, $shareManager, IAppManager $appManager){
        parent::__construct($AppName, $request);
        $this->appVersion = $config->getAppValue('phonetrack', 'installed_version');
        // just to keep Owncloud compatibility
        // the first case : Nextcloud
        // else : Owncloud
        if (method_exists($appManager, 'getAppPath')){
            $this->appPath = $appManager->getAppPath('phonetrack');
        }
        else {
            $this->appPath = \OC_App::getAppPath('phonetrack');
            // even dirtier
            //$this->appPath = getcwd().'/apps/phonetrack';
        }
        $this->userId = $UserId;
        $this->dbtype = $config->getSystemValue('dbtype');
        // IConfig object
        $this->config = $config;

        if ($this->dbtype === 'pgsql'){
            $this->dbdblquotes = '"';
        }
        else{
            $this->dbdblquotes = '';
        }
        $this->dbconnection = \OC::$server->getDatabaseConnection();
        if ($UserId !== '' and $userfolder !== null){
            // path of user files folder relative to DATA folder
            $this->userfolder = $userfolder;
            // absolute path to user files folder
            $this->userAbsoluteDataPath =
                $this->config->getSystemValue('datadirectory').
                rtrim($this->userfolder->getFullPath(''), '/');
        }
        //$this->shareManager = \OC::$server->getShareManager();
        $this->shareManager = $shareManager;
        $this->defaultDeviceId = ['yourname', 'deviceid'];
    }

    /*
     * quote and choose string escape function depending on database used
     */
    private function db_quote_escape_string($str){
        return $this->dbconnection->quote($str);
    }

    private function getUserTileServers($type){
        // custom tile servers management
        $sqlts = 'SELECT servername, type, url, layers, version, format, opacity, transparent, minzoom, maxzoom, attribution FROM *PREFIX*phonetrack_tileserver ';
        $sqlts .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
        $sqlts .= 'AND type='.$this->db_quote_escape_string($type).';';
        $req = $this->dbconnection->prepare($sqlts);
        $req->execute();
        $tss = Array();
        while ($row = $req->fetch()){
            $tss[$row["servername"]] = Array();
            foreach (Array('servername', 'type', 'url', 'layers', 'version', 'format', 'opacity', 'transparent', 'minzoom', 'maxzoom', 'attribution') as $field) {
                $tss[$row['servername']][$field] = $row[$field];
            }
        }
        $req->closeCursor();
        return $tss;
    }

    /**
     * Welcome page.
     * Get session list
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index() {
        $userFolder = \OC::$server->getUserFolder();
        $userfolder_path = $userFolder->getPath();

        $tss = $this->getUserTileServers('tile');
        $oss = $this->getUserTileServers('overlay');
        $tssw = $this->getUserTileServers('tilewms');
        $ossw = $this->getUserTileServers('overlaywms');

        // PARAMS to view

        require_once('tileservers.php');
        $params = [
            'username'=>$this->userId,
			'basetileservers'=>$baseTileServers,
			'usertileservers'=>$tss,
			'useroverlayservers'=>$oss,
			'usertileserverswms'=>$tssw,
			'useroverlayserverswms'=>$ossw,
            'publicsessionname'=>'',
            'phonetrack_version'=>$this->appVersion
        ];
        $response = new TemplateResponse('phonetrack', 'main', $params);
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedChildSrcDomain('*')
            ->addAllowedObjectDomain('*')
            ->addAllowedScriptDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     *
     * get sessions owned by and shared with current user
     */
    public function getSessions() {
        $sessions = array();
        // sessions owned by current user
        $sqlget = 'SELECT name, token, publicviewtoken, public FROM *PREFIX*phonetrack_sessions ';
        $sqlget .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            $dbtoken = $row['token'];
            $sharedWith = $this->getSessionShares($dbtoken);
            $dbpublicviewtoken = $row['publicviewtoken'];
            $dbpublic = $row['public'];
            array_push($sessions, array($dbname, $dbtoken, $dbpublicviewtoken, $dbpublic, $sharedWith));
        }
        $req->closeCursor();

        // sessions shared with current user
        $sqlgetshares = 'SELECT sessionid, sharetoken FROM *PREFIX*phonetrack_shares ';
        $sqlgetshares .= 'WHERE username=\''.$this->userId.'\' ';
        $req = $this->dbconnection->prepare($sqlgetshares);
        $req->execute();
        while ($row = $req->fetch()){
            $dbsessionid = $row['sessionid'];
            $dbsharetoken = $row['sharetoken'];
            $sessionInfo = $this->getSessionInfo($dbsessionid);
            $dbname = $sessionInfo['name'];
            $dbuser = $sessionInfo['user'];
            array_push($sessions, array($dbname, $dbsharetoken, $dbuser));
        }
        $req->closeCursor();

        $response = new DataResponse(
            [
                'sessions'=>$sessions
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    private function getSessionInfo($sessionid) {
        $dbname = null;
        $sqlget = 'SELECT name, '.$this->dbdblquotes.'user'.$this->dbdblquotes.' FROM *PREFIX*phonetrack_sessions ';
        $sqlget .= 'WHERE token='.$this->db_quote_escape_string($sessionid).';';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            $dbuser = $row['user'];
        }
        $req->closeCursor();

        return ['user'=>$dbuser, 'name'=>$dbname];
    }



    private function getSessionShares($sessionid) {
        $sharedWith = [];
        $sqlchk = 'SELECT username FROM *PREFIX*phonetrack_shares ';
        $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($sessionid).';';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbusername = null;
        while ($row = $req->fetch()){
            array_push($sharedWith, $row['username']);
        }
        $req->closeCursor();

        return $sharedWith;
    }

    /**
     * @NoAdminRequired
     */
    public function createSession($name) {
        $token = '';
        // check if session name is not already used
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ';
        $sqlchk .= 'AND name='.$this->db_quote_escape_string($name).' ';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbname = null;
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            break;
        }
        $req->closeCursor();

        if ($dbname === null) {
            // determine token
            $token = md5($this->userId.$name.rand());
            $publicviewtoken = md5($this->userId.$name.rand());

            // insert
            $sql = 'INSERT INTO *PREFIX*phonetrack_sessions';
            $sql .= ' ('.$this->dbdblquotes.'user'.$this->dbdblquotes.', name, token, publicviewtoken, public) ';
            $sql .= 'VALUES (\''.$this->userId.'\',';
            $sql .= $this->db_quote_escape_string($name).',';
            $sql .= $this->db_quote_escape_string($token).',';
            $sql .= $this->db_quote_escape_string($publicviewtoken).',';
            $sql .= $this->db_quote_escape_string('1').');';
            $req = $this->dbconnection->prepare($sql);
            $req->execute();
            $req->closeCursor();

            $ok = 1;
        }
        else {
            $ok = 2;
        }

        $response = new DataResponse(
            [
                'done'=>$ok,
                'token'=>$token,
                'publicviewtoken'=>$publicviewtoken
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function deleteSession($token) {
        // check if session exists
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ';
        $sqlchk .= 'AND token='.$this->db_quote_escape_string($token).' ';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbname = null;
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            break;
        }
        $req->closeCursor();

        if ($dbname !== null) {
            $sqldel = 'DELETE FROM *PREFIX*phonetrack_sessions ';
            $sqldel .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
            $sqldel .= 'AND token='.$this->db_quote_escape_string($token).';';
            $req = $this->dbconnection->prepare($sqldel);
            $req->execute();
            $req->closeCursor();

            $sqldel = 'DELETE FROM *PREFIX*phonetrack_points ';
            $sqldel .= 'WHERE sessionid='.$this->db_quote_escape_string($token).';';
            $req = $this->dbconnection->prepare($sqldel);
            $req->execute();
            $req->closeCursor();

            $sqldel = 'DELETE FROM *PREFIX*phonetrack_shares ';
            $sqldel .= 'WHERE sessionid='.$this->db_quote_escape_string($token).';';
            $req = $this->dbconnection->prepare($sqldel);
            $req->execute();
            $req->closeCursor();

            $ok = 1;
        }
        else {
            $ok = 2;
        }

        $response = new DataResponse(
            [
                'done'=>$ok,
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function deletePoint($token, $deviceid, $pointid) {
        // check if session exists
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ';
        $sqlchk .= 'AND token='.$this->db_quote_escape_string($token).' ';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbname = null;
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            break;
        }
        $req->closeCursor();

        if ($dbname !== null) {
            // check if point exists
            $sqlchk = 'SELECT id FROM *PREFIX*phonetrack_points ';
            $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
            $sqlchk .= 'AND deviceid='.$this->db_quote_escape_string($deviceid).' ';
            $sqlchk .= 'AND id='.$this->db_quote_escape_string($pointid).' ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbid = null;
            while ($row = $req->fetch()){
                $dbid = $row['id'];
                break;
            }
            $req->closeCursor();

            if ($dbid !== null) {
                $sqldel = 'DELETE FROM *PREFIX*phonetrack_points ';
                $sqldel .= 'WHERE id='.$this->db_quote_escape_string($dbid).';';
                $req = $this->dbconnection->prepare($sqldel);
                $req->execute();
                $req->closeCursor();

                $ok = 1;
            }
            else {
                $ok = 2;
            }
        }
        else {
            $ok = 2;
        }

        $response = new DataResponse(
            [
                'done'=>$ok,
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function updatePoint($token, $deviceid, $pointid,
        $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, $useragent) {
        // check if session exists
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ';
        $sqlchk .= 'AND token='.$this->db_quote_escape_string($token).' ';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbname = null;
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            break;
        }
        $req->closeCursor();

        if ($dbname !== null) {
            // check if point exists
            $sqlchk = 'SELECT id FROM *PREFIX*phonetrack_points ';
            $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
            $sqlchk .= 'AND deviceid='.$this->db_quote_escape_string($deviceid).' ';
            $sqlchk .= 'AND id='.$this->db_quote_escape_string($pointid).' ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbid = null;
            while ($row = $req->fetch()){
                $dbid = $row['id'];
                break;
            }
            $req->closeCursor();

            if ($dbid !== null) {
                $sqlupd = 'UPDATE *PREFIX*phonetrack_points SET';
                $sqlupd .= ' lat='.$this->db_quote_escape_string($lat).' ';
                $sqlupd .= ', lon='.$this->db_quote_escape_string($lon).' ';
                $sqlupd .= ', altitude='.$this->db_quote_escape_string($alt).' ';
                $sqlupd .= ', timestamp='.$this->db_quote_escape_string($timestamp).' ';
                $sqlupd .= ', accuracy='.$this->db_quote_escape_string($acc).' ';
                $sqlupd .= ', batterylevel='.$this->db_quote_escape_string($bat).' ';
                $sqlupd .= ', satellites='.$this->db_quote_escape_string($sat).' ';
                $sqlupd .= ', useragent='.$this->db_quote_escape_string($useragent).' ';
                $sqlupd .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
                $sqlupd .= 'AND deviceid='.$this->db_quote_escape_string($deviceid).' ';
                $sqlupd .= 'AND id='.$this->db_quote_escape_string($pointid).';';
                $req = $this->dbconnection->prepare($sqlupd);
                $req->execute();
                $req->closeCursor();

                $ok = 1;
            }
            else {
                $ok = 2;
            }
        }
        else {
            $ok = 2;
        }

        $response = new DataResponse(
            [
                'done'=>$ok,
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function setSessionPublic($token, $public) {
        // check if session exists
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ';
        $sqlchk .= 'AND token='.$this->db_quote_escape_string($token).' ';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbname = null;
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            break;
        }
        $req->closeCursor();

        if ($dbname !== null) {
            $sqlren = 'UPDATE *PREFIX*phonetrack_sessions ';
            $sqlren .= 'SET public='.$this->db_quote_escape_string($public).' ';
            $sqlren .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
            $sqlren .= 'AND token='.$this->db_quote_escape_string($token).';';
            $req = $this->dbconnection->prepare($sqlren);
            $req->execute();
            $req->closeCursor();

            $ok = 1;
        }
        else {
            $ok = 2;
        }

        $response = new DataResponse(
            [
                'done'=>$ok,
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function renameSession($token, $newname) {
        // check if session exists
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ';
        $sqlchk .= 'AND token='.$this->db_quote_escape_string($token).' ';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbname = null;
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            break;
        }
        $req->closeCursor();

        if ($dbname !== null) {
            $sqlren = 'UPDATE *PREFIX*phonetrack_sessions ';
            $sqlren .= 'SET name='.$this->db_quote_escape_string($newname).' ';
            $sqlren .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
            $sqlren .= 'AND token='.$this->db_quote_escape_string($token).';';
            $req = $this->dbconnection->prepare($sqlren);
            $req->execute();
            $req->closeCursor();

            $ok = 1;
        }
        else {
            $ok = 2;
        }

        $response = new DataResponse(
            [
                'done'=>$ok,
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function deleteDevice($token, $device) {
        // check if session exists
        $sqlchk = 'SELECT name, token FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ';
        $sqlchk .= 'AND token='.$this->db_quote_escape_string($token).' ';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbname = null;
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            break;
        }
        $req->closeCursor();

        if ($dbname !== null) {
            // check if device exists
            $sqlchk = 'SELECT count(*) as c FROM *PREFIX*phonetrack_points ';
            $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
            $sqlchk .= 'AND deviceid='.$this->db_quote_escape_string($device).' ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $c = 0;
            while ($row = $req->fetch()){
                $c = (int)$row['c'];
                break;
            }
            $req->closeCursor();

            if ($c > 0) {
                $sqldel = 'DELETE FROM *PREFIX*phonetrack_points ';
                $sqldel .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
                $sqldel .= 'AND deviceid='.$this->db_quote_escape_string($device).' ';
                $req = $this->dbconnection->prepare($sqldel);
                $req->execute();
                $req->closeCursor();
            }

            $ok = 1;
        }
        else {
            $ok = 2;
        }

        $response = new DataResponse(
            [
                'done'=>$ok,
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function addPoint($token, $deviceid, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, $useragent) {
        $this->logPost($token, $deviceid, $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, $useragent);

        $sqlchk = 'SELECT MAX(id) as maxid FROM *PREFIX*phonetrack_points ';
        $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
        $sqlchk .= 'AND deviceid='.$this->db_quote_escape_string($deviceid).' ';
        $sqlchk .= 'AND lat='.$this->db_quote_escape_string($lat).' ';
        $sqlchk .= 'AND lon='.$this->db_quote_escape_string($lon).' ';
        $sqlchk .= 'AND timestamp='.$this->db_quote_escape_string($timestamp).' ';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbid = null;
        while ($row = $req->fetch()){
            $dbid = $row['maxid'];
            break;
        }
        $req->closeCursor();

        $response = new DataResponse(
            [
                'done'=>1,
                'id'=>$dbid
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     *
     * called by normal (logged) page
     */
    public function track($sessions) {
        $result = array();
        foreach ($sessions as $session) {
            $token = $session[0];
            $lastTime = $session[1];

            // check if session exists
            $dbtoken = null;
            $sqlget = 'SELECT token FROM *PREFIX*phonetrack_sessions ';
            $sqlget .= 'WHERE token='.$this->db_quote_escape_string($token).' ';
            $req = $this->dbconnection->prepare($sqlget);
            $req->execute();
            while ($row = $req->fetch()){
                $dbtoken = $row['token'];
            }
            $req->closeCursor();

            // if not, check it is a shared session
            if ($dbtoken === null) {
                $sqlget = 'SELECT sessionid FROM *PREFIX*phonetrack_shares ';
                $sqlget .= 'WHERE sharetoken='.$this->db_quote_escape_string($token).' ';
                $sqlget .= 'AND username='.$this->db_quote_escape_string($this->userId).';';
                $req = $this->dbconnection->prepare($sqlget);
                $req->execute();
                while ($row = $req->fetch()){
                    $dbtoken = $row['sessionid'];
                }
                $req->closeCursor();
            }

            // session exists
            if ($dbtoken !== null) {
                // get list of devices
                $devices = array();
                $sqldev = 'SELECT deviceid FROM *PREFIX*phonetrack_points ';
                $sqldev .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                $sqldev .= 'GROUP BY deviceid;';
                $req = $this->dbconnection->prepare($sqldev);
                $req->execute();
                while ($row = $req->fetch()){
                    array_push($devices, $row['deviceid']);
                }
                $req->closeCursor();

                // get the coords for each device
                $result[$token] = array();

                foreach ($devices as $devname) {
                    $resultDevArray = array();
                    $lastDeviceTime = 0;
                    if (is_array($lastTime) && array_key_exists($devname, $lastTime)) {
                        $lastDeviceTime = $lastTime[$devname];
                    }

                    $sqlget = 'SELECT id, deviceid, lat, lon, timestamp, accuracy, satellites, altitude, ';
                    $sqlget .= 'batterylevel, useragent FROM *PREFIX*phonetrack_points ';
                    $sqlget .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                    $sqlget .= 'AND deviceid='.$this->db_quote_escape_string($devname).' ';
                    $sqlget .= 'AND timestamp>'.$this->db_quote_escape_string($lastDeviceTime).' ';
                    $sqlget .= 'ORDER BY timestamp ASC';
                    $req = $this->dbconnection->prepare($sqlget);
                    $req->execute();
                    while ($row = $req->fetch()){
                        $entry = array();
                        foreach ($row as $k => $v) {
                            $entry[$k] = $v;
                        }
                        array_push($resultDevArray, $entry);
                    }
                    $req->closeCursor();
                    if (count($resultDevArray) > 0) {
                        $result[$token][$devname] = $resultDevArray;
                    }
                }
            }
        }

        $response = new DataResponse(
            [
                'sessions'=>$result
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    private function isSessionPublic($token) {
        $dbpublic = '';
        $sqlget = 'SELECT token, public FROM *PREFIX*phonetrack_sessions ';
        $sqlget .= 'WHERE token='.$this->db_quote_escape_string($token).' ';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            $dbtoken = $row['token'];
            $dbpublic = $row['public'];
        }
        $req->closeCursor();

        return ($dbpublic === '1' or $dbpublic === 1);
    }

    /**
     * @NoAdminRequired
     * @PublicPage
     *
     * called by publicWebLog page
     */
    public function publicWebLogTrack($sessions) {
        $result = array();
        foreach ($sessions as $session) {
            $token = $session[0];
            if ($this->isSessionPublic($token)) {
                $lastTime = $session[1];

                // check if session exists
                $dbtoken = null;
                $sqlget = 'SELECT token FROM *PREFIX*phonetrack_sessions ';
                $sqlget .= 'WHERE token='.$this->db_quote_escape_string($token).' ';
                $req = $this->dbconnection->prepare($sqlget);
                $req->execute();
                while ($row = $req->fetch()){
                    $dbtoken = $row['token'];
                }
                $req->closeCursor();

                // session exists
                if ($dbtoken !== null) {
                    // get list of devices
                    $devices = array();
                    $sqldev = 'SELECT deviceid FROM *PREFIX*phonetrack_points ';
                    $sqldev .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
                    $sqldev .= 'GROUP BY deviceid;';
                    $req = $this->dbconnection->prepare($sqldev);
                    $req->execute();
                    while ($row = $req->fetch()){
                        array_push($devices, $row['deviceid']);
                    }
                    $req->closeCursor();

                    // get the coords for each device
                    $result[$token] = array();

                    foreach ($devices as $devname) {
                        $resultDevArray = array();
                        $lastDeviceTime = 0;
                        if (is_array($lastTime) && array_key_exists($devname, $lastTime)) {
                            $lastDeviceTime = $lastTime[$devname];
                        }

                        $sqlget = 'SELECT id, deviceid, lat, lon, timestamp, accuracy, satellites,';
                        $sqlget .= ' altitude, batterylevel, useragent FROM *PREFIX*phonetrack_points ';
                        $sqlget .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
                        $sqlget .= 'AND deviceid='.$this->db_quote_escape_string($devname).' ';
                        $sqlget .= 'AND timestamp>'.$this->db_quote_escape_string($lastDeviceTime).' ';
                        $sqlget .= 'ORDER BY timestamp ASC';
                        $req = $this->dbconnection->prepare($sqlget);
                        $req->execute();
                        while ($row = $req->fetch()){
                            $entry = array();
                            foreach ($row as $k => $v) {
                                $entry[$k] = $v;
                            }
                            array_push($resultDevArray, $entry);
                        }
                        $req->closeCursor();
                        if (count($resultDevArray) > 0) {
                            $result[$token][$devname] = $resultDevArray;
                        }
                    }
                }
            }
        }

        $response = new DataResponse(
            [
                'sessions'=>$result
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     * @PublicPage
     *
     * called by publicSessionView page
     */
    public function publicViewTrack($sessions) {
        $result = array();
        foreach ($sessions as $session) {
            $publicviewtoken = $session[0];
            $lastTime = $session[1];

            // check if session exists
            $dbpublicviewtoken = null;
            $sqlget = 'SELECT publicviewtoken, token, public FROM *PREFIX*phonetrack_sessions ';
            $sqlget .= 'WHERE publicviewtoken='.$this->db_quote_escape_string($publicviewtoken).' ';
            $req = $this->dbconnection->prepare($sqlget);
            $req->execute();
            while ($row = $req->fetch()){
                $dbpublicviewtoken = $row['publicviewtoken'];
                $dbtoken = $row['token'];
                $dbpublic = (int)$row['public'];
            }
            $req->closeCursor();

            // session exists
            if ($dbpublicviewtoken !== null and $dbpublic === 1) {
                // get list of devices
                $devices = array();
                $sqldev = 'SELECT deviceid FROM *PREFIX*phonetrack_points ';
                $sqldev .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                $sqldev .= 'GROUP BY deviceid;';
                $req = $this->dbconnection->prepare($sqldev);
                $req->execute();
                while ($row = $req->fetch()){
                    array_push($devices, $row['deviceid']);
                }
                $req->closeCursor();

                // get the coords for each device
                $result[$dbpublicviewtoken] = array();

                foreach ($devices as $devname) {
                    $resultDevArray = array();
                    $lastDeviceTime = 0;
                    if (is_array($lastTime) && array_key_exists($devname, $lastTime)) {
                        $lastDeviceTime = $lastTime[$devname];
                    }

                    $sqlget = 'SELECT id, deviceid, lat, lon, timestamp, accuracy, satellites, ';
                    $sqlget .= 'altitude, batterylevel, useragent FROM *PREFIX*phonetrack_points ';
                    $sqlget .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                    $sqlget .= 'AND deviceid='.$this->db_quote_escape_string($devname).' ';
                    $sqlget .= 'AND timestamp>'.$this->db_quote_escape_string($lastDeviceTime).' ';
                    $sqlget .= 'ORDER BY timestamp ASC';
                    $req = $this->dbconnection->prepare($sqlget);
                    $req->execute();
                    while ($row = $req->fetch()){
                        $entry = array();
                        foreach ($row as $k => $v) {
                            $entry[$k] = $v;
                        }
                        array_push($resultDevArray, $entry);
                    }
                    $req->closeCursor();
                    if (count($resultDevArray) > 0) {
                        $result[$dbpublicviewtoken][$devname] = $resultDevArray;
                    }
                }
            }
        }

        $response = new DataResponse(
            [
                'sessions'=>$result
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     **/
    public function publicSessionWatch($publicviewtoken) {
        if ($publicviewtoken !== '') {
            // check if session exists
            $sqlchk = 'SELECT token, public  FROM *PREFIX*phonetrack_sessions ';
            $sqlchk .= 'WHERE publicviewtoken='.$this->db_quote_escape_string($publicviewtoken).' ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbtoken = null;
            $dbpublic = null;
            while ($row = $req->fetch()){
                $dbtoken = $row['token'];
                $dbpublic = (int)$row['public'];
                break;
            }
            $req->closeCursor();

            if ($dbtoken !== null and $dbpublic === 1) {
                return $this->publicWebLog($dbtoken, '');
            }
            else {
                return 'Session does not exist or is not public';
            }
        }
        else {
            return 'There is no such session';
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     **/
    public function publicWebLog($token, $deviceid) {
        if ($token !== '') {
            // check if session exists
            $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
            $sqlchk .= 'WHERE token='.$this->db_quote_escape_string($token).' ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbname = null;
            while ($row = $req->fetch()){
                $dbname = $row['name'];
                break;
            }
            $req->closeCursor();

            if ($dbname !== null) {
            }
            else {
                return 'There is no such session';
            }
        }
        else {
            return 'There is no such session';
        }

        require_once('tileservers.php');
        $params = [
            'username'=>'',
			'basetileservers'=>$baseTileServers,
			'usertileservers'=>[],
			'useroverlayservers'=>[],
			'usertileserverswms'=>[],
			'useroverlayserverswms'=>[],
            'publicsessionname'=>$dbname,
            'phonetrack_version'=>$this->appVersion
        ];
        $response = new TemplateResponse('phonetrack', 'main', $params);
        $response->setHeaders(Array('X-Frame-Options'=>''));
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedChildSrcDomain('*')
            ->addAllowedObjectDomain('*')
            ->addAllowedScriptDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function importSession($path) {
        $done = 1;
        $userFolder = \OC::$server->getUserFolder();
        $cleanpath = str_replace(array('../', '..\\'), '',  $path);

        $file = null;
        if ($userFolder->nodeExists($cleanpath)){
            $file = $userFolder->get($cleanpath);
            if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                $file->isReadable()){
                $sessionName = str_replace(['.gpx', '.GPX'], '', $file->getName());
                $res = $this->createSession($sessionName);
                $response = $res->getData();
                if ($response['done']) {
                    $token = $response['token'];
                    $publicviewtoken = $response['publicviewtoken'];
                    $done = $this->parseGpxImportPoints($file, $token);
                }
                else {
                    $done = 2;
                }
            }
            else {
                $done = 3;
            }
        }
        else {
            $done = 4;
        }

        // if done is not 1, 3 or 4 : delete session
        if ($done !== 1 && $done !== 3 && $done !== 4) {
            $this->deleteSession($token);
        }

        $response = new DataResponse(
            [
                'done'=>$done,
                'token'=>$token,
                'sessionName'=>$sessionName,
                'publicviewtoken'=>$publicviewtoken
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    private function parseGpxImportPoints($file, $token) {
        $gpx_content = $file->getContent();
        try{
            $gpx = new \SimpleXMLElement($gpx_content);
        }
        catch (\Exception $e) {
            error_log("Exception in ".$file->getName()." gpx parsing : ".$e->getMessage());
            return 5;
        }

        if (count($gpx->trk) === 0){
            error_log('Nothing to parse in '.$file->getName().' gpx file');
            return 6;
        }

        $trackIndex = 1;
        foreach($gpx->trk as $track){
            $deviceid = str_replace("\n", '', $track->name);
            if (empty($deviceid)){
                $deviceid = 'device '.$trackIndex;
            }
            $deviceid = str_replace('"', "'", $deviceid);

            foreach($track->trkseg as $segment) {
                foreach($segment->trkpt as $point) {
                    $lat = (float)$point['lat'];
                    $lon = (float)$point['lon'];
                    if (empty($point->time)) {
                        $timestamp = 0;
                    }
                    else{
                        $time = new \DateTime($point->time);
                        $timestamp = $time->getTimestamp();
                    }
                    if (empty($point->ele)) {
                        $ele = null;
                    }
                    else{
                        $ele = (float)$point->ele;
                    }
                    $this->logPost($token, $deviceid, $lat, $lon, $ele, $timestamp, -1, -1, -1, 'imported');
                }
            }
            $trackIndex++;
        }

        return 1;
    }

    /**
     * @NoAdminRequired
     */
    public function export($name, $token, $target) {
        date_default_timezone_set('UTC');
        $done = false;
        $userFolder = \OC::$server->getUserFolder();
        $path = $target.'/'.$name.'.gpx';
        $cleanpath = str_replace(array('../', '..\\'), '',  $path);

        $file = null;
        $filePossible = false;
        if ($userFolder->nodeExists($cleanpath)){
            $file = $userFolder->get($cleanpath);
            if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                $file->isUpdateable()){
                $filePossible = true;
            }
            else{
                $filePossible = false;
            }
        }
        else{
            $dirpath = dirname($cleanpath);
            $newFileName = basename($cleanpath);
            if ($userFolder->nodeExists($dirpath)){
                $dir = $userFolder->get($dirpath);
                if ($dir->getType() === \OCP\Files\FileInfo::TYPE_FOLDER and
                    $dir->isCreatable()){
                    $dir->newFile($newFileName);
                    $file = $dir->get($newFileName);
                    $filePossible = true;
                }
                else{
                    $filePossible = false;
                }
            }
            else{
                $filePossible = false;
            }
        }

        if ($filePossible) {
            // check if session exists
            $dbtoken = null;
            $sqlget = 'SELECT token FROM *PREFIX*phonetrack_sessions ';
            $sqlget .= 'WHERE name='.$this->db_quote_escape_string($name).' ';
            $sqlget .= 'AND token='.$this->db_quote_escape_string($token).' ';
            $req = $this->dbconnection->prepare($sqlget);
            $req->execute();
            while ($row = $req->fetch()){
                $dbtoken = $row['token'];
            }
            $req->closeCursor();

            // if not, check it is a shared session
            if ($dbtoken === null) {
                $sqlget = 'SELECT sessionid FROM *PREFIX*phonetrack_shares ';
                $sqlget .= 'WHERE sharetoken='.$this->db_quote_escape_string($token).' ';
                $sqlget .= 'AND username='.$this->db_quote_escape_string($this->userId).';';
                $req = $this->dbconnection->prepare($sqlget);
                $req->execute();
                while ($row = $req->fetch()){
                    $dbtoken = $row['sessionid'];
                }
                $req->closeCursor();
            }

            // session exists
            if ($dbtoken !== null) {
                // indexed by track name
                $coords = array();
                // get list of devices
                $devices = array();
                $sqldev = 'SELECT deviceid FROM *PREFIX*phonetrack_points ';
                $sqldev .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                $sqldev .= 'GROUP BY deviceid;';
                $req = $this->dbconnection->prepare($sqldev);
                $req->execute();
                while ($row = $req->fetch()){
                    array_push($devices, $row['deviceid']);
                }
                $req->closeCursor();

                // get the coords for each device
                $result[$name] = array();

                foreach ($devices as $devname) {
                    $coords[$devname] = array();
                    $sqlget = 'SELECT * FROM *PREFIX*phonetrack_points ';
                    $sqlget .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                    $sqlget .= 'AND deviceid='.$this->db_quote_escape_string($devname).' ';
                    $req = $this->dbconnection->prepare($sqlget);
                    $req->execute();
                    while ($row = $req->fetch()){
                        $epoch = $row['timestamp'];
                        $date = '';
                        if (is_numeric($epoch)) {
                            $epoch = (int)$epoch;
                            $dt = new \DateTime("@$epoch");
                            $date = $dt->format('Y-m-d\TH:i:s\Z');
                        }
                        $lat = $row['lat'];
                        $lon = $row['lon'];
                        $alt = $row['altitude'];

                        $point = array($lat, $lon, $date, $alt);
                        array_push($coords[$devname], $point);
                    }
                    $req->closeCursor();
                }
                $gpxContent = $this->generateGpx($name, $coords);
                $file->putContent($gpxContent);
                $done = true;
            }
        }

        $response = new DataResponse(
            [
                'done'=>$done
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    private function generateGpx($name, $coords) {
        $dt = new \DateTime();
        $date = $dt->format('Y-m-d\TH:i:s\Z');
        $gpxText = '<?xml version="1.0" encoding="UTF-8" standalone="no" ?>' . "\n";
        $gpxText .= '<gpx xmlns="http://www.topografix.com/GPX/1/1"' .
            ' xmlns:gpxx="http://www.garmin.com/xmlschemas/GpxExtensions/v3"' .
            ' xmlns:wptx1="http://www.garmin.com/xmlschemas/WaypointExtension/v1"' .
            ' xmlns:gpxtpx="http://www.garmin.com/xmlschemas/TrackPointExtension/v1"' .
            ' creator="PhoneTrack Owncloud/Nextcloud app ' .
            $this->appVersion. '" version="1.1"' .
            ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' .
            ' xsi:schemaLocation="http://www.topografix.com/GPX/1/1' .
            ' http://www.topografix.com/GPX/1/1/gpx.xsd' .
            ' http://www.garmin.com/xmlschemas/GpxExtensions/v3' .
            ' http://www8.garmin.com/xmlschemas/GpxExtensionsv3.xsd' .
            ' http://www.garmin.com/xmlschemas/WaypointExtension/v1' .
            ' http://www8.garmin.com/xmlschemas/WaypointExtensionv1.xsd' .
            ' http://www.garmin.com/xmlschemas/TrackPointExtension/v1' .
            ' http://www.garmin.com/xmlschemas/TrackPointExtensionv1.xsd">' . "\n";
        $gpxText .= '<metadata>' . "\n" . ' <time>' . $date . '</time>' . "\n";
        $gpxText .= ' <name>' . $name . '</name>' . "\n";
        $gpxText .= ' <desc>' . count($coords) . ' devices</desc>' . "\n";
        $gpxText .= '</metadata>' . "\n";
        foreach ($coords as $device => $points) {
            $gpxText .= '<trk>' . "\n" . ' <name>' . $device . '</name>' . "\n";
            $gpxText .= ' <trkseg>' . "\n";
            foreach ($points as $point) {
                $gpxText .= '  <trkpt lat="'.$point[0].'" lon="'.$point[1].'">' . "\n";
                $gpxText .= '   <time>' . $point[2] . '</time>' . "\n";
                if ($point[3] !== '') {
                    $gpxText .= '   <ele>' . $point[3] . '</ele>' . "\n";
                }
                $gpxText .= '  </trkpt>' . "\n";
            }
            $gpxText .= ' </trkseg>' . "\n";
            $gpxText .= '</trk>' . "\n";
        }
        $gpxText .= '</gpx>';
        return $gpxText;
    }

    /**
     * @NoAdminRequired
     */
    public function addUserShare($token, $username) {
        $ok = 0;
        // check if session exists and owned by current user
        $sqlchk = 'SELECT name, token FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ';
        $sqlchk .= 'AND token='.$this->db_quote_escape_string($token).' ';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbname = null;
        $dbtoken = null;
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            $dbtoken = $row['token'];
            break;
        }
        $req->closeCursor();

        if ($dbname !== null) {
            // check if user share exists
            $sqlchk = 'SELECT username, sessionid FROM *PREFIX*phonetrack_shares ';
            $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
            $sqlchk .= 'AND username='.$this->db_quote_escape_string($username).' ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbusername = null;
            while ($row = $req->fetch()){
                $dbusername = $row['username'];
                break;
            }
            $req->closeCursor();

            if ($dbusername === null) {
                // determine share token
                $sharetoken = md5('share'.$this->userId.$dbname.rand());

                // insert
                $sql = 'INSERT INTO *PREFIX*phonetrack_shares';
                $sql .= ' (sessionid, username, sharetoken) ';
                $sql .= 'VALUES (';
                $sql .= $this->db_quote_escape_string($dbtoken).',';
                $sql .= $this->db_quote_escape_string($username).',';
                $sql .= $this->db_quote_escape_string($sharetoken);
                $sql .= ');';
                $req = $this->dbconnection->prepare($sql);
                $req->execute();
                $req->closeCursor();

                $ok = 1;
            }
            else {
                $ok = 2;
            }
        }
        else {
            $ok = 3;
        }

        $response = new DataResponse(
            [
                'done'=>$ok
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function deleteUserShare($token, $username) {
        $ok = 0;
        // check if session exists
        $sqlchk = 'SELECT name, token FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ';
        $sqlchk .= 'AND token='.$this->db_quote_escape_string($token).' ';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbname = null;
        $dbtoken = null;
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            $dbtoken = $row['token'];
            break;
        }
        $req->closeCursor();

        if ($dbname !== null) {
            // check if user share exists
            $sqlchk = 'SELECT username, sessionid FROM *PREFIX*phonetrack_shares ';
            $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
            $sqlchk .= 'AND username='.$this->db_quote_escape_string($username).' ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbusername = null;
            while ($row = $req->fetch()){
                $dbusername = $row['username'];
                break;
            }
            $req->closeCursor();

            if ($dbusername !== null) {
                // delete
                $sqldel = 'DELETE FROM *PREFIX*phonetrack_shares ';
                $sqldel .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                $sqldel .= 'AND username='.$this->db_quote_escape_string($username).';';
                $req = $this->dbconnection->prepare($sqldel);
                $req->execute();
                $req->closeCursor();

                $ok = 1;
            }
            else {
                $ok = 2;
            }
        }
        else {
            $ok = 3;
        }

        $response = new DataResponse(
            [
                'done'=>$ok
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

}
