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

function distance($lat1, $long1, $lat2, $long2){

    if ($lat1 === $lat2 and $long1 === $long2){
        return 0;
    }

    // Convert latitude and longitude to
    // spherical coordinates in radians.
    $degrees_to_radians = pi()/180.0;

    // phi = 90 - latitude
    $phi1 = (90.0 - $lat1)*$degrees_to_radians;
    $phi2 = (90.0 - $lat2)*$degrees_to_radians;

    // theta = longitude
    $theta1 = $long1*$degrees_to_radians;
    $theta2 = $long2*$degrees_to_radians;

    $cos = (sin($phi1)*sin($phi2)*cos($theta1 - $theta2) +
           cos($phi1)*cos($phi2));
    // why some cosinus are > than 1 ?
    if ($cos > 1.0){
        $cos = 1.0;
    }
    $arc = acos($cos);

    // Remember to multiply arc by the radius of the earth
    // in your favorite set of units to get length.
    return $arc*6371000;
}

class PageController extends Controller {

    private $userId;
    private $userfolder;
    private $config;
    private $appVersion;
    private $userAbsoluteDataPath;
    private $shareManager;
    private $userManager;
    private $dbconnection;
    private $dbtype;
    private $dbdblquotes;
    private $appPath;
    private $defaultDeviceId;
    private $logger;
    protected $appName;

    public function __construct($AppName, IRequest $request, $UserId,
                                $userfolder, $config, $shareManager,
                                IAppManager $appManager, $userManager,
                                $logger){
        parent::__construct($AppName, $request);
        $this->logger = $logger;
        $this->appName = $AppName;
        $this->appVersion = $config->getAppValue('phonetrack', 'installed_version');
        // just to keep Owncloud compatibility
        // the first case : Nextcloud
        // else : Owncloud
        if (method_exists($appManager, 'getAppPath')){
            $this->appPath = $appManager->getAppPath('phonetrack');
        }
        $this->userId = $UserId;
        $this->userManager = $userManager;
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
     * Transition to new DB schema
     */
    private function adaptData() {
        $sessions = array();
        // sessions owned by current user
        $sqlget = 'SELECT name, token, creationversion FROM *PREFIX*phonetrack_sessions ';
        $sqlget .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            $dbtoken = $row['token'];
            $creationversion = $row['creationversion'];
            array_push($sessions, array($dbname, $dbtoken, $creationversion));
        }
        $req->closeCursor();

        foreach ($sessions as $s) {
            // if created before 0.0.8
            if ($s[2] === '' or $s[2] === null) {
                $devices = array();
                // get all devices
                $token = $s[1];
                // we get all potential devices from points
                $sqlgetdev = 'SELECT deviceid FROM *PREFIX*phonetrack_points ';
                $sqlgetdev .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' GROUP BY deviceid ;';
                $req = $this->dbconnection->prepare($sqlgetdev);
                $req->execute();
                while ($row = $req->fetch()){
                    array_push($devices, $row['deviceid']);
                }
                $req->closeCursor();

                foreach ($devices as $d) {
                    // if device does not exist, we create it
                    $did = null;
                    $sqlcheckdev = 'SELECT id, name FROM *PREFIX*phonetrack_devices ';
                    $sqlcheckdev .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
                    $sqlcheckdev .= 'AND name='.$this->db_quote_escape_string($d).' ;';
                    $req = $this->dbconnection->prepare($sqlcheckdev);
                    $req->execute();
                    while ($row = $req->fetch()){
                        $did = $row['id'];
                    }
                    $req->closeCursor();

                    // device with this name does not exist
                    if ($did === null) {
                        $sql = 'INSERT INTO *PREFIX*phonetrack_devices';
                        $sql .= ' (name, sessionid) ';
                        $sql .= 'VALUES (';
                        $sql .= $this->db_quote_escape_string($d).',';
                        $sql .= $this->db_quote_escape_string($token);
                        $sql .= ');';
                        $req = $this->dbconnection->prepare($sql);
                        $req->execute();
                        $req->closeCursor();
                    }
                }

                $alldevices = array();
                $sqlgetdev = 'SELECT id, name FROM *PREFIX*phonetrack_devices ';
                $sqlgetdev .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ;';
                $req = $this->dbconnection->prepare($sqlgetdev);
                $req->execute();
                while ($row = $req->fetch()){
                    array_push($alldevices, array($row['id'], $row['name']));
                }
                $req->closeCursor();

                foreach ($alldevices as $d) {
                    $id = $d[0];
                    $dname = $d[1];
                    // we modify all the points
                    $sqlupd = 'UPDATE *PREFIX*phonetrack_points SET';
                    $sqlupd .= ' deviceid='.$this->db_quote_escape_string($id).' ';
                    $sqlupd .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
                    $sqlupd .= 'AND deviceid='.$this->db_quote_escape_string($dname).' ;';
                    $req = $this->dbconnection->prepare($sqlupd);
                    $req->execute();
                    $req->closeCursor();
                }
                // update session creationversion
                $sqlupd = 'UPDATE *PREFIX*phonetrack_sessions SET';
                $sqlupd .= ' creationversion='.$this->db_quote_escape_string($this->appVersion).' ';
                $sqlupd .= 'WHERE token='.$this->db_quote_escape_string($token).' ;';
                $req = $this->dbconnection->prepare($sqlupd);
                $req->execute();
                $req->closeCursor();
            }
        }
    }

    /**
     * Welcome page.
     * Get session list
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index() {
        //date_default_timezone_set('Europe/Paris');
        //phpinfo();
        $tss = $this->getUserTileServers('tile');
        $oss = $this->getUserTileServers('overlay');
        $tssw = $this->getUserTileServers('tilewms');
        $ossw = $this->getUserTileServers('overlaywms');

        // migrate data
        //$this->adaptData();

        // PARAMS to view

        require_once('tileservers.php');
        if (!isset($baseTileServers) ) {
            $baseTileServers = '';
        }
        $params = [
            'username'=>$this->userId,
            'basetileservers'=>$baseTileServers,
            'usertileservers'=>$tss,
            'useroverlayservers'=>$oss,
            'usertileserverswms'=>$tssw,
            'useroverlayserverswms'=>$ossw,
            'publicsessionname'=>'',
            'lastposonly'=>'',
            'sharefilters'=>'',
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

    private function getReservedNames($token) {
        $result = array();

        $sqlgetres = 'SELECT name, nametoken FROM *PREFIX*phonetrack_devices ';
        $sqlgetres .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ;';
        $req = $this->dbconnection->prepare($sqlgetres);
        $req->execute();
        while ($row = $req->fetch()){
            $dbdevicename = $row['name'];
            $dbnametoken = $row['nametoken'];
            if ($dbnametoken !== '' and $dbnametoken !== null) {
                array_push($result, array('token'=>$dbnametoken, 'name'=>$dbdevicename));
            }
        }
        $req->closeCursor();

        return $result;
    }

    /**
     * @NoAdminRequired
     *
     * get sessions owned by and shared with current user
     */
    public function getSessions() {
        $sessions = array();
        // sessions owned by current user
        $sqlget = 'SELECT name, token, publicviewtoken, public, autoexport, autopurge FROM *PREFIX*phonetrack_sessions ';
        $sqlget .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            $dbtoken = $row['token'];
            $sharedWith = $this->getUserShares($dbtoken);
            $dbpublicviewtoken = $row['publicviewtoken'];
            $dbpublic = $row['public'];
            $dbautoexport = $row['autoexport'];
            $dbautopurge = $row['autopurge'];
            $reservedNames = $this->getReservedNames($dbtoken);
            $publicShares = $this->getPublicShares($dbtoken);
            $devices = $this->getDevices($dbtoken);
            array_push($sessions, array($dbname, $dbtoken, $dbpublicviewtoken, $devices, $dbpublic, $sharedWith, $reservedNames, $publicShares, $dbautoexport, $dbautopurge));
        }
        $req->closeCursor();

        // sessions shared with current user
        $sqlgetshares = 'SELECT sessionid, sharetoken FROM *PREFIX*phonetrack_shares ';
        $sqlgetshares .= 'WHERE username='.$this->db_quote_escape_string($this->userId).' ;';
        $req = $this->dbconnection->prepare($sqlgetshares);
        $req->execute();
        while ($row = $req->fetch()){
            $dbsessionid = $row['sessionid'];
            $dbsharetoken = $row['sharetoken'];
            $sessionInfo = $this->getSessionInfo($dbsessionid);
            $dbname = $sessionInfo['name'];
            $dbuser = $sessionInfo['user'];
            $devices = $this->getDevices($dbsessionid);
            array_push($sessions, array($dbname, $dbsharetoken, $dbuser, $devices));
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

    private function getDevices($sessionid) {
        $devices = array();
        $sqlget = 'SELECT id, name, alias, color, nametoken, shape FROM *PREFIX*phonetrack_devices ';
        $sqlget .= 'WHERE sessionid='.$this->db_quote_escape_string($sessionid).';';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            $dbid = $row['id'];
            $dbname = $row['name'];
            $dbalias = $row['alias'];
            $dbcolor = $row['color'];
            $dbnametoken = $row['nametoken'];
            $dbshape = $row['shape'];
            $geofences = $this->getGeofences($dbid);
            $proxims = $this->getProxims($dbid);
            $oneDev = [$dbid, $dbname, $dbalias, $dbcolor, $dbnametoken, $geofences, $proxims, $dbshape];
            array_push($devices, $oneDev);
        }
        $req->closeCursor();

        return $devices;
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

    /**
     * with whom is this session shared ?
     */
    private function getUserShares($sessionid) {
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
     * get the public shares for a session
     */
    private function getPublicShares($sessionid) {
        $shares = [];
        $sqlchk = 'SELECT * FROM *PREFIX*phonetrack_pubshares ';
        $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($sessionid).';';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbusername = null;
        while ($row = $req->fetch()){
            array_push(
                $shares,
                array(
                    'token'=>$row['sharetoken'],
                    'filters'=>$row['filters'],
                    'devicename'=>$row['devicename'],
                    'lastposonly'=>$row['lastposonly'],
                    'geofencify'=>$row['geofencify']
                )
            );
        }
        $req->closeCursor();

        return $shares;
    }

    /**
     * @NoAdminRequired
     */
    public function setPublicShareDevice($token, $sharetoken, $devicename) {
        $done = 0;
        // check if sessions exists
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
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
            // check if sharetoken exists
            $sqlchk = 'SELECT * FROM *PREFIX*phonetrack_pubshares ';
            $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
            $sqlchk .= 'AND sharetoken='.$this->db_quote_escape_string($sharetoken).';';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbshareid = null;
            while ($row = $req->fetch()){
                $dbshareid = $row['id'];
            }
            $req->closeCursor();

            if ($dbshareid !== null) {
                // set device name
                $sqlupd = 'UPDATE *PREFIX*phonetrack_pubshares SET';
                $sqlupd .= ' devicename='.$this->db_quote_escape_string($devicename).' ';
                $sqlupd .= 'WHERE id='.$this->db_quote_escape_string($dbshareid).';';
                $req = $this->dbconnection->prepare($sqlupd);
                $req->execute();
                $req->closeCursor();

                $done = 1;
            }
            else {
                $done = 3;
            }
        }
        else {
            $done = 2;
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

    /**
     * @NoAdminRequired
     */
    public function setPublicShareGeofencify($token, $sharetoken, $geofencify) {
        $done = 0;
        // check if sessions exists
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
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
            // check if sharetoken exists
            $sqlchk = 'SELECT * FROM *PREFIX*phonetrack_pubshares ';
            $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
            $sqlchk .= 'AND sharetoken='.$this->db_quote_escape_string($sharetoken).';';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbshareid = null;
            while ($row = $req->fetch()){
                $dbshareid = $row['id'];
            }
            $req->closeCursor();

            if ($dbshareid !== null) {
                // set device name
                $sqlupd = 'UPDATE *PREFIX*phonetrack_pubshares SET';
                $sqlupd .= ' geofencify='.$this->db_quote_escape_string($geofencify).' ';
                $sqlupd .= 'WHERE id='.$this->db_quote_escape_string($dbshareid).';';
                $req = $this->dbconnection->prepare($sqlupd);
                $req->execute();
                $req->closeCursor();

                $done = 1;
            }
            else {
                $done = 3;
            }
        }
        else {
            $done = 2;
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

    /**
     * @NoAdminRequired
     */
    public function setPublicShareLastOnly($token, $sharetoken, $lastposonly) {
        $done = 0;
        // check if sessions exists
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
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
            // check if sharetoken exists
            $sqlchk = 'SELECT * FROM *PREFIX*phonetrack_pubshares ';
            $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
            $sqlchk .= 'AND sharetoken='.$this->db_quote_escape_string($sharetoken).';';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbshareid = null;
            while ($row = $req->fetch()){
                $dbshareid = $row['id'];
            }
            $req->closeCursor();

            if ($dbshareid !== null) {
                // set device name
                $sqlupd = 'UPDATE *PREFIX*phonetrack_pubshares SET';
                $sqlupd .= ' lastposonly='.$this->db_quote_escape_string($lastposonly).' ';
                $sqlupd .= 'WHERE id='.$this->db_quote_escape_string($dbshareid).';';
                $req = $this->dbconnection->prepare($sqlupd);
                $req->execute();
                $req->closeCursor();

                $done = 1;
            }
            else {
                $done = 3;
            }
        }
        else {
            $done = 2;
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

    /**
     * @NoAdminRequired
     */
    public function createSession($name) {
        $token = '';
        $publicviewtoken = '';
        // check if session name is not already used
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
        $sqlchk .= 'AND name='.$this->db_quote_escape_string($name).' ';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbname = null;
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            break;
        }
        $req->closeCursor();

        if ($dbname === null and $name !== '') {
            // determine token
            $token = md5($this->userId.$name.rand());
            $publicviewtoken = md5($this->userId.$name.rand());

            // insert
            $sql = 'INSERT INTO *PREFIX*phonetrack_sessions';
            $sql .= ' ('.$this->dbdblquotes.'user'.$this->dbdblquotes.', name, token, publicviewtoken, public, creationversion) ';
            $sql .= 'VALUES ('.$this->db_quote_escape_string($this->userId).',';
            $sql .= $this->db_quote_escape_string($name).',';
            $sql .= $this->db_quote_escape_string($token).',';
            $sql .= $this->db_quote_escape_string($publicviewtoken).',';
            $sql .= $this->db_quote_escape_string('1').',';
            $sql .= $this->db_quote_escape_string($this->appVersion).');';
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
        $ok = 0;
        // check if session exists
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
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

            // get all devices
            $dids = array();
            $sqlchk = 'SELECT id FROM *PREFIX*phonetrack_devices ';
            $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ;';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbdevid = null;
            while ($row = $req->fetch()){
                array_push($dids, $row['id']);
            }
            $req->closeCursor();

            foreach ($dids as $did) {
                $sqldel = 'DELETE FROM *PREFIX*phonetrack_points ';
                $sqldel .= 'WHERE deviceid='.$this->db_quote_escape_string($did).' ;';
                $req = $this->dbconnection->prepare($sqldel);
                $req->execute();
                $req->closeCursor();
            }

            $sqldel = 'DELETE FROM *PREFIX*phonetrack_shares ';
            $sqldel .= 'WHERE sessionid='.$this->db_quote_escape_string($token).';';
            $req = $this->dbconnection->prepare($sqldel);
            $req->execute();
            $req->closeCursor();

            $sqldel = 'DELETE FROM *PREFIX*phonetrack_devices ';
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
    public function deletePoints($token, $deviceid, $pointids) {
        $ok = 0;
        // check if session exists
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
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
            $dbdid = null;
            $sqldev = 'SELECT id FROM *PREFIX*phonetrack_devices ';
            $sqldev .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
            $sqldev .= 'AND id='.$this->db_quote_escape_string($deviceid).' ;';
            $req = $this->dbconnection->prepare($sqldev);
            $req->execute();
            while ($row = $req->fetch()){
                $dbdid =  $row['id'];
            }
            $req->closeCursor();

            if ($dbdid !== null) {
                if (count($pointids) > 0) {
                    $escapedPointIds = array();
                    foreach ($pointids as $pid) {
                        array_push($escapedPointIds, $this->db_quote_escape_string($pid));
                    }
                    $sqldel = 'DELETE FROM *PREFIX*phonetrack_points ';
                    $sqldel .= 'WHERE deviceid='.$this->db_quote_escape_string($dbdid).' ';
                    $sqldel .= 'AND (id=';
                    $sqldel .= implode(' OR id=', $escapedPointIds);
                    $sqldel .= ');';
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
        }
        else {
            $ok = 4;
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
        $lat, $lon, $alt, $timestamp, $acc, $bat, $sat, $useragent, $speed, $bearing) {
        // check if session exists
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
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
            $dbdid = null;
            $sqldev = 'SELECT id FROM *PREFIX*phonetrack_devices ';
            $sqldev .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
            $sqldev .= 'AND id='.$this->db_quote_escape_string($deviceid).' ;';
            $req = $this->dbconnection->prepare($sqldev);
            $req->execute();
            while ($row = $req->fetch()){
                $dbdid =  $row['id'];
            }
            $req->closeCursor();

            if ($dbdid !== null) {
                // check if point exists
                $sqlchk = 'SELECT id FROM *PREFIX*phonetrack_points ';
                $sqlchk .= 'WHERE deviceid='.$this->db_quote_escape_string($dbdid).' ';
                $sqlchk .= 'AND id='.$this->db_quote_escape_string($pointid).' ';
                $req = $this->dbconnection->prepare($sqlchk);
                $req->execute();
                $dbpid = null;
                while ($row = $req->fetch()){
                    $dbpid = $row['id'];
                    break;
                }
                $req->closeCursor();

                if ($dbpid !== null) {
                    $sqlupd = 'UPDATE *PREFIX*phonetrack_points SET';
                    $sqlupd .= ' lat='.$this->db_quote_escape_string($lat).' ';
                    $sqlupd .= ', lon='.$this->db_quote_escape_string($lon).' ';
                    $sqlupd .= ', altitude='.(is_numeric($alt) ? $this->db_quote_escape_string(floatval($alt)) : 'NULL').' ';
                    $sqlupd .= ', timestamp='.$this->db_quote_escape_string($timestamp).' ';
                    $sqlupd .= ', accuracy='.(is_numeric($acc) ? $this->db_quote_escape_string(floatval($acc)) : 'NULL').' ';
                    $sqlupd .= ', batterylevel='.(is_numeric($bat) ? $this->db_quote_escape_string(floatval($bat)) : 'NULL').' ';
                    $sqlupd .= ', satellites='.(is_numeric($sat) ? $this->db_quote_escape_string(intval($sat)) : 'NULL').' ';
                    $sqlupd .= ', useragent='.$this->db_quote_escape_string($useragent).' ';
                    $sqlupd .= ', speed='.(is_numeric($speed) ? $this->db_quote_escape_string(floatval($speed)) : 'NULL').' ';
                    $sqlupd .= ', bearing='.(is_numeric($bearing) ? $this->db_quote_escape_string(floatval($bearing)) : 'NULL').' ';
                    $sqlupd .= 'WHERE deviceid='.$this->db_quote_escape_string($dbdid).' ';
                    $sqlupd .= 'AND id='.$this->db_quote_escape_string($dbpid).';';
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
                $ok = 3;
            }
        }
        else {
            $ok = 4;
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
        $ok = 0;
        if (intval($public) === 1 or intval($public) === 0) {
            // check if session exists
            $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
            $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
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
        }
        else {
            $ok = 3;
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
    public function setSessionAutoExport($token, $value) {
        // check if session exists
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
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
            $sqlren .= 'SET autoexport='.$this->db_quote_escape_string($value).' ';
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
    public function setSessionAutoPurge($token, $value) {
        // check if session exists
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
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
            $sqlren .= 'SET autopurge='.$this->db_quote_escape_string($value).' ';
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
    public function setDeviceColor($session, $device, $color) {
        $ok = 0;
        // check if session exists
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
        $sqlchk .= 'AND token='.$this->db_quote_escape_string($session).' ';
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
            $sqlchk = 'SELECT id FROM *PREFIX*phonetrack_devices ';
            $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($session).' ';
            $sqlchk .= 'AND id='.$this->db_quote_escape_string($device).' ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbdevid = null;
            while ($row = $req->fetch()){
                $dbdevid = $row['id'];
                break;
            }
            $req->closeCursor();

            if ($dbdevid !== null) {
                $sqlupd = 'UPDATE *PREFIX*phonetrack_devices ';
                $sqlupd .= 'SET color='.$this->db_quote_escape_string($color).' ';
                $sqlupd .= 'WHERE id='.$this->db_quote_escape_string($device).' ';
                $sqlupd .= 'AND sessionid='.$this->db_quote_escape_string($session).';';
                $req = $this->dbconnection->prepare($sqlupd);
                $req->execute();
                $req->closeCursor();
                $ok = 1;
            }
            else {
                $ok = 3;
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
    public function setDeviceShape($session, $device, $shape) {
        $ok = 0;
        // check if session exists
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
        $sqlchk .= 'AND token='.$this->db_quote_escape_string($session).' ';
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
            $sqlchk = 'SELECT id FROM *PREFIX*phonetrack_devices ';
            $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($session).' ';
            $sqlchk .= 'AND id='.$this->db_quote_escape_string($device).' ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbdevid = null;
            while ($row = $req->fetch()){
                $dbdevid = $row['id'];
                break;
            }
            $req->closeCursor();

            if ($dbdevid !== null) {
                $sqlupd = 'UPDATE *PREFIX*phonetrack_devices ';
                $sqlupd .= 'SET shape='.$this->db_quote_escape_string($shape).' ';
                $sqlupd .= 'WHERE id='.$this->db_quote_escape_string($device).' ';
                $sqlupd .= 'AND sessionid='.$this->db_quote_escape_string($session).';';
                $req = $this->dbconnection->prepare($sqlupd);
                $req->execute();
                $req->closeCursor();
                $ok = 1;
            }
            else {
                $ok = 3;
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
    public function renameSession($token, $newname) {
        $ok = 0;
        if ($newname !== '' and $newname !== null) {
            // check if session exists
            $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
            $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
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
        }
        else {
            $ok = 3;
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
    public function renameDevice($token, $deviceid, $newname) {
        $ok = 0;
        if ($newname !== '' and $newname !== null) {
            // check if session exists
            $sqlchk = 'SELECT name, token FROM *PREFIX*phonetrack_sessions ';
            $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
            $sqlchk .= 'AND token='.$this->db_quote_escape_string($token).' ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbtoken = null;
            while ($row = $req->fetch()){
                $dbtoken = $row['token'];
                break;
            }
            $req->closeCursor();

            if ($dbtoken !== null) {
                // check if device exists
                $sqlchk = 'SELECT id FROM *PREFIX*phonetrack_devices ';
                $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                $sqlchk .= 'AND id='.$this->db_quote_escape_string($deviceid).' ';
                $req = $this->dbconnection->prepare($sqlchk);
                $req->execute();
                $dbdeviceid = null;
                while ($row = $req->fetch()){
                    $dbdeviceid = $row['id'];
                }
                $req->closeCursor();

                if ($dbdeviceid !== null) {
                    $sqlren = 'UPDATE *PREFIX*phonetrack_devices ';
                    $sqlren .= 'SET name='.$this->db_quote_escape_string($newname).' ';
                    $sqlren .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                    $sqlren .= 'AND id='.$this->db_quote_escape_string($dbdeviceid).' ';
                    $req = $this->dbconnection->prepare($sqlren);
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
        }
        else {
            $ok = 4;
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
    public function setDeviceAlias($token, $deviceid, $newalias) {
        $ok = 0;
        if ($newalias !== null) {
            // check if session exists
            $sqlchk = 'SELECT name, token FROM *PREFIX*phonetrack_sessions ';
            $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
            $sqlchk .= 'AND token='.$this->db_quote_escape_string($token).' ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbtoken = null;
            while ($row = $req->fetch()){
                $dbtoken = $row['token'];
                break;
            }
            $req->closeCursor();

            if ($dbtoken !== null) {
                // check if device exists
                $sqlchk = 'SELECT id FROM *PREFIX*phonetrack_devices ';
                $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                $sqlchk .= 'AND id='.$this->db_quote_escape_string($deviceid).' ';
                $req = $this->dbconnection->prepare($sqlchk);
                $req->execute();
                $dbdeviceid = null;
                while ($row = $req->fetch()){
                    $dbdeviceid = $row['id'];
                }
                $req->closeCursor();

                if ($dbdeviceid !== null) {
                    $sqlren = 'UPDATE *PREFIX*phonetrack_devices ';
                    $sqlren .= 'SET alias='.$this->db_quote_escape_string($newalias).' ';
                    $sqlren .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                    $sqlren .= 'AND id='.$this->db_quote_escape_string($dbdeviceid).' ';
                    $req = $this->dbconnection->prepare($sqlren);
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
        }
        else {
            $ok = 4;
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
    public function reaffectDevice($token, $deviceid, $newSessionId) {
        $ok = 0;
        // check if session exists
        $sqlchk = 'SELECT name, token FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
        $sqlchk .= 'AND token='.$this->db_quote_escape_string($token).' ';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbtoken = null;
        while ($row = $req->fetch()){
            $dbtoken = $row['token'];
            break;
        }
        $req->closeCursor();

        if ($dbtoken !== null) {
            // check if destination session exists
            $sqlchk = 'SELECT name, token FROM *PREFIX*phonetrack_sessions ';
            $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
            $sqlchk .= 'AND token='.$this->db_quote_escape_string($newSessionId).' ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbdesttoken = null;
            while ($row = $req->fetch()){
                $dbdesttoken = $row['token'];
                break;
            }
            $req->closeCursor();

            if ($dbdesttoken !== null) {
                // check if device exists
                $sqlchk = 'SELECT id, name FROM *PREFIX*phonetrack_devices ';
                $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                $sqlchk .= 'AND id='.$this->db_quote_escape_string($deviceid).' ';
                $req = $this->dbconnection->prepare($sqlchk);
                $req->execute();
                $dbdeviceid = null;
                $dbdevicename = null;
                while ($row = $req->fetch()){
                    $dbdeviceid = $row['id'];
                    $dbdevicename = $row['name'];
                }
                $req->closeCursor();

                if ($dbdeviceid !== null) {
                    // check if there is a device with same name in destination session
                    $sqlchk = 'SELECT id, name FROM *PREFIX*phonetrack_devices ';
                    $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($dbdesttoken).' ';
                    $sqlchk .= 'AND name='.$this->db_quote_escape_string($dbdevicename).' ';
                    $req = $this->dbconnection->prepare($sqlchk);
                    $req->execute();
                    $dbdestname = null;
                    while ($row = $req->fetch()){
                        $dbdestname = $row['name'];
                    }
                    $req->closeCursor();

                    if ($dbdestname === null) {
                        $sqlreaff = 'UPDATE *PREFIX*phonetrack_devices ';
                        $sqlreaff .= 'SET sessionid='.$this->db_quote_escape_string($dbdesttoken).' ';
                        $sqlreaff .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                        $sqlreaff .= 'AND id='.$this->db_quote_escape_string($dbdeviceid).' ';
                        $req = $this->dbconnection->prepare($sqlreaff);
                        $req->execute();
                        $req->closeCursor();

                        $ok = 1;
                    }
                    else {
                        $ok = 3;
                    }
                }
                else {
                    $ok = 4;
                }
            }
            else {
                $ok = 5;
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
    public function deleteDevice($token, $deviceid) {
        $ok = 0;
        // check if session exists
        $sqlchk = 'SELECT name, token FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
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
            $sqlchk = 'SELECT id FROM *PREFIX*phonetrack_devices ';
            $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
            $sqlchk .= 'AND id='.$this->db_quote_escape_string($deviceid).' ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbdeviceid = null;
            while ($row = $req->fetch()){
                $dbdeviceid = $row['id'];
            }
            $req->closeCursor();

            if ($dbdeviceid !== null) {
                $sqldel = 'DELETE FROM *PREFIX*phonetrack_points ';
                $sqldel .= 'WHERE deviceid='.$this->db_quote_escape_string($dbdeviceid).' ;';
                $req = $this->dbconnection->prepare($sqldel);
                $req->execute();
                $req->closeCursor();

                $sqldel = 'DELETE FROM *PREFIX*phonetrack_geofences ';
                $sqldel .= 'WHERE deviceid='.$this->db_quote_escape_string($dbdeviceid).' ;';
                $req = $this->dbconnection->prepare($sqldel);
                $req->execute();
                $req->closeCursor();

                $sqldel = 'DELETE FROM *PREFIX*phonetrack_proxims ';
                $sqldel .= 'WHERE deviceid1='.$this->db_quote_escape_string($dbdeviceid).' ';
                $sqldel .= 'OR deviceid2='.$this->db_quote_escape_string($dbdeviceid).' ;';
                $req = $this->dbconnection->prepare($sqldel);
                $req->execute();
                $req->closeCursor();

                $sqldel = 'DELETE FROM *PREFIX*phonetrack_devices ';
                $sqldel .= 'WHERE id='.$this->db_quote_escape_string($dbdeviceid).' ;';
                $req = $this->dbconnection->prepare($sqldel);
                $req->execute();
                $req->closeCursor();
                $ok = 1;
            }
            else {
                $ok = 3;
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

    private function deviceLinesOrPointsAsked($token, $deviceid, $options) {
        if ($options === null) {
            return False;
        }
        else {
            $devid = strval($deviceid);
            return (
                    isset($options['activeSessions'])
                and array_key_exists($token, $options['activeSessions'])
                and array_key_exists($devid, $options['activeSessions'][$token])
                and array_key_exists('line', $options['activeSessions'][$token][$devid])
                and array_key_exists('point', $options['activeSessions'][$token][$devid])
                and (
                    $options['activeSessions'][$token][$devid]['line'] === True
                    or $options['activeSessions'][$token][$devid]['point'] === True
                )
            );
        }
    }

    /**
     * @NoAdminRequired
     *
     * called by normal (logged) page
     */
    public function track($sessions) {
        $result = array();
        $colors = array();
        $shapes = array();
        $names = array();
        $aliases = array();
        $geofences = array();
        $proxims = array();
        // manage sql optim filters (time only)
        $fArray = $this->getCurrentFilters();
        $settingsTimeFilterSQL = '';
        if (isset($fArray['tsmin'])) {
            $settingsTimeFilterSQL .= 'AND timestamp >= '.$this->db_quote_escape_string($fArray['tsmin']).' ';
        }
        if (isset($fArray['tsmax'])) {
            $settingsTimeFilterSQL .= 'AND timestamp <= '.$this->db_quote_escape_string($fArray['tsmax']).' ';
        }
        // get option values
        $sqlget = 'SELECT * FROM *PREFIX*phonetrack_options ';
        $sqlget .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        $optString = null;
        $options = null;
        while ($row = $req->fetch()){
            $optString = $row['jsonvalues'];
        }
        if ($optString !== null) {
            $options = json_decode($optString, True);
        }
        if (is_array($sessions)) {
            foreach ($sessions as $session) {
                if (is_array($session) and count($session) === 3) {
                    $token = $session[0];
                    $lastTime = $session[1];
                    $firstTime = $session[2];

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
                        $sqldev = 'SELECT id FROM *PREFIX*phonetrack_devices ';
                        $sqldev .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ;';
                        $req = $this->dbconnection->prepare($sqldev);
                        $req->execute();
                        while ($row = $req->fetch()){
                            array_push($devices, intval($row['id']));
                        }
                        $req->closeCursor();

                        // get the coords for each device
                        $result[$token] = array();

                        foreach ($devices as $devid) {
                            $resultDevArray = array();

                            $firstDeviceTimeSQL = '';
                            if (is_array($firstTime) && array_key_exists($devid, $firstTime)) {
                                $firstDeviceTime = $firstTime[$devid];
                                $firstDeviceTimeSQL = 'timestamp<'.$this->db_quote_escape_string($firstDeviceTime);
                            }

                            $lastDeviceTime = 0;
                            $lastDeviceTimeSQL = '';
                            if (is_array($lastTime) && array_key_exists($devid, $lastTime)) {
                                $lastDeviceTime = $lastTime[$devid];
                                $lastDeviceTimeSQL = 'timestamp>'.$this->db_quote_escape_string($lastDeviceTime);
                            }
                            // build SQL condition for first/last
                            $firstLastSQL = '';
                            if ($firstDeviceTimeSQL !== '') {
                                if ($lastDeviceTimeSQL !== '') {
                                    $firstLastSQL = 'AND ('.$firstDeviceTimeSQL.' OR '.$lastDeviceTimeSQL.') ';
                                }
                                else {
                                    $firstLastSQL = 'AND '.$firstDeviceTimeSQL.' ';
                                }
                            }
                            else if ($lastDeviceTimeSQL !== '') {
                                $firstLastSQL = 'AND '.$lastDeviceTimeSQL.' ';
                            }
                            // we give color (first point given)
                            else {
                                $sqlcolor = 'SELECT color, name, alias, shape ';
                                $sqlcolor .= 'FROM *PREFIX*phonetrack_devices ';
                                $sqlcolor .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                                $sqlcolor .= 'AND id='.$this->db_quote_escape_string($devid).'; ';
                                $req = $this->dbconnection->prepare($sqlcolor);
                                $req->execute();
                                $col = '';
                                while ($row = $req->fetch()){
                                    $shape = $row['shape'];
                                    $col = $row['color'];
                                    $name = $row['name'];
                                    $alias = $row['alias'];
                                }
                                $req->closeCursor();
                                if (!array_key_exists($token, $shapes)) {
                                    $shapes[$token] = array();
                                }
                                $shapes[$token][$devid] = $shape;
                                if (!array_key_exists($token, $colors)) {
                                    $colors[$token] = array();
                                }
                                $colors[$token][$devid] = $col;
                                if (!array_key_exists($token, $names)) {
                                    $names[$token] = array();
                                }
                                $names[$token][$devid] = $name;
                                if (!array_key_exists($token, $aliases)) {
                                    $aliases[$token] = array();
                                }
                                $aliases[$token][$devid] = $alias;
                                // geofences
                                if (!array_key_exists($token, $geofences)) {
                                    $geofences[$token] = array();
                                }
                                if (!array_key_exists($devid, $geofences[$token])) {
                                    $geofences[$token][$devid] = array();
                                }
                                $geofences[$token][$devid] = $this->getGeofences($devid);
                                // proxims
                                if (!array_key_exists($token, $proxims)) {
                                    $proxims[$token] = array();
                                }
                                if (!array_key_exists($devid, $proxims[$token])) {
                                    $proxims[$token][$devid] = array();
                                }
                                $proxims[$token][$devid] = $this->getProxims($devid);
                            }

                            $sqlget = 'SELECT id, deviceid, lat, lon, timestamp, accuracy, satellites,';
                            $sqlget .= ' altitude, batterylevel, useragent, speed, bearing FROM *PREFIX*phonetrack_points ';
                            $sqlget .= 'WHERE deviceid='.$this->db_quote_escape_string($devid).' ';
                            $sqlget .= $firstLastSQL;
                            $sqlget .= $settingsTimeFilterSQL;
                            // get max number of points to load
                            $nbpointsload = null;
                            if (isset($options['nbpointsload']) and is_numeric($options['nbpointsload'])) {
                                $nbpointsload = intval($options['nbpointsload']);
                            }
                            // are lines or points asked ? if not, just get the last
                            if (! $this->deviceLinesOrPointsAsked($token, $devid, $options)) {
                                $sqlget .= 'ORDER BY timestamp DESC LIMIT 1';
                            }
                            else if ($nbpointsload !== null) {
                                $sqlget .= 'ORDER BY timestamp DESC LIMIT '.$nbpointsload;
                            }
                            else {
                                $sqlget .= 'ORDER BY timestamp DESC';
                            }
                            $req = $this->dbconnection->prepare($sqlget);
                            $req->execute();
                            while ($row = $req->fetch()){
                                $entry = array(
                                    intval($row['id']),
                                    floatval($row['lat']),
                                    floatval($row['lon']),
                                    intval($row['timestamp']),
                                    is_numeric($row['accuracy']) ? floatval($row['accuracy']) : null,
                                    is_numeric($row['satellites']) ? intval($row['satellites']) : null,
                                    is_numeric($row['altitude']) ? floatval($row['altitude']) : null,
                                    is_numeric($row['batterylevel']) ? floatval($row['batterylevel']) : null,
                                    $row['useragent'],
                                    is_numeric($row['speed']) ? floatval($row['speed']) : null,
                                    is_numeric($row['bearing']) ? floatval($row['bearing']) : null
                                );
                                array_unshift($resultDevArray, $entry);
                            }
                            $req->closeCursor();
                            if (count($resultDevArray) > 0) {
                                $result[$token][$devid] = $resultDevArray;
                            }
                            else {
                                // if device has no new point and no last time
                                // it means it was probably reserved : we don't give its name
                                if (!is_array($lastTime) or !array_key_exists($devid, $lastTime)) {
                                    unset($names[$dbtoken][$devid]);
                                    unset($aliases[$dbtoken][$devid]);
                                    unset($colors[$dbtoken][$devid]);
                                    unset($shapes[$dbtoken][$devid]);
                                    unset($geofences[$dbtoken][$devid]);
                                }
                            }
                        }
                    }
                }
            }
        }

        $response = new DataResponse(
            [
                'sessions'=>$result,
                'colors'=>$colors,
                'shapes'=>$shapes,
                'names'=>$names,
                'aliases'=>$aliases,
                'geofences'=>$geofences,
                'proxims'=>$proxims
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    private function getGeofences($devid) {
        $geofences = array();
        $sqlfences = 'SELECT id, name, latmin, latmax, lonmin, lonmax, urlenter, urlleave, ';
        $sqlfences .= 'urlenterpost, urlleavepost, sendemail ';
        $sqlfences .= 'FROM *PREFIX*phonetrack_geofences ';
        $sqlfences .= 'WHERE deviceid='.$this->db_quote_escape_string($devid).' ;';
        $req = $this->dbconnection->prepare($sqlfences);
        $req->execute();
        while ($row = $req->fetch()){
            $fence = array();
            foreach ($row as $k => $v) {
                $fence[$k] = $v;
            }
            array_push($geofences, $fence);
        }
        $req->closeCursor();
        return $geofences;
    }

    private function getProxims($devid) {
        $proxims = array();
        $sqlproxims = '
            SELECT *PREFIX*phonetrack_proxims.id AS id, deviceid2, lowlimit, highlimit,
                urlclose, urlfar,
                urlclosepost, urlfarpost,
                sendemail,
                *PREFIX*phonetrack_devices.name AS dname2,
                *PREFIX*phonetrack_sessions.name AS sname2
            FROM *PREFIX*phonetrack_proxims
            INNER JOIN *PREFIX*phonetrack_devices ON deviceid2=*PREFIX*phonetrack_devices.id
            INNER JOIN *PREFIX*phonetrack_sessions ON *PREFIX*phonetrack_devices.sessionid=*PREFIX*phonetrack_sessions.token
            WHERE deviceid1='.$this->db_quote_escape_string($devid).' ;';
        $req = $this->dbconnection->prepare($sqlproxims);
        $req->execute();
        while ($row = $req->fetch()){
            $proxim = array();
            foreach ($row as $k => $v) {
                $proxim[$k] = $v;
            }
            array_push($proxims, $proxim);
        }
        $req->closeCursor();
        return $proxims;
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
        $colors = array();
        $shapes = array();
        $names = array();
        $aliases = array();
        foreach ($sessions as $session) {
            $token = $session[0];
            if ($this->isSessionPublic($token)) {
                $lastTime = $session[1];
                $firstTime = $session[2];

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
                    $sqldev = 'SELECT id FROM *PREFIX*phonetrack_devices ';
                    $sqldev .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ;';
                    $req = $this->dbconnection->prepare($sqldev);
                    $req->execute();
                    while ($row = $req->fetch()){
                        array_push($devices, intval($row['id']));
                    }
                    $req->closeCursor();

                    // get the coords for each device
                    $result[$token] = array();

                    foreach ($devices as $devid) {
                        $resultDevArray = array();

                        $firstDeviceTimeSQL = '';
                        if (is_array($firstTime) && array_key_exists($devid, $firstTime)) {
                            $firstDeviceTime = $firstTime[$devid];
                            $firstDeviceTimeSQL = 'timestamp<'.$this->db_quote_escape_string($firstDeviceTime);
                        }

                        $lastDeviceTime = 0;
                        $lastDeviceTimeSQL = '';
                        if (is_array($lastTime) && array_key_exists($devid, $lastTime)) {
                            $lastDeviceTime = $lastTime[$devid];
                            $lastDeviceTimeSQL = 'timestamp>'.$this->db_quote_escape_string($lastDeviceTime);
                        }
                        // build SQL condition for first/last
                        $firstLastSQL = '';
                        if ($firstDeviceTimeSQL !== '') {
                            if ($lastDeviceTimeSQL !== '') {
                                $firstLastSQL = 'AND ('.$firstDeviceTimeSQL.' OR '.$lastDeviceTimeSQL.') ';
                            }
                            else {
                                $firstLastSQL = 'AND '.$firstDeviceTimeSQL.' ';
                            }
                        }
                        else if ($lastDeviceTimeSQL !== '') {
                            $firstLastSQL = 'AND '.$lastDeviceTimeSQL.' ';
                        }
                        // we give color (first point given)
                        else {
                            $sqlcolor = 'SELECT color, name, alias, shape ';
                            $sqlcolor .= 'FROM *PREFIX*phonetrack_devices ';
                            $sqlcolor .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                            $sqlcolor .= 'AND id='.$this->db_quote_escape_string($devid).'; ';
                            $req = $this->dbconnection->prepare($sqlcolor);
                            $req->execute();
                            $col = '';
                            while ($row = $req->fetch()){
                                $col = $row['color'];
                                $shape = $row['shape'];
                                $name = $row['name'];
                                $alias = $row['alias'];
                            }
                            $req->closeCursor();
                            if (!array_key_exists($dbtoken, $shapes)) {
                                $shapes[$dbtoken] = array();
                            }
                            $shapes[$dbtoken][$devid] = $shape;
                            if (!array_key_exists($dbtoken, $colors)) {
                                $colors[$dbtoken] = array();
                            }
                            $colors[$dbtoken][$devid] = $col;
                            if (!array_key_exists($dbtoken, $names)) {
                                $names[$dbtoken] = array();
                            }
                            $names[$dbtoken][$devid] = $name;
                            if (!array_key_exists($dbtoken, $aliases)) {
                                $aliases[$dbtoken] = array();
                            }
                            $aliases[$dbtoken][$devid] = $alias;
                        }

                        $sqlget = 'SELECT id, deviceid, lat, lon, timestamp, accuracy, satellites,';
                        $sqlget .= ' altitude, batterylevel, useragent, speed, bearing FROM *PREFIX*phonetrack_points ';
                        $sqlget .= 'WHERE deviceid='.$this->db_quote_escape_string($devid).' ';
                        $sqlget .= $firstLastSQL;
                        $sqlget .= 'ORDER BY timestamp DESC LIMIT 1000';
                        $req = $this->dbconnection->prepare($sqlget);
                        $req->execute();
                        while ($row = $req->fetch()){
                            $entry = array(
                                intval($row['id']),
                                floatval($row['lat']),
                                floatval($row['lon']),
                                intval($row['timestamp']),
                                is_numeric($row['accuracy']) ? floatval($row['accuracy']) : null,
                                is_numeric($row['satellites']) ? intval($row['satellites']) : null,
                                is_numeric($row['altitude']) ? floatval($row['altitude']) : null,
                                is_numeric($row['batterylevel']) ? floatval($row['batterylevel']) : null,
                                $row['useragent'],
                                is_numeric($row['speed']) ? floatval($row['speed']) : null,
                                is_numeric($row['bearing']) ? floatval($row['bearing']) : null
                            );
                            array_unshift($resultDevArray, $entry);
                        }
                        $req->closeCursor();
                        if (count($resultDevArray) > 0) {
                            $result[$token][$devid] = $resultDevArray;
                        }
                        else {
                            // if device has no new point and no last time
                            // it means it was probably reserved : we don't give its name
                            if (!is_array($lastTime) or !array_key_exists($devid, $lastTime)) {
                                unset($names[$dbtoken][$devid]);
                                unset($aliases[$dbtoken][$devid]);
                                unset($colors[$dbtoken][$devid]);
                                unset($shapes[$dbtoken][$devid]);
                            }
                        }
                    }
                }
            }
        }

        $response = new DataResponse(
            [
                'sessions'=>$result,
                'colors'=>$colors,
                'shapes'=>$shapes,
                'names'=>$names,
                'aliases'=>$aliases
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
        $colors = array();
        $shapes = array();
        $names = array();
        $aliases = array();
        foreach ($sessions as $session) {
            $publicviewtoken = $session[0];
            $lastTime = $session[1];
            $firstTime = $session[2];
            $lastposonly = 0;
            $geofencify = 0;

            // check if session exists
            $dbtoken = null;
            $dbpublicviewtoken = null;
            $dbpublic = null;
            $filters = null;
            $deviceNameRestriction = '';
            $sqlget = 'SELECT publicviewtoken, token, public FROM *PREFIX*phonetrack_sessions ';
            $sqlget .= 'WHERE publicviewtoken='.$this->db_quote_escape_string($publicviewtoken).' ';
            $req = $this->dbconnection->prepare($sqlget);
            $req->execute();
            while ($row = $req->fetch()){
                $dbpublicviewtoken = $row['publicviewtoken'];
                $dbtoken = $row['token'];
                $dbpublic = intval($row['public']);
            }
            $req->closeCursor();
            if ($dbpublic !== 1) {
                $dbpublicviewtoken = null;
            }

            // there is no session with this publicviewtoken
            // check if there is a public share with the sharetoken
            if ($dbpublicviewtoken === null) {
                $sqlget = 'SELECT sharetoken, sessionid, filters, devicename, lastposonly, geofencify FROM *PREFIX*phonetrack_pubshares ';
                $sqlget .= 'WHERE sharetoken='.$this->db_quote_escape_string($publicviewtoken).' ';
                $req = $this->dbconnection->prepare($sqlget);
                $req->execute();
                while ($row = $req->fetch()){
                    $dbpublicviewtoken = $row['sharetoken'];
                    $dbtoken = $row['sessionid'];
                    $filters = json_decode($row['filters'], True);
                    $lastposonly = $row['lastposonly'];
                    $geofencify = $row['geofencify'];
                    if ($row['devicename'] !== null and $row['devicename'] !== '') {
                        $deviceNameRestriction = ' AND name='.$this->db_quote_escape_string($row['devicename']).' ';
                    }
                }
                $req->closeCursor();
            }

            // session exists and is public or shared by public share
            if ($dbpublicviewtoken !== null) {
                // get list of devices
                $devices = array();
                $sqldev = 'SELECT id FROM *PREFIX*phonetrack_devices ';
                $sqldev .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                $sqldev .= $deviceNameRestriction.' ;';
                $req = $this->dbconnection->prepare($sqldev);
                $req->execute();
                while ($row = $req->fetch()){
                    array_push($devices, intval($row['id']));
                }
                $req->closeCursor();

                // get the coords for each device
                $result[$dbpublicviewtoken] = array();

                foreach ($devices as $devid) {
                    $resultDevArray = array();

                    $firstDeviceTimeSQL = '';
                    if (is_array($firstTime) && array_key_exists($devid, $firstTime)) {
                        $firstDeviceTime = $firstTime[$devid];
                        $firstDeviceTimeSQL = 'timestamp<'.$this->db_quote_escape_string($firstDeviceTime);
                    }

                    $lastDeviceTime = 0;
                    $lastDeviceTimeSQL = '';
                    if (is_array($lastTime) && array_key_exists($devid, $lastTime)) {
                        $lastDeviceTime = $lastTime[$devid];
                        $lastDeviceTimeSQL = 'timestamp>'.$this->db_quote_escape_string($lastDeviceTime);
                    }
                    // build SQL condition for first/last
                    $firstLastSQL = '';
                    if ($firstDeviceTimeSQL !== '') {
                        if ($lastDeviceTimeSQL !== '') {
                            $firstLastSQL = 'AND ('.$firstDeviceTimeSQL.' OR '.$lastDeviceTimeSQL.') ';
                        }
                        else {
                            $firstLastSQL = 'AND '.$firstDeviceTimeSQL.' ';
                        }
                    }
                    else if ($lastDeviceTimeSQL !== '') {
                        $firstLastSQL = 'AND '.$lastDeviceTimeSQL.' ';
                    }
                    // we give color (first point given)
                    else {
                        $sqlcolor = 'SELECT color, name, alias, shape ';
                        $sqlcolor .= 'FROM *PREFIX*phonetrack_devices ';
                        $sqlcolor .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                        $sqlcolor .= 'AND id='.$this->db_quote_escape_string($devid).'; ';
                        $req = $this->dbconnection->prepare($sqlcolor);
                        $req->execute();
                        $col = '';
                        while ($row = $req->fetch()){
                            $col = $row['color'];
                            $shape = $row['shape'];
                            $name = $row['name'];
                            $alias = $row['alias'];
                        }
                        $req->closeCursor();
                        if (!array_key_exists($dbpublicviewtoken, $shapes)) {
                            $shapes[$dbpublicviewtoken] = array();
                        }
                        $shapes[$dbpublicviewtoken][$devid] = $shape;
                        if (!array_key_exists($dbpublicviewtoken, $colors)) {
                            $colors[$dbpublicviewtoken] = array();
                        }
                        $colors[$dbpublicviewtoken][$devid] = $col;
                        if (!array_key_exists($dbpublicviewtoken, $names)) {
                            $names[$dbpublicviewtoken] = array();
                        }
                        $names[$dbpublicviewtoken][$devid] = $name;
                        if (!array_key_exists($dbpublicviewtoken, $aliases)) {
                            $aliases[$dbpublicviewtoken] = array();
                        }
                        $aliases[$dbpublicviewtoken][$devid] = $alias;
                    }


                    $sqlget = 'SELECT id, deviceid, lat, lon, timestamp, accuracy, satellites, ';
                    $sqlget .= 'altitude, batterylevel, useragent, speed, bearing FROM *PREFIX*phonetrack_points ';
                    $sqlget .= 'WHERE deviceid='.$this->db_quote_escape_string($devid).' ';
                    $sqlget .= $firstLastSQL;
                    if (intval($lastposonly) === 0) {
                        $sqlget .= 'ORDER BY timestamp DESC LIMIT 1000 ;';
                    }
                    else {
                        $sqlget .= 'ORDER BY timestamp DESC LIMIT 1 ;';
                    }
                    $req = $this->dbconnection->prepare($sqlget);
                    $req->execute();
                    while ($row = $req->fetch()){
                        if ($filters === null or $this->filterPoint($row, $filters)) {
                            $entry = array(
                                intval($row['id']),
                                floatval($row['lat']),
                                floatval($row['lon']),
                                intval($row['timestamp']),
                                is_numeric($row['accuracy']) ? floatval($row['accuracy']) : null,
                                is_numeric($row['satellites']) ? intval($row['satellites']) : null,
                                is_numeric($row['altitude']) ? floatval($row['altitude']) : null,
                                is_numeric($row['batterylevel']) ? floatval($row['batterylevel']) : null,
                                $row['useragent'],
                                is_numeric($row['speed']) ? floatval($row['speed']) : null,
                                is_numeric($row['bearing']) ? floatval($row['bearing']) : null
                            );
                            array_unshift($resultDevArray, $entry);
                        }
                    }
                    $req->closeCursor();
                    if (count($resultDevArray) > 0) {
                        $result[$dbpublicviewtoken][$devid] = $resultDevArray;
                    }
                    else {
                        // if device has no new point and no last time
                        // it means it was probably reserved : we don't give its name
                        if (!is_array($lastTime) or !array_key_exists($devid, $lastTime)) {
                            unset($names[$dbpublicviewtoken][$devid]);
                            unset($aliases[$dbpublicviewtoken][$devid]);
                            unset($colors[$dbpublicviewtoken][$devid]);
                            unset($shapes[$dbpublicviewtoken][$devid]);
                        }
                    }
                }
                if (intval($geofencify) !== 0) {
                    $result[$dbpublicviewtoken] = $this->geofencify($dbtoken, $dbpublicviewtoken, $result[$dbpublicviewtoken]);
                }
            }
        }

        $response = new DataResponse(
            [
                'sessions'=>$result,
                'colors'=>$colors,
                'shapes'=>$shapes,
                'names'=>$names,
                'aliases'=>$aliases
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    private function getDeviceFencesCenter($devid) {
        $fences = array();
        $sqlget = 'SELECT latmin, lonmin, latmax, lonmax, name';
        $sqlget .= ' FROM *PREFIX*phonetrack_geofences ';
        $sqlget .= 'WHERE deviceid='.$this->db_quote_escape_string($devid).' ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            $lat = (floatval($row['latmin']) + floatval($row['latmax'])) / 2;
            $lon = (floatval($row['lonmin']) + floatval($row['lonmax'])) / 2;
            $fences[$row['name']] = array($lat, $lon, floatval($row['latmin']), floatval($row['latmax']), floatval($row['lonmin']), floatval($row['lonmax']));
        }
        return $fences;
    }

    private function geofencify($token, $ptk, $devtab) {
        $result = array();
        if (count($devtab) > 0) {
            foreach ($devtab as $devid => $entries) {
                $geofencesCenter = $this->getDeviceFencesCenter($devid);
                if (count($geofencesCenter) > 0) {
                    $result[$devid] = array();
                    foreach ($entries as $entry) {
                        $sentry = $this->geofencifyPoint($entry, $geofencesCenter);
                        if ($sentry !== null) {
                            array_push($result[$devid], $sentry);
                        }
                    }
                    if (count($result[$devid]) === 0) {
                        unset($result[$devid]);
                    }
                }
            }
        }
        return $result;
    }

    private function geofencifyPoint($entry, $geofencesCenter) {
        $nearestName = null;
        $distMin = null;
        foreach ($geofencesCenter as $name=>$coords) {
            // if point is inside geofencing zone
            if (    $entry[1] >= $coords[2]
                and $entry[1] <= $coords[3]
                and $entry[2] >= $coords[4]
                and $entry[2] <= $coords[5]
            ) {
                $dist = distance($coords[0], $coords[1], $entry[1], $entry[2]);
                if ($nearestName === null or $dist < $distMin) {
                    $distMin = $dist;
                    $nearestName = $name;
                }
            }
        }
        if ($nearestName !== null) {
            return array($entry[0], $geofencesCenter[$nearestName][0], $geofencesCenter[$nearestName][1], $entry[3], null, null, null, null, null, null, null);
        }
        else {
            return null;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     **/
    public function publicSessionWatch($publicviewtoken) {
        if ($publicviewtoken !== '') {
            $lastposonly = 0;
            // check if a public session has this publicviewtoken
            $sqlchk = 'SELECT token, public  FROM *PREFIX*phonetrack_sessions ';
            $sqlchk .= 'WHERE publicviewtoken='.$this->db_quote_escape_string($publicviewtoken).' ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbtoken = null;
            $dbpublic = null;
            while ($row = $req->fetch()){
                $dbtoken = $row['token'];
                $dbpublic = intval($row['public']);
                break;
            }
            $req->closeCursor();

            if ($dbtoken !== null and $dbpublic === 1) {
                // we give publicWebLog the real session id but then, the share token is used in the JS
                return $this->publicWebLog($dbtoken, '');
            }
            else {
                // check if a public session has this publicviewtoken
                $sqlchk = 'SELECT sessionid, sharetoken, lastposonly, filters  FROM *PREFIX*phonetrack_pubshares ';
                $sqlchk .= 'WHERE sharetoken='.$this->db_quote_escape_string($publicviewtoken).' ';
                $req = $this->dbconnection->prepare($sqlchk);
                $req->execute();
                $dbtoken = null;
                $dbpublic = null;
                $filters = '';
                while ($row = $req->fetch()){
                    $dbtoken = $row['sessionid'];
                    $lastposonly = $row['lastposonly'];
                    $filters = $row['filters'];
                    break;
                }
                $req->closeCursor();

                if ($dbtoken !== null) {
                    // we give publicWebLog the real session id but then, the share token is used in the JS
                    return $this->publicWebLog($dbtoken, '', $lastposonly, $filters);
                }
                else {
                    return 'Session does not exist or is not public';
                }
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
     *
     * lastposonly is given to the page, it makes the page delete all points but the last for each device
     **/
    public function publicWebLog($token, $devicename, $lastposonly=0, $filters='') {
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
        if (!isset($baseTileServers) ) {
            $baseTileServers = '';
        }
        $params = [
            'username'=>'',
            'basetileservers'=>$baseTileServers,
            'usertileservers'=>[],
            'useroverlayservers'=>[],
            'usertileserverswms'=>[],
            'useroverlayserverswms'=>[],
            'publicsessionname'=>$dbname,
            'lastposonly'=>$lastposonly,
            'sharefilters'=>$filters,
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
        $devices = [];
        if ($done === 1) {
            $devices = $this->getDevices($token);
        }

        $response = new DataResponse(
            [
                'done'=>$done,
                'token'=>$token,
                'devices'=>$devices,
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
            error_log('Exception in '.$file->getName().' gpx parsing : '.$e->getMessage());
            return 5;
        }

        if (count($gpx->trk) === 0){
            error_log('Nothing to parse in '.$file->getName().' gpx file');
            return 6;
        }

        $trackIndex = 1;
        foreach($gpx->trk as $track){
            $points = array();
            $devicename = str_replace("\n", '', $track->name);
            if (empty($devicename)){
                $devicename = 'device '.$trackIndex;
            }
            $devicename = str_replace('"', "'", $devicename);

            foreach($track->trkseg as $segment) {
                foreach($segment->trkpt as $point) {
                    $lat = floatval($point['lat']);
                    $lon = floatval($point['lon']);
                    $acc = null;
                    $bat = null;
                    $sat = null;
                    $ua  = '';
                    $speed = null;
                    $bearing = null;
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
                        $ele = floatval($point->ele);
                    }
                    if (!empty($point->speed)) {
                        $speed = floatval($point->speed);
                    }
                    if (!empty($point->course)) {
                        $bearing = floatval($point->course);
                    }
                    if (!empty($point->sat)) {
                        $sat = intval($point->sat);
                    }
                    if (!empty($point->extensions)) {
                        if (!empty($point->extensions->useragent)) {
                            $ua = $point->extensions->useragent;
                        }
                        if (!empty($point->extensions->batterylevel)) {
                            $bat = floatval($point->extensions->batterylevel);
                        }
                        if (!empty($point->extensions->accuracy)) {
                            $acc = floatval($point->extensions->accuracy);
                        }
                    }
                    if (!is_null($lat) and $lat !== '' and
                        !is_null($lon) and $lon !== '' and
                        !is_null($timestamp) and $timestamp !== ''
                    ) {
                        array_push($points, array($lat, $lon, $ele, $timestamp, $acc, $bat, $sat, $ua, $speed, $bearing));
                    }
                }
            }
            if (count($points) > 0) {
                $this->logMultiple($token, $devicename, $points);
            }
            $trackIndex++;
        }

        return 1;
    }

    /**
     * @NoAdminRequired
     */
    public function export($name, $token, $target, $username='', $filterArray=null) {
        date_default_timezone_set('UTC');
        $done = false;
        $userFolder = null;
        // user is logged in
        if ($this->userId !== null and $this->userId !== '') {
            $userFolder = \OC::$server->getUserFolder($this->userId);
            $userId = $this->userId;
        }
        // automatic export is done by system, username is manually given
        else if ($username !== ''){
            $userFolder = \OC::$server->getUserFolder($username);
            $userId = $username;
        }
        // get options to know if we should export one file per device
        $oneFilePerDevice = false;
        $sqlget = 'SELECT * FROM *PREFIX*phonetrack_options ';
        $sqlget .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($userId).' ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        $optString = null;
        while ($row = $req->fetch()){
            $optString = $row['jsonvalues'];
        }
        if ($optString !== null) {
            $f = json_decode($optString);
            if (isset($f->{'exportoneperdev'})) {
                $oneFilePerDevice = $f->{'exportoneperdev'};
            }
        }

        $path = $target;
        $cleanpath = str_replace(array('../', '..\\'), '',  $path);

        if ($userFolder !== null) {
            $file = null;
            $filePossible = false;
            $dirpath = dirname($cleanpath);
            $newFileName = basename($cleanpath);
            if ($oneFilePerDevice) {
                if ($userFolder->nodeExists($dirpath)){
                    $dir = $userFolder->get($dirpath);
                    if ($dir->getType() === \OCP\Files\FileInfo::TYPE_FOLDER and
                        $dir->isCreatable()){
                        $filePossible = true;
                    }
                }
            }
            else {
                if ($userFolder->nodeExists($cleanpath)){
                    $dir = $userFolder->get($dirpath);
                    $file = $userFolder->get($cleanpath);
                    if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                        $file->isUpdateable()){
                        $filePossible = true;
                    }
                }
                else{
                    if ($userFolder->nodeExists($dirpath)){
                        $dir = $userFolder->get($dirpath);
                        if ($dir->getType() === \OCP\Files\FileInfo::TYPE_FOLDER and
                            $dir->isCreatable()){
                            $filePossible = true;
                        }
                    }
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
                    $sqlget .= 'AND username='.$this->db_quote_escape_string($userId).';';
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
                    $sqldev = 'SELECT id, name FROM *PREFIX*phonetrack_devices ';
                    $sqldev .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ;';
                    $req = $this->dbconnection->prepare($sqldev);
                    $req->execute();
                    while ($row = $req->fetch()){
                        array_push($devices, array($row['id'], $row['name']));
                    }
                    $req->closeCursor();

                    // get the coords for each device
                    $result[$name] = array();

                    // get filters
                    if ($filterArray === null) {
                        $filterArray = $this->getCurrentFilters($userId);
                    }
                    $filterSql = $this->getSqlFilter($filterArray);

                    foreach ($devices as $d) {
                        $devid = $d[0];
                        $devname = $d[1];
                        $coords[$devname] = $this->getDeviceCoords($devid, $devname, $filterSql);
                        // generate a file for this device
                        if ($oneFilePerDevice) {
                            $devCoords = array($devname => $coords[$devname]);
                            $gpxContent = $this->generateGpx($name, $devCoords);
                            // generate file name for this device
                            $devFileName = str_replace(array('.gpx', '.GPX'), '_'.$devname.'.gpx',  $newFileName);
                            if (! $dir->nodeExists($devFileName)) {
                                $dir->newFile($devFileName);
                            }
                            $file = $dir->get($devFileName);
                            if ($file->isUpdateable()) {
                                $file->putContent($gpxContent);
                            }
                        }
                    }
                    // one file for the whole session
                    if (!$oneFilePerDevice) {
                        $gpxContent = $this->generateGpx($name, $coords);
                        if (! $dir->nodeExists($newFileName)) {
                            $dir->newFile($newFileName);
                        }
                        $file = $dir->get($newFileName);
                        $file->putContent($gpxContent);
                    }
                    $done = true;
                }
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

    private function getDeviceCoords($devid, $devname, $filterSql) {
        $res = array();
        $sqlget = 'SELECT * FROM *PREFIX*phonetrack_points ';
        $sqlget .= 'WHERE deviceid='.$this->db_quote_escape_string($devid).' ';
        if ($filterSql !== '') {
            $sqlget .= 'AND '.$filterSql;
        }
        $sqlget .= ' ORDER BY timestamp ASC ;';
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
            $acc = $row['accuracy'];
            $bat = $row['batterylevel'];
            $ua  = $row['useragent'];
            $sat = $row['satellites'];
            $speed = $row['speed'];
            $bearing = $row['bearing'];

            $point = array($lat, $lon, $date, $alt, $acc, $sat, $bat, $ua, $speed, $bearing);
            array_push($res, $point);
        }
        $req->closeCursor();

        return $res;
    }

    private function getCurrentFilters($username='') {
        $userId = $this->userId;
        if ($username !== '') {
            $userId = $username;
        }
        $fArray = null;
        $sqlget = 'SELECT * FROM *PREFIX*phonetrack_options ';
        $sqlget .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($userId).' ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        $optString = null;
        while ($row = $req->fetch()){
            $optString = $row['jsonvalues'];
        }
        if ($optString !== null) {
            $f = json_decode($optString);
            if (isset($f->{'applyfilters'}) and $f->{'applyfilters'} === true) {
                $fArray = array();
                if (isset($f->{'datemin'}) and $f->{'datemin'} !== '') {
                    $hourmin = (isset($f->{'hourmin'}) and $f->{'hourmin'} !== '') ? intval($f->{'hourmin'}) : 0;
                    $minutemin = (isset($f->{'minutemin'}) and $f->{'minutemin'} !== '') ? intval($f->{'minutemin'}) : 0;
                    $secondmin = (isset($f->{'secondmin'}) and $f->{'secondmin'} !== '') ? intval($f->{'secondmin'}) : 0;
                    $fArray['tsmin'] = intval($f->{'datemin'}) + 3600*$hourmin + 60*$minutemin + $secondmin;
                }
                else {
                    if (    isset($f->{'hourmin'}) and $f->{'hourmin'} !== ''
                        and isset($f->{'minutemin'}) and $f->{'minutemin'} !== ''
                        and isset($f->{'secondmin'}) and $f->{'secondmin'} !== ''
                    ) {
                        date_default_timezone_set(ini_get('date.timezone'));
                        $now = new \DateTime();
                        $y = $now->format('Y');
                        $m = $now->format('m');
                        $d = $now->format('d');
                        $h = intval($f->{'hourmin'});
                        $mi = intval($f->{'minutemin'});
                        $s = intval($f->{'secondmin'});
                        $dmin = new \DateTime($y.'-'.$m.'-'.$d.' '.$h.':'.$mi.':'.$s);
                        $fArray['tsmin'] = $dmin->getTimestamp();
                    }
                }
                if (isset($f->{'datemax'}) and $f->{'datemax'} !== '') {
                    $hourmax = (isset($f->{'hourmax'}) and $f->{'hourmax'} !== '') ? intval($f->{'hourmax'}) : 23;
                    $minutemax = (isset($f->{'minutemax'}) and $f->{'minutemax'} !== '') ? intval($f->{'minutemax'}) : 59;
                    $secondmax = (isset($f->{'secondmax'}) and $f->{'secondmax'} !== '') ? intval($f->{'secondmax'}) : 59;
                    $fArray['tsmax'] = intval($f->{'datemax'}) + 3600*$hourmax + 60*$minutemax + $secondmax;
                }
                else {
                    if (    isset($f->{'hourmax'}) and $f->{'hourmax'} !== ''
                        and isset($f->{'minutemax'}) and $f->{'minutemax'} !== ''
                        and isset($f->{'secondmax'}) and $f->{'secondmax'} !== ''
                    ) {
                        date_default_timezone_set(ini_get('date.timezone'));
                        $now = new \DateTime();
                        $y = $now->format('Y');
                        $m = $now->format('m');
                        $d = $now->format('d');
                        $h = intval($f->{'hourmax'});
                        $mi = intval($f->{'minutemax'});
                        $s = intval($f->{'secondmax'});
                        $dmax = new \DateTime($y.'-'.$m.'-'.$d.' '.$h.':'.$mi.':'.$s);
                        $fArray['tsmax'] = $dmax->getTimestamp();
                    }
                }
                date_default_timezone_set('UTC');
                $lastTS = new \DateTime();
                $lastTS = $lastTS->getTimestamp();
                $lastTSset = false;
                if (isset($f->{'lastdays'}) and $f->{'lastdays'} !== '') {
                    $lastTS = $lastTS - 24*3600*intval($f->{'lastdays'});
                    $lastTSset = true;
                }
                if (isset($f->{'lasthours'}) and $f->{'lasthours'} !== '') {
                    $lastTS = $lastTS - 3600*intval($f->{'lasthours'});
                    $lastTSset = true;
                }
                if (isset($f->{'lastmins'}) and $f->{'lastmins'} !== '') {
                    $lastTS = $lastTS - 60*intval($f->{'lastmins'});
                    $lastTSset = true;
                }
                if ($lastTSset and (!isset($fArray['tsmin']) or $lastTS > $fArray['tsmin'])) {
                    $fArray['tsmin'] = $lastTS;
                }
                foreach (['elevationmin', 'elevationmax', 'accuracymin', 'accuracymax', 'satellitesmin', 'satellitesmax', 'batterymin', 'batterymax', 'speedmax', 'speedmin', 'bearingmax', 'bearingmin', 'lastdays', 'lasthours', 'lastmins'] as $k) {
                    if (isset($f->{$k}) and $f->{$k} !== '') {
                        $fArray[$k] = intval($f->{$k});
                    }
                }
            }
        }

        return $fArray;
    }

    private function filterPoint($p, $fArray) {
        return (
                (!isset($fArray['tsmin']) or intval($p['timestamp']) >= $fArray['tsmin'])
            and (!isset($fArray['tsmax']) or intval($p['timestamp']) <= $fArray['tsmax'])
            and (!isset($fArray['elevationmax']) or intval($p['altitude']) <= $fArray['elevationmax'])
            and (!isset($fArray['elevationmin']) or intval($p['altitude']) >= $fArray['elevationmin'])
            and (!isset($fArray['accuracymax']) or intval($p['accuracy']) <= $fArray['accuracymax'])
            and (!isset($fArray['accuracymin']) or intval($p['accuracy']) >= $fArray['accuracymin'])
            and (!isset($fArray['satellitesmax']) or intval($p['satellites']) <= $fArray['satellitesmax'])
            and (!isset($fArray['satellitesmin']) or intval($p['satellites']) >= $fArray['satellitesmin'])
            and (!isset($fArray['batterymax']) or intval($p['batterylevel']) <= $fArray['batterymax'])
            and (!isset($fArray['batterymin']) or intval($p['batterylevel']) >= $fArray['batterymin'])
            and (!isset($fArray['speedmax']) or floatval($p['speed']) <= $fArray['speedmax'])
            and (!isset($fArray['speedmin']) or floatval($p['speed']) >= $fArray['speedmin'])
            and (!isset($fArray['bearingmax']) or floatval($p['bearing']) <= $fArray['bearingmax'])
            and (!isset($fArray['bearingmin']) or floatval($p['bearing']) >= $fArray['bearingmin'])
        );
    }

    private function getSqlFilter($fArray) {
        $sql = '';
        if ($fArray !== null) {
            $cond = array();
            if (isset($fArray['tsmin'])) { array_push($cond, 'timestamp >= '.$this->db_quote_escape_string($fArray['tsmin'])); }
            if (isset($fArray['tsmax'])) { array_push($cond, 'timestamp <= '.$this->db_quote_escape_string($fArray['tsmax'])); }
            if (isset($fArray['elevationmax'])) { array_push($cond, 'altitude <= '.$this->db_quote_escape_string($fArray['elevationmax'])); }
            if (isset($fArray['elevationmin'])) { array_push($cond, 'altitude >= '.$this->db_quote_escape_string($fArray['elvationmin'])); }
            if (isset($fArray['accuracymax'])) { array_push($cond, 'accuracy <= '.$this->db_quote_escape_string($fArray['accuracymax'])); }
            if (isset($fArray['accuracymin'])) { array_push($cond, 'accuracy >= '.$this->db_quote_escape_string($fArray['accuracymin'])); }
            if (isset($fArray['satellitesmax'])) { array_push($cond, 'satellites <= '.$this->db_quote_escape_string($fArray['satellitesmax'])); }
            if (isset($fArray['satellitesmin'])) { array_push($cond, 'satellites >= '.$this->db_quote_escape_string($fArray['satellitesmin'])); }
            if (isset($fArray['batterymax'])) { array_push($cond, 'batterylevel <= '.$this->db_quote_escape_string($fArray['batterymax'])); }
            if (isset($fArray['batterymin'])) { array_push($cond, 'batterylevel >= '.$this->db_quote_escape_string($fArray['batterymin'])); }
            if (isset($fArray['speedmax'])) { array_push($cond, 'speed <= '.$this->db_quote_escape_string($fArray['speedmax'])); }
            if (isset($fArray['speedmin'])) { array_push($cond, 'speed >= '.$this->db_quote_escape_string($fArray['speedmin'])); }
            if (isset($fArray['bearingmax'])) { array_push($cond, 'bearing <= '.$this->db_quote_escape_string($fArray['bearingmax'])); }
            if (isset($fArray['bearingmin'])) { array_push($cond, 'bearing >= '.$this->db_quote_escape_string($fArray['bearingmin'])); }
            $sql = implode(' AND ', $cond);
        }
        return $sql;
    }

    private function generateGpx($name, $coords) {
        date_default_timezone_set('UTC');
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
                $alt = $point[3];
                $acc = $point[4];
                $sat = $point[5];
                $bat = $point[6];
                $ua = $point[7];
                $speed = $point[8];
                $bearing = $point[9];
                $gpxExtension = '';
                $gpxText .= '  <trkpt lat="'.$point[0].'" lon="'.$point[1].'">' . "\n";
                $gpxText .= '   <time>' . $point[2] . '</time>' . "\n";
                if (is_numeric($alt)) {
                    $gpxText .= '   <ele>' . sprintf('%.2f', floatval($alt)) . '</ele>' . "\n";
                }
                if (is_numeric($speed) && floatval($speed) >= 0) {
                    $gpxText .= '   <speed>' . sprintf('%.3f', floatval($speed)) . '</speed>' . "\n";
                }
                if (is_numeric($bearing) && floatval($bearing) >= 0 && floatval($bearing) <= 360) {
                    $gpxText .= '   <course>' . sprintf('%.3f', floatval($bearing)) . '</course>' . "\n";
                }
                if (is_numeric($sat) && intval($sat) >= 0) {
                    $gpxText .= '   <sat>' . intval($sat) . '</sat>' . "\n";
                }
                if (is_numeric($acc) && intval($acc) >= 0) {
                    $gpxExtension .= '     <accuracy>' . sprintf('%.2f', floatval($acc)) . '</accuracy>' . "\n";
                }
                if (is_numeric($bat) && intval($bat) >= 0) {
                    $gpxExtension .= '     <batterylevel>' . sprintf('%.2f', floatval($bat)) . '</batterylevel>' . "\n";
                }
                if ($ua !== '') {
                    $gpxExtension .= '     <useragent>' . $ua . '</useragent>' . "\n";
                }
                if ($gpxExtension !== '') {
                    $gpxText .= '   <extensions>'. "\n" . $gpxExtension;
                    $gpxText .= '   </extensions>' . "\n";
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
        // check if username exists
        $userNames = [];
        foreach($this->userManager->search('') as $u) {
            if ($u->getUID() !== $this->userId) {
                array_push($userNames, $u->getUID());
            }
        }
        if ($username !== '' and in_array($username, $userNames)) {
            // check if session exists and owned by current user
            $sqlchk = 'SELECT name, token FROM *PREFIX*phonetrack_sessions ';
            $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
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

            if ($token !== '' and $dbname !== null) {
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
        }
        else {
            $ok = 4;
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
     * Used to build public tokens with filters (then accessed by publicWatchUrl)
     * @NoAdminRequired
     */
    public function addPublicShare($token) {
        $ok = 0;
        $filters = '';
        $sharetoken = '';
        // check if session exists and owned by current user
        $sqlchk = 'SELECT name, token FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
        $sqlchk .= 'AND token='.$this->db_quote_escape_string($token).' ';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbname = null;
        $dbtoken = null;
        $sharetoken = null;
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            $dbtoken = $row['token'];
            break;
        }
        $req->closeCursor();

        if ($dbname !== null) {
            $filters = '{}';
            $filterArray = $this->getCurrentFilters();
            if ($filterArray !== null) {
                $filters = json_encode($filterArray);
            }

            // determine share token
            $sharetoken = md5('share'.$this->userId.$dbname.rand());

            // insert
            $sql = 'INSERT INTO *PREFIX*phonetrack_pubshares';
            $sql .= ' (sessionid, sharetoken, filters) ';
            $sql .= 'VALUES (';
            $sql .= $this->db_quote_escape_string($dbtoken).',';
            $sql .= $this->db_quote_escape_string($sharetoken).',';
            $sql .= $this->db_quote_escape_string($filters);
            $sql .= ');';
            $req = $this->dbconnection->prepare($sql);
            $req->execute();
            $req->closeCursor();

            $ok = 1;
        }
        else {
            $ok = 3;
        }

        $response = new DataResponse(
            [
                'done'=>$ok,
                'sharetoken'=>$sharetoken,
                'filters'=>$filters
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
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
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

        if ($token !== '' and $dbname !== null) {
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

    /**
     * @NoAdminRequired
     */
    public function deletePublicShare($token, $sharetoken) {
        $ok = 0;
        // check if session exists
        $sqlchk = 'SELECT name, token FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
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
            // check if public share exists
            $sqlchk = 'SELECT sharetoken, sessionid FROM *PREFIX*phonetrack_pubshares ';
            $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
            $sqlchk .= 'AND sharetoken='.$this->db_quote_escape_string($sharetoken).' ';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbsharetoken = null;
            while ($row = $req->fetch()){
                $dbsharetoken = $row['sharetoken'];
                break;
            }
            $req->closeCursor();

            if ($dbsharetoken !== null) {
                // delete
                $sqldel = 'DELETE FROM *PREFIX*phonetrack_pubshares ';
                $sqldel .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                $sqldel .= 'AND sharetoken='.$this->db_quote_escape_string($dbsharetoken).';';
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

    /**
     * @NoAdminRequired
     */
    public function addNameReservation($token, $devicename) {
        $ok = 0;
        $nametoken = null;
        if ($devicename !== '' and $devicename !== null) {
            // check if session exists and owned by current user
            $sqlchk = 'SELECT name, token FROM *PREFIX*phonetrack_sessions ';
            $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
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
                // check if name reservation exists
                $sqlchk = 'SELECT name, sessionid, nametoken FROM *PREFIX*phonetrack_devices ';
                $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                $sqlchk .= 'AND name='.$this->db_quote_escape_string($devicename).' ';
                $req = $this->dbconnection->prepare($sqlchk);
                $req->execute();
                $dbdevicename = null;
                $dbdevicenametoken = null;
                while ($row = $req->fetch()){
                    $dbdevicename = $row['name'];
                    $dbdevicenametoken = $row['nametoken'];
                    break;
                }
                $req->closeCursor();

                // no entry in DB : we create it
                if ($dbdevicename === null) {
                    // determine name token
                    $nametoken = md5('nametoken'.$this->userId.$dbdevicename.rand());

                    // insert
                    $sql = 'INSERT INTO *PREFIX*phonetrack_devices';
                    $sql .= ' (sessionid, name, nametoken) ';
                    $sql .= 'VALUES (';
                    $sql .= $this->db_quote_escape_string($dbtoken).',';
                    $sql .= $this->db_quote_escape_string($devicename).',';
                    $sql .= $this->db_quote_escape_string($nametoken);
                    $sql .= ');';
                    $req = $this->dbconnection->prepare($sql);
                    $req->execute();
                    $req->closeCursor();

                    $ok = 1;
                }
                // if there is an entry but no token, name is free to be reserved
                // so we update the entry
                else if ($dbdevicenametoken === '' or $dbdevicenametoken === null) {
                    $nametoken = md5('nametoken'.$this->userId.$dbdevicename.rand());
                    $sqlupd = 'UPDATE *PREFIX*phonetrack_devices ';
                    $sqlupd .= 'SET nametoken='.$this->db_quote_escape_string($nametoken).' ';
                    $sqlupd .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                    $sqlupd .= 'AND name='.$this->db_quote_escape_string($dbdevicename).';';
                    $req = $this->dbconnection->prepare($sqlupd);
                    $req->execute();
                    $req->closeCursor();

                    $ok = 1;
                }
                // the name is already reserved
                else {
                    $ok = 2;
                }
            }
            else {
                $ok = 3;
            }
        }
        else {
            $ok = 4;
        }

        $response = new DataResponse(
            [
                'done'=>$ok,
                'nametoken'=>$nametoken
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
    public function deleteNameReservation($token, $devicename) {
        $ok = 0;
        if ($devicename !== '' and $devicename !== null) {
            // check if session exists
            $sqlchk = 'SELECT name, token FROM *PREFIX*phonetrack_sessions ';
            $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
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
                // check if name reservation exists
                $sqlchk = 'SELECT name, sessionid, nametoken FROM *PREFIX*phonetrack_devices ';
                $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                $sqlchk .= 'AND name='.$this->db_quote_escape_string($devicename).' ';
                $req = $this->dbconnection->prepare($sqlchk);
                $req->execute();
                $dbdevicename = null;
                $dbdevicenametoken = null;
                while ($row = $req->fetch()){
                    $dbdevicename = $row['name'];
                    $dbdevicenametoken = $row['nametoken'];
                    break;
                }
                $req->closeCursor();

                // there is no such device
                if ($dbdevicename === null) {
                    $ok = 2;
                }
                // the device exists and is has a nametoken
                else if ($dbdevicenametoken !== '' and $dbdevicenametoken !== null) {
                    // delete
                    $sqlupd = 'UPDATE *PREFIX*phonetrack_devices ';
                    $sqlupd .= 'SET nametoken='.$this->db_quote_escape_string('').' ';
                    $sqlupd .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                    $sqlupd .= 'AND name='.$this->db_quote_escape_string($dbdevicename).';';
                    $req = $this->dbconnection->prepare($sqlupd);
                    $req->execute();
                    $req->closeCursor();

                    $ok = 1;
                }
                else {
                    $ok = 3;
                }
            }
            else {
                $ok = 4;
            }
        }
        else {
            $ok = 5;
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

    private function sessionExists($token, $userid) {
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_sessions ';
        $sqlchk .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($userid).' ';
        $sqlchk .= 'AND token='.$this->db_quote_escape_string($token).' ';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbname = null;
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            break;
        }
        $req->closeCursor();

        return ($dbname !== null);
    }

    private function deviceExists($devid, $token) {
        $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_devices ';
        $sqlchk .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
        $sqlchk .= 'AND id='.$this->db_quote_escape_string($devid).' ';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbname = null;
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            break;
        }
        $req->closeCursor();

        return ($dbname !== null);
    }

    /**
     * @NoAdminRequired
     */
    public function addGeofence($token, $device, $fencename, $latmin, $latmax, $lonmin, $lonmax,
                                $urlenter, $urlleave, $urlenterpost, $urlleavepost, $sendemail) {
        $ok = 0;
        $fenceid = null;
        if ($this->sessionExists($token, $this->userId) and $this->deviceExists($device, $token)) {
            // check there is no fence with this name already
            $sqlchk = 'SELECT name FROM *PREFIX*phonetrack_geofences ';
            $sqlchk .= 'WHERE name='.$this->db_quote_escape_string($fencename).' ';
            $sqlchk .= 'AND deviceid='.$this->db_quote_escape_string($device).' ;';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbfencename = null;
            while ($row = $req->fetch()){
                $dbfencename = $row['name'];
                break;
            }
            $req->closeCursor();

            if ($dbfencename === null) {
                // insert
                $sql = 'INSERT INTO *PREFIX*phonetrack_geofences';
                $sql .= ' (name, deviceid, latmin, latmax, lonmin, lonmax, urlenter, urlleave, ';
                $sql .= 'urlenterpost, urlleavepost, sendemail) ';
                $sql .= 'VALUES (';
                $sql .= $this->db_quote_escape_string($fencename).',';
                $sql .= $this->db_quote_escape_string($device).',';
                $sql .= $this->db_quote_escape_string(floatval($latmin)).',';
                $sql .= $this->db_quote_escape_string(floatval($latmax)).',';
                $sql .= $this->db_quote_escape_string(floatval($lonmin)).',';
                $sql .= $this->db_quote_escape_string(floatval($lonmax)).',';
                $sql .= $this->db_quote_escape_string($urlenter).',';
                $sql .= $this->db_quote_escape_string($urlleave).',';
                $sql .= $this->db_quote_escape_string(intval($urlenterpost)).',';
                $sql .= $this->db_quote_escape_string(intval($urlleavepost)).',';
                $sql .= $this->db_quote_escape_string(intval($sendemail));
                $sql .= ');';
                $req = $this->dbconnection->prepare($sql);
                $req->execute();
                $req->closeCursor();

                $sqlchk = 'SELECT id FROM *PREFIX*phonetrack_geofences ';
                $sqlchk .= 'WHERE name='.$this->db_quote_escape_string($fencename).' ';
                $sqlchk .= 'AND deviceid='.$this->db_quote_escape_string($device).' ;';
                $req = $this->dbconnection->prepare($sqlchk);
                $req->execute();
                while ($row = $req->fetch()){
                    $fenceid = $row['id'];
                    break;
                }
                $req->closeCursor();

                $user = $this->userManager->get($this->userId);
                $userEmail = $user->getEMailAddress();
                $mailFromA = $this->config->getSystemValue('mail_from_address');
                $mailFromD = $this->config->getSystemValue('mail_domain');
                if (!empty($mailFromA) and !empty($mailFromD) and !empty($userEmail)) {
                    $ok = 1;
                }
                else {
                    $ok = 4;
                }
            }
            else {
                $ok = 3;
            }
        }
        else {
            $ok = 2;
        }

        $response = new DataResponse(
            [
                'done'=>$ok,
                'fenceid'=>$fenceid
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
    public function addProxim($token, $device, $sid, $dname, $lowlimit, $highlimit,
                                $urlclose, $urlfar, $urlclosepost, $urlfarpost, $sendemail) {
        $ok = 0;
        $proximid = null;
        $targetDeviceId = null;
        if ($this->sessionExists($token, $this->userId) and $this->deviceExists($device, $token)) {
            // check if target session id is owned by current user or if it's shared with him/her
            $targetSessionId = null;
            $ownsTargetSession = $this->sessionExists($sid, $this->userId);
            if ($ownsTargetSession) {
                $targetSessionId = $sid;
            }
            else {
                $sqlchk = 'SELECT id, sessionid, sharetoken FROM *PREFIX*phonetrack_shares ';
                $sqlchk .= 'WHERE username='.$this->db_quote_escape_string($this->userId).' ';
                $sqlchk .= 'AND sharetoken='.$this->db_quote_escape_string($sid).' ;';
                $req = $this->dbconnection->prepare($sqlchk);
                $req->execute();
                while ($row = $req->fetch()){
                    $targetSessionId = $row['sessionid'];
                    break;
                }
                $req->closeCursor();
            }

            if ($targetSessionId !== null) {
                // check if there is a device named like that in target session
                $sqlchk = 'SELECT id FROM *PREFIX*phonetrack_devices ';
                $sqlchk .= 'WHERE name='.$this->db_quote_escape_string($dname).' ';
                $sqlchk .= 'AND sessionid='.$this->db_quote_escape_string($targetSessionId).' ;';
                $req = $this->dbconnection->prepare($sqlchk);
                $req->execute();
                while ($row = $req->fetch()){
                    $targetDeviceId = $row['id'];
                    break;
                }
                $req->closeCursor();

                if ($targetDeviceId !== null) {
                    // insert
                    $sql = 'INSERT INTO *PREFIX*phonetrack_proxims';
                    $sql .= ' (deviceid1, deviceid2, lowlimit, highlimit, urlclose, urlfar, ';
                    $sql .= 'urlclosepost, urlfarpost, sendemail) ';
                    $sql .= 'VALUES (';
                    $sql .= $this->db_quote_escape_string($device).',';
                    $sql .= $this->db_quote_escape_string($targetDeviceId).',';
                    $sql .= $this->db_quote_escape_string(intval($lowlimit)).',';
                    $sql .= $this->db_quote_escape_string(intval($highlimit)).',';
                    $sql .= $this->db_quote_escape_string($urlclose).',';
                    $sql .= $this->db_quote_escape_string($urlfar).',';
                    $sql .= $this->db_quote_escape_string(intval($urlclosepost)).',';
                    $sql .= $this->db_quote_escape_string(intval($urlfarpost)).',';
                    $sql .= $this->db_quote_escape_string(intval($sendemail));
                    $sql .= ');';
                    $req = $this->dbconnection->prepare($sql);
                    $req->execute();
                    $req->closeCursor();

                    $sqlchk = 'SELECT MAX(id) as maxid FROM *PREFIX*phonetrack_proxims ';
                    $sqlchk .= 'WHERE deviceid1='.$this->db_quote_escape_string($device).' ';
                    $sqlchk .= 'AND deviceid2='.$this->db_quote_escape_string($targetDeviceId).' ;';
                    $req = $this->dbconnection->prepare($sqlchk);
                    $req->execute();
                    while ($row = $req->fetch()){
                        $proximid = $row['maxid'];
                        break;
                    }
                    $req->closeCursor();

                    $user = $this->userManager->get($this->userId);
                    $userEmail = $user->getEMailAddress();
                    $mailFromA = $this->config->getSystemValue('mail_from_address');
                    $mailFromD = $this->config->getSystemValue('mail_domain');
                    if (!empty($mailFromA) and !empty($mailFromD) and !empty($userEmail)) {
                        $ok = 1;
                    }
                    else {
                        $ok = 4;
                    }
                }
                else {
                    $ok = 5;
                }
            }
            else {
                $ok = 3;
            }
        }
        else {
            $ok = 2;
        }

        $response = new DataResponse(
            [
                'done'=>$ok,
                'proximid'=>$proximid,
                'targetdeviceid'=>$targetDeviceId
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
    public function deleteProxim($token, $device, $proximid) {
        $ok = 0;
        if ($this->sessionExists($token, $this->userId) and $this->deviceExists($device, $token)) {
            $dbproximid = null;
            $sqlchk = 'SELECT id, deviceid1 FROM *PREFIX*phonetrack_proxims ';
            $sqlchk .= 'WHERE id='.$this->db_quote_escape_string($proximid).' ';
            $sqlchk .= 'AND deviceid1='.$this->db_quote_escape_string($device).' ;';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            while ($row = $req->fetch()){
                $dbproximid = $row['id'];
                break;
            }
            $req->closeCursor();

            if ($dbproximid !== null) {
                $sqldel = 'DELETE FROM *PREFIX*phonetrack_proxims ';
                $sqldel .= 'WHERE id='.$this->db_quote_escape_string($dbproximid).' ';
                $sqldel .= 'AND deviceid1='.$this->db_quote_escape_string($device).' ;';
                $req = $this->dbconnection->prepare($sqldel);
                $req->execute();
                $req->closeCursor();

                $ok = 1;
            }
        }
        else {
            $ok = 2;
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
    public function deleteGeofence($token, $device, $fenceid) {
        $ok = 0;
        if ($this->sessionExists($token, $this->userId) and $this->deviceExists($device, $token)) {
            $sqldel = 'DELETE FROM *PREFIX*phonetrack_geofences ';
            $sqldel .= 'WHERE deviceid='.$this->db_quote_escape_string($device).' ';
            $sqldel .= 'AND id='.$this->db_quote_escape_string($fenceid).' ;';
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
    public function getUserList() {
        $userNames = [];
        foreach($this->userManager->search('') as $u) {
            if ($u->getUID() !== $this->userId) {
                array_push($userNames, $u->getUID());
            }
        }
        $response = new DataResponse(
            [
                'users'=>$userNames
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
     *
     **/
    private function logMultiple($token, $devicename, $points) {
        $done = 0;
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
            $dbdeviceid = null;
            $sqlgetres = 'SELECT id, name FROM *PREFIX*phonetrack_devices ';
            $sqlgetres .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
            $sqlgetres .= 'AND name='.$this->db_quote_escape_string($devicename).' ;';
            $req = $this->dbconnection->prepare($sqlgetres);
            $req->execute();
            while ($row = $req->fetch()){
                $dbdeviceid = $row['id'];
                $dbdevicename = $row['name'];
            }
            $req->closeCursor();

            if ($dbdeviceid === null) {
                // device does not exist and there is no reservation corresponding
                // => we create it
                $sql = 'INSERT INTO *PREFIX*phonetrack_devices';
                $sql .= ' (name, sessionid) ';
                $sql .= 'VALUES (';
                $sql .= $this->db_quote_escape_string($devicename).',';
                $sql .= $this->db_quote_escape_string($token);
                $sql .= ');';
                $req = $this->dbconnection->prepare($sql);
                $req->execute();
                $req->closeCursor();

                // get the newly created device id
                $sqlgetdid = 'SELECT id FROM *PREFIX*phonetrack_devices ';
                $sqlgetdid .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ';
                $sqlgetdid .= 'AND name='.$this->db_quote_escape_string($devicename).' ;';
                $req = $this->dbconnection->prepare($sqlgetdid);
                $req->execute();
                while ($row = $req->fetch()){
                    $dbdeviceid = $row['id'];
                }
                $req->closeCursor();
            }

            $valuesStrings = array();
            foreach ($points as $point) {
                $lat = $point[0];
                $lon = $point[1];
                $alt = $point[2];
                $timestamp = $point[3];
                $acc = $point[4];
                $bat = $point[5];
                $sat = $point[6];
                $useragent = $point[7];
                $speed = $point[8];
                $bearing = $point[9];
                // correct timestamp if needed
                $time = $timestamp;
                if (is_numeric($time)) {
                    $time = floatval($time);
                    if ($time > 10000000000.0) {
                        $time = $time / 1000;
                    }
                }

                if (is_numeric($acc)) {
                    $acc = sprintf('%.2f', floatval($acc));
                }

                $oneVal = '(';
                $oneVal .= $this->db_quote_escape_string($dbdeviceid).',';
                $oneVal .= $this->db_quote_escape_string($lat).',';
                $oneVal .= $this->db_quote_escape_string($lon).',';
                $oneVal .= $this->db_quote_escape_string($time).',';
                $oneVal .= (is_numeric($acc) ? $this->db_quote_escape_string($acc) : 'NULL').',';
                $oneVal .= (is_numeric($sat) ? $this->db_quote_escape_string($sat) : 'NULL').',';
                $oneVal .= (is_numeric($alt) ? $this->db_quote_escape_string($alt) : 'NULL').',';
                $oneVal .= (is_numeric($bat) ? $this->db_quote_escape_string($bat) : 'NULL').',';
                $oneVal .= $this->db_quote_escape_string($useragent).',';
                $oneVal .= (is_numeric($speed) ? $this->db_quote_escape_string($speed) : 'NULL').',';
                $oneVal .= (is_numeric($bearing) ? $this->db_quote_escape_string($bearing) : 'NULL').') ';

                array_push($valuesStrings, $oneVal);
            }

            // insert by packets of 50
            while ($valuesStrings !== null and count($valuesStrings) > 0) {
                $c = 0;
                $values = '';
                if ($valuesStrings !== null and count($valuesStrings) > 0) {
                    $values .= array_shift($valuesStrings);
                    $c++;
                }
                while ($valuesStrings !== null and count($valuesStrings) > 0 and $c < 500) {
                    $values .= ', '.array_shift($valuesStrings);
                    $c++;
                }

                $sql = 'INSERT INTO *PREFIX*phonetrack_points';
                $sql .= ' (deviceid, lat, lon, timestamp, accuracy, satellites, altitude, batterylevel, useragent, speed, bearing) VALUES '.$values.';';
                $req = $this->dbconnection->prepare($sql);
                $req->execute();
                $req->closeCursor();
            }

            $this->logger->error("Device imported from GPX", array('app' => $this->appName));
            $done = 1;
        }
        else {
            $done = 3;
        }
        return $done;
    }

    private function getOrCreateExportDir($userId) {
        $dir = null;
        $userFolder = \OC::$server->getUserFolder($userId);

        $sqlget = 'SELECT * FROM *PREFIX*phonetrack_options ';
        $sqlget .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($userId).' ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        $optString = null;
        while ($row = $req->fetch()){
            $optString = $row['jsonvalues'];
        }
        if ($optString !== null) {
            $f = json_decode($optString, True);
            if (isset($f['autoexportpath'])) {
                $dirpath = $f['autoexportpath'];
            }
            else {
                $dirpath = '/PhoneTrack_export';
            }

            if ($userFolder->nodeExists($dirpath)){
                $tmp = $userFolder->get($dirpath);
                if ($tmp->getType() === \OCP\Files\FileInfo::TYPE_FOLDER and
                    $tmp->isCreatable()){
                    $dir = $tmp;
                }
            }
            else {
                $userFolder->newFolder($dirpath);
                $dir = $userFolder->get($dirpath);
            }
        }
        return $dir;
    }

    private function cronAutoPurge() {
        date_default_timezone_set('UTC');
        foreach (array('day'=>'1', 'week'=>'7', 'month'=>'31') as $s => $nbDays) {
            $now = new \DateTime();
            $now->modify('-'.$nbDays.' day');
            $ts = $now->getTimestamp();

            // get all sessions with this auto purge value
            $sessions = array();
            $sqlget = 'SELECT token FROM *PREFIX*phonetrack_sessions ';
            $sqlget .= 'WHERE autopurge='.$this->db_quote_escape_string($s).' ;';
            $req = $this->dbconnection->prepare($sqlget);
            $req->execute();
            while ($row = $req->fetch()){
                array_push($sessions, $row['token']);
            }
            $req->closeCursor();

            $devices = array();
            foreach ($sessions as $token) {
                $sqlget = 'SELECT id FROM *PREFIX*phonetrack_devices ';
                $sqlget .= 'WHERE sessionid='.$this->db_quote_escape_string($token).' ;';
                $req = $this->dbconnection->prepare($sqlget);
                $req->execute();
                while ($row = $req->fetch()){
                    array_push($devices, $row['id']);
                }
                $req->closeCursor();
            }

            foreach ($devices as $did) {
                $sqldel = 'DELETE FROM *PREFIX*phonetrack_points ';
                $sqldel .= 'WHERE deviceid='.$this->db_quote_escape_string($did).' ';
                $sqldel .= 'and timestamp<'.$this->db_quote_escape_string($ts).' ;';
                $req = $this->dbconnection->prepare($sqldel);
                $req->execute();
                $req->closeCursor();
            }
        }
    }

    /**
     * auto export
     * triggered by NC cron job
     *
     * export sessions
     */
    public function cronAutoExport() {
        date_default_timezone_set('UTC');
        $userNames = [];

        // last day
        $now = new \DateTime();
        $y = $now->format('Y');
        $m = $now->format('m');
        $d = $now->format('d');
        $timestamp = $now->getTimestamp();

        // get begining of today
        $dateMaxDay = new \DateTime($y.'-'.$m.'-'.$d);
        $maxDayTimestamp = $dateMaxDay->getTimestamp();
        $minDayTimestamp = $maxDayTimestamp - 24*60*60;

        $dateMaxDay->modify('-1 day');
        $dailySuffix = '_daily_'.$dateMaxDay->format('Y-m-d');
        //$dailySuffix = '_daily_'.$y.'-'.sprintf('%02d', intval($m)).'-'.sprintf('%02d', intval($d)-1);

        // last week
        $now = new \DateTime();
        while (intval($now->format('N')) !== 1) {
            $now->modify('-1 day');
        }
        $y = $now->format('Y');
        $m = $now->format('m');
        $d = $now->format('d');
        $dateWeekMax = new \DateTime($y.'-'.$m.'-'.$d);
        $maxWeekTimestamp = $dateWeekMax->getTimestamp();
        $minWeekTimestamp = $maxWeekTimestamp - 7*24*60*60;
        $dateWeekMin = new \DateTime($y.'-'.$m.'-'.$d);
        $dateWeekMin->modify('-7 day');
        $weeklySuffix = '_weekly_'.$dateWeekMin->format('Y-m-d');

        // last month
        $now = new \DateTime();
        while (intval($now->format('d')) !== 1) {
            $now->modify('-1 day');
        }
        $y = $now->format('Y');
        $m = $now->format('m');
        $d = $now->format('d');
        $dateMonthMax = new \DateTime($y.'-'.$m.'-'.$d);
        $maxMonthTimestamp = $dateMonthMax->getTimestamp();
        $now->modify('-1 day');
        while (intval($now->format('d')) !== 1) {
            $now->modify('-1 day');
        }
        $y = intval($now->format('Y'));
        $m = intval($now->format('m'));
        $d = intval($now->format('d'));
        $dateMonthMin = new \DateTime($y.'-'.$m.'-'.$d);
        $minMonthTimestamp = $dateMonthMin->getTimestamp();
        $monthlySuffix = '_monthly_'.$dateMonthMin->format('Y-m');

        $weekFilterArray = array();
        $weekFilterArray['tsmin'] = $minWeekTimestamp;
        $weekFilterArray['tsmax'] = $maxWeekTimestamp;
        $dayFilterArray = array();
        $dayFilterArray['tsmin'] = $minDayTimestamp;
        $dayFilterArray['tsmax'] = $maxDayTimestamp;
        $monthFilterArray = array();
        $monthFilterArray['tsmin'] = $minMonthTimestamp;
        $monthFilterArray['tsmax'] = $maxMonthTimestamp;

        foreach($this->userManager->search('') as $u) {
            $userName = $u->getUID();

            $sqlget = 'SELECT name, token, autoexport FROM *PREFIX*phonetrack_sessions ';
            $sqlget .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($userName).' ;';
            $req = $this->dbconnection->prepare($sqlget);
            $req->execute();
            while ($row = $req->fetch()){
                $dbname = $row['name'];
                $dbtoken = $row['token'];
                $dbexportType = $row['autoexport'];
                // export if autoexport is set
                if ($dbexportType !== 'no') {
                    $suffix = $dailySuffix;
                    $filterArray = $dayFilterArray;
                    if ($dbexportType === 'weekly') {
                        $suffix = $weeklySuffix;
                        $filterArray = $weekFilterArray;
                    }
                    else if ($dbexportType === 'monthly') {
                        $suffix = $monthlySuffix;
                        $filterArray = $monthFilterArray;
                    }
                    $dir = $this->getOrCreateExportDir($userName);
                    // check if file already exists
                    $exportName = $dbname.$suffix.'.gpx';

                    $rel_path = str_replace(\OC::$server->getUserFolder($userName)->getPath(), '', $dir->getPath());
                    $exportPath = $rel_path.'/'.$exportName;
                    if (! $dir->nodeExists($exportName)) {
                        $this->export($dbname, $dbtoken, $exportPath, $userName, $filterArray);
                    }
                }
            }
        }
        // we run the auto purge method AFTER the auto export
        // to avoid deleting data before it has been eventually exported
        $this->cronAutoPurge();
    }

    /**
     * @NoAdminRequired
     * @PublicPage
     * @NoCSRFRequired
     */
    public function APIgetLastPositions($sessionid) {
        $result = array();
        // check if session exists
        $dbtoken = null;
        $sqlget = 'SELECT publicviewtoken, token FROM *PREFIX*phonetrack_sessions ';
        $sqlget .= 'WHERE publicviewtoken='.$this->db_quote_escape_string($sessionid).' ';
        $sqlget .= 'AND public=1 ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            $dbtoken = $row['token'];
            $dbpubtoken = $row['publicviewtoken'];
        }
        $req->closeCursor();

        // session exists
        if ($dbtoken !== null) {
            // get list of devices
            $devices = array();
            $sqldev = 'SELECT id FROM *PREFIX*phonetrack_devices ';
            $sqldev .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ;';
            $req = $this->dbconnection->prepare($sqldev);
            $req->execute();
            while ($row = $req->fetch()){
                array_push($devices, $row['id']);
            }
            $req->closeCursor();

            // get the coords for each device
            $result[$dbpubtoken] = array();

            foreach ($devices as $devid) {
                $name = null;
                $sqlname = 'SELECT name ';
                $sqlname .= 'FROM *PREFIX*phonetrack_devices ';
                $sqlname .= 'WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ';
                $sqlname .= 'AND id='.$this->db_quote_escape_string($devid).'; ';
                $req = $this->dbconnection->prepare($sqlname);
                $req->execute();
                $col = '';
                while ($row = $req->fetch()){
                    $name = $row['name'];
                }
                $req->closeCursor();

                $entry = array();
                $sqlget = 'SELECT lat, lon, timestamp, batterylevel, satellites, accuracy, altitude, speed, bearing';
                $sqlget .= ' FROM *PREFIX*phonetrack_points ';
                $sqlget .= 'WHERE deviceid='.$this->db_quote_escape_string($devid).' ';
                $sqlget .= 'ORDER BY timestamp DESC LIMIT 1 ;';
                $req = $this->dbconnection->prepare($sqlget);
                $req->execute();
                while ($row = $req->fetch()){
                    foreach ($row as $k => $v) {
                        $entry[$k] = floatval($v);
                    }
                }
                $req->closeCursor();
                if (count($entry) > 0) {
                    $result[$dbpubtoken][$name] = $entry;
                }
            }
        }
        $response = new DataResponse($result);
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

}
