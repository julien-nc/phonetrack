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
use OCP\DB\QueryBuilder\IQueryBuilder;

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

function endswith($string, $test) {
    $strlen = strlen($string);
    $testlen = strlen($test);
    if ($testlen > $strlen) return false;
    return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
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
    private $currentXmlTag;
    private $importToken;
    private $importDevName;
    private $currentPoint;
    private $currentPointList;
    private $trackIndex;
    private $pointIndex;

    public function __construct($AppName, IRequest $request, $UserId,
                                $userfolder, $config, $shareManager,
                                IAppManager $appManager, $userManager,
                                $logger){
        parent::__construct($AppName, $request);
        $this->logger = $logger;
        $this->appName = $AppName;
        $this->appVersion = $config->getAppValue('phonetrack', 'installed_version');
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
        $sqlts = '
            SELECT servername, type, url, layers,
                   version, format, opacity, transparent,
                   minzoom, maxzoom, attribution
            FROM *PREFIX*phonetrack_tileserver
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
            AND type='.$this->db_quote_escape_string($type).';';
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
        //date_default_timezone_set('Europe/Paris');
        //phpinfo();
        $tss = $this->getUserTileServers('tile');
        $oss = $this->getUserTileServers('overlay');
        $tssw = $this->getUserTileServers('tilewms');
        $ossw = $this->getUserTileServers('overlaywms');

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
            'filtersBookmarks'=>$this->getFiltersBookmarks(),
            'phonetrack_version'=>$this->appVersion
        ];
        $response = new TemplateResponse('phonetrack', 'main', $params);
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            //->addAllowedChildSrcDomain('*')
            ->addAllowedFrameDomain('*')
            ->addAllowedWorkerSrcDomain('*')
            ->addAllowedObjectDomain('*')
            ->addAllowedScriptDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    private function getReservedNames($token) {
        $result = array();

        $sqlgetres = '
            SELECT name, nametoken
            FROM *PREFIX*phonetrack_devices
            WHERE sessionid='.$this->db_quote_escape_string($token).' ;';
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
        $sqlget = '
            SELECT name, token, publicviewtoken, public, autoexport, autopurge, locked
            FROM *PREFIX*phonetrack_sessions
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
            ORDER BY LOWER(name) ASC ;';
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
            $dblocked = intval($row['locked']);
            $reservedNames = $this->getReservedNames($dbtoken);
            $publicShares = $this->getPublicShares($dbtoken);
            $devices = $this->getDevices($dbtoken);
            array_push($sessions, array($dbname, $dbtoken, $dbpublicviewtoken, $devices, $dbpublic, $sharedWith, $reservedNames, $publicShares, $dbautoexport, $dbautopurge, $dblocked));
        }
        $req->closeCursor();

        $ncUserList = $this->getUserList()->getData()['users'];
        // sessions shared with current user
        $sqlgetshares = '
            SELECT sessionid, sharetoken
            FROM *PREFIX*phonetrack_shares
            WHERE username='.$this->db_quote_escape_string($this->userId).' ;';
        $req = $this->dbconnection->prepare($sqlgetshares);
        $req->execute();
        while ($row = $req->fetch()){
            $dbsessionid = $row['sessionid'];
            $dbsharetoken = $row['sharetoken'];
            $sessionInfo = $this->getSessionInfo($dbsessionid);
            $dbname = $sessionInfo['name'];
            $dbuserId = $sessionInfo['user'];
            $userNameDisplay = $dbuserId;
            if (array_key_exists($dbuserId, $ncUserList)) {
                $userNameDisplay = $ncUserList[$dbuserId];
            }
            $devices = $this->getDevices($dbsessionid);
            array_push($sessions, array($dbname, $dbsharetoken, $userNameDisplay, $devices));
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

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * get sessions owned by and shared with current user
     */
    public function APIgetSessions() {
        $sessions = array();
        // sessions owned by current user
        $sqlget = '
            SELECT name, token, publicviewtoken, public, autoexport, autopurge
            FROM *PREFIX*phonetrack_sessions
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
            ORDER BY LOWER(name) ASC ;';
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
        $sqlgetshares = '
            SELECT sessionid, sharetoken,
                   *PREFIX*phonetrack_sessions.publicviewtoken AS publicviewtoken,
                   *PREFIX*phonetrack_sessions.public AS public
            FROM *PREFIX*phonetrack_shares
            INNER JOIN *PREFIX*phonetrack_sessions ON *PREFIX*phonetrack_shares.sessionid=*PREFIX*phonetrack_sessions.token
            WHERE username='.$this->db_quote_escape_string($this->userId).' ;';
        $req = $this->dbconnection->prepare($sqlgetshares);
        $req->execute();
        while ($row = $req->fetch()){
            $dbsessionid = $row['sessionid'];
            $dbsharetoken = $row['sharetoken'];
            $sessionInfo = $this->getSessionInfo($dbsessionid);
            $dbname = $sessionInfo['name'];
            $dbuser = $sessionInfo['user'];
            $dbpublic = is_numeric($row['public']) ? intval($row['public']) : 0;
            $dbpublicviewtoken = $row['publicviewtoken'];
            $devices = $this->getDevices($dbsessionid);
            array_push($sessions, array($dbname, $dbsharetoken, $dbpublicviewtoken, $devices, $dbpublic, $dbuser));
        }
        $req->closeCursor();

        $response = new DataResponse(
            $sessions
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
        $sqlget = '
            SELECT id, name, alias, color, nametoken, shape
            FROM *PREFIX*phonetrack_devices
            WHERE sessionid='.$this->db_quote_escape_string($sessionid).' ;';
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
        $sqlget = '
            SELECT name, '.$this->dbdblquotes.'user'.$this->dbdblquotes.'
            FROM *PREFIX*phonetrack_sessions
            WHERE token='.$this->db_quote_escape_string($sessionid).';';
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
        $ncUserList = $this->getUserList()->getData()['users'];
        $sharesToDelete = [];
        $sharedWith = [];
        $sqlchk = '
            SELECT username
            FROM *PREFIX*phonetrack_shares
            WHERE sessionid='.$this->db_quote_escape_string($sessionid).' ;';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbusername = null;
        while ($row = $req->fetch()){
            //array_push($sharedWith, $row['username']);
            $userId = $row['username'];
            if (array_key_exists($userId, $ncUserList)) {
                $userName = $ncUserList[$userId];
                $sharedWith[$userId] = $userName;
            }
            else {
                array_push($sharesToDelete, $userId);
            }
        }
        $req->closeCursor();

        // delete useless shares (with unexisting users)
        foreach ($sharesToDelete as $uid) {
            $sqldel = '
                DELETE FROM *PREFIX*phonetrack_shares
                WHERE sessionid='.$this->db_quote_escape_string($sessionid).'
                    AND username='.$this->db_quote_escape_string($uid).' ;';
            $req = $this->dbconnection->prepare($sqldel);
            $req->execute();
            $req->closeCursor();
        }

        return $sharedWith;
    }

    /**
     * get the public shares for a session
     */
    private function getPublicShares($sessionid) {
        $shares = [];
        $sqlchk = '
            SELECT *
            FROM *PREFIX*phonetrack_pubshares
            WHERE sessionid='.$this->db_quote_escape_string($sessionid).' ;';
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
        $sqlchk = '
            SELECT name
            FROM *PREFIX*phonetrack_sessions
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND token='.$this->db_quote_escape_string($token).' ;';
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
            $sqlchk = '
                SELECT *
                FROM *PREFIX*phonetrack_pubshares
                WHERE sessionid='.$this->db_quote_escape_string($token).'
                AND sharetoken='.$this->db_quote_escape_string($sharetoken).' ;';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbshareid = null;
            while ($row = $req->fetch()){
                $dbshareid = $row['id'];
            }
            $req->closeCursor();

            if ($dbshareid !== null) {
                // set device name
                $sqlupd = '
                    UPDATE *PREFIX*phonetrack_pubshares
                    SET devicename='.$this->db_quote_escape_string($devicename).'
                    WHERE id='.$this->db_quote_escape_string($dbshareid).' ;';
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
        $sqlchk = '
            SELECT name
            FROM *PREFIX*phonetrack_sessions
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND token='.$this->db_quote_escape_string($token).' ;';
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
            $sqlchk = '
                SELECT *
                FROM *PREFIX*phonetrack_pubshares
                WHERE sessionid='.$this->db_quote_escape_string($token).'
                      AND sharetoken='.$this->db_quote_escape_string($sharetoken).' ;';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbshareid = null;
            while ($row = $req->fetch()){
                $dbshareid = $row['id'];
            }
            $req->closeCursor();

            if ($dbshareid !== null) {
                // set device name
                $sqlupd = '
                    UPDATE *PREFIX*phonetrack_pubshares
                    SET geofencify='.$this->db_quote_escape_string($geofencify).'
                    WHERE id='.$this->db_quote_escape_string($dbshareid).' ;';
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
        $sqlchk = '
            SELECT name
            FROM *PREFIX*phonetrack_sessions
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND token='.$this->db_quote_escape_string($token).' ;';
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
            $sqlchk = '
                SELECT *
                FROM *PREFIX*phonetrack_pubshares
                WHERE sessionid='.$this->db_quote_escape_string($token).'
                      AND sharetoken='.$this->db_quote_escape_string($sharetoken).' ;';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbshareid = null;
            while ($row = $req->fetch()){
                $dbshareid = $row['id'];
            }
            $req->closeCursor();

            if ($dbshareid !== null) {
                // set device name
                $sqlupd = '
                    UPDATE *PREFIX*phonetrack_pubshares
                    SET lastposonly='.$this->db_quote_escape_string($lastposonly).'
                    WHERE id='.$this->db_quote_escape_string($dbshareid).' ;';
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
     * @NoCSRFRequired
     */
    public function APIcreateSession($sessionname) {
        return $this->createSession($sessionname);
    }

    /**
     * @NoAdminRequired
     */
    public function createSession($name) {
        $token = '';
        $publicviewtoken = '';
        // check if session name is not already used
        $sqlchk = '
            SELECT name
            FROM *PREFIX*phonetrack_sessions
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
            AND name='.$this->db_quote_escape_string($name).' ;';
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
            $sql = '
                INSERT INTO *PREFIX*phonetrack_sessions
                ('.$this->dbdblquotes.'user'.$this->dbdblquotes.', name, token, publicviewtoken, public, creationversion)
                VALUES ('.$this->db_quote_escape_string($this->userId).','.
                          $this->db_quote_escape_string($name).','.
                          $this->db_quote_escape_string($token).','.
                          $this->db_quote_escape_string($publicviewtoken).','.
                          $this->db_quote_escape_string('1').','.
                          $this->db_quote_escape_string($this->appVersion).'
                );';
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
        $sqlchk = '
            SELECT name
            FROM *PREFIX*phonetrack_sessions
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND token='.$this->db_quote_escape_string($token).' ;';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbname = null;
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            break;
        }
        $req->closeCursor();

        if ($dbname !== null) {
            // get all devices
            $dids = array();
            $sqlchk = '
                SELECT id
                FROM *PREFIX*phonetrack_devices
                WHERE sessionid='.$this->db_quote_escape_string($token).' ;';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbdevid = null;
            while ($row = $req->fetch()){
                array_push($dids, $row['id']);
            }
            $req->closeCursor();

            foreach ($dids as $did) {
                $this->deleteDevice($token, $did);
            }

            $sqldel = '
                DELETE FROM *PREFIX*phonetrack_shares
                WHERE sessionid='.$this->db_quote_escape_string($token).' ;';
            $req = $this->dbconnection->prepare($sqldel);
            $req->execute();
            $req->closeCursor();

            $sqldel = '
                DELETE FROM *PREFIX*phonetrack_pubshares
                WHERE sessionid='.$this->db_quote_escape_string($token).' ;';
            $req = $this->dbconnection->prepare($sqldel);
            $req->execute();
            $req->closeCursor();

            $sqldel = '
                DELETE FROM *PREFIX*phonetrack_sessions
                WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                      AND token='.$this->db_quote_escape_string($token).' ;';
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
        $sqlchk = '
            SELECT name
            FROM *PREFIX*phonetrack_sessions
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND token='.$this->db_quote_escape_string($token).' ;';
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
            $sqldev = '
                SELECT id
                FROM *PREFIX*phonetrack_devices
                WHERE sessionid='.$this->db_quote_escape_string($token).'
                      AND id='.$this->db_quote_escape_string($deviceid).' ;';
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
                    $sqldel = '
                        DELETE FROM *PREFIX*phonetrack_points
                        WHERE deviceid='.$this->db_quote_escape_string($dbdid).'
                              AND (id='.implode(' OR id=', $escapedPointIds).');';
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
        $sqlchk = '
            SELECT name
            FROM *PREFIX*phonetrack_sessions
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                   AND token='.$this->db_quote_escape_string($token).' ;';
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
            $sqldev = '
                SELECT id
                FROM *PREFIX*phonetrack_devices
                WHERE sessionid='.$this->db_quote_escape_string($token).'
                      AND id='.$this->db_quote_escape_string($deviceid).' ;';
            $req = $this->dbconnection->prepare($sqldev);
            $req->execute();
            while ($row = $req->fetch()){
                $dbdid =  $row['id'];
            }
            $req->closeCursor();

            if ($dbdid !== null) {
                // check if point exists
                $sqlchk = '
                    SELECT id
                    FROM *PREFIX*phonetrack_points
                    WHERE deviceid='.$this->db_quote_escape_string($dbdid).'
                          AND id='.$this->db_quote_escape_string($pointid).' ;';
                $req = $this->dbconnection->prepare($sqlchk);
                $req->execute();
                $dbpid = null;
                while ($row = $req->fetch()){
                    $dbpid = $row['id'];
                    break;
                }
                $req->closeCursor();

                if ($dbpid !== null) {
                    $sqlupd = '
                        UPDATE *PREFIX*phonetrack_points
                        SET
                             lat='.$this->db_quote_escape_string($lat).',
                             lon='.$this->db_quote_escape_string($lon).',
                             altitude='.(is_numeric($alt) ? $this->db_quote_escape_string(floatval($alt)) : 'NULL').',
                             timestamp='.$this->db_quote_escape_string($timestamp).',
                             accuracy='.(is_numeric($acc) ? $this->db_quote_escape_string(floatval($acc)) : 'NULL').',
                             batterylevel='.(is_numeric($bat) ? $this->db_quote_escape_string(floatval($bat)) : 'NULL').',
                             satellites='.(is_numeric($sat) ? $this->db_quote_escape_string(intval($sat)) : 'NULL').',
                             useragent='.$this->db_quote_escape_string($useragent).',
                             speed='.(is_numeric($speed) ? $this->db_quote_escape_string(floatval($speed)) : 'NULL').',
                             bearing='.(is_numeric($bearing) ? $this->db_quote_escape_string(floatval($bearing)) : 'NULL').'
                        WHERE deviceid='.$this->db_quote_escape_string($dbdid).'
                              AND id='.$this->db_quote_escape_string($dbpid).' ;';
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
            $sqlchk = '
                SELECT name
                FROM *PREFIX*phonetrack_sessions
                WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                      AND token='.$this->db_quote_escape_string($token).' ;';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbname = null;
            while ($row = $req->fetch()){
                $dbname = $row['name'];
                break;
            }
            $req->closeCursor();

            if ($dbname !== null) {
                $sqlren = '
                    UPDATE *PREFIX*phonetrack_sessions
                    SET public='.$this->db_quote_escape_string($public).'
                    WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                          AND token='.$this->db_quote_escape_string($token).' ;';
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
    public function setSessionLocked($token, $locked) {
        $ilocked = intval($locked);
        if ($ilocked === 1 or $ilocked === 0) {
            // check if session exists
            $qb = $this->dbconnection->getQueryBuilder();
            // is the project shared with the user ?
            $qb->select('name')
                ->from('phonetrack_sessions', 's')
                ->where(
                    $qb->expr()->eq('user', $qb->createNamedParameter($this->userId, IQueryBuilder::PARAM_STR))
                )
                ->andWhere(
                    $qb->expr()->eq('token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR))
                );
            $req = $qb->execute();
            $dbname = null;
            while ($row = $req->fetch()){
                $dbname = $row['name'];
                break;
            }
            $req->closeCursor();
            $qb = $qb->resetQueryParts();

            if ($dbname !== null) {
                $qb->update('phonetrack_sessions');
                $qb->set('locked', $qb->createNamedParameter($ilocked, IQueryBuilder::PARAM_INT))
                   ->where(
                       $qb->expr()->eq('user', $qb->createNamedParameter($this->userId, IQueryBuilder::PARAM_STR))
                   )
                   ->andWhere(
                       $qb->expr()->eq('token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR))
                   );
                $req = $qb->execute();
                $qb = $qb->resetQueryParts();

                $response = new DataResponse(['done'=>1]);
                return $response;
            }
            else {
                $response = new DataResponse(['done'=>2], 400);
                return $response;
            }
        }
        else {
            $response = new DataResponse(['done'=>3], 400);
            return $response;
        }
    }

    /**
     * @NoAdminRequired
     */
    public function setSessionAutoExport($token, $value) {
        // check if session exists
        $sqlchk = '
            SELECT name
            FROM *PREFIX*phonetrack_sessions
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND token='.$this->db_quote_escape_string($token).' ;';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbname = null;
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            break;
        }
        $req->closeCursor();

        if ($dbname !== null) {
            $sqlren = '
                UPDATE *PREFIX*phonetrack_sessions
                SET autoexport='.$this->db_quote_escape_string($value).'
                WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                      AND token='.$this->db_quote_escape_string($token).' ;';
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
        $sqlchk = '
            SELECT name
            FROM *PREFIX*phonetrack_sessions
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND token='.$this->db_quote_escape_string($token).' ;';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbname = null;
        while ($row = $req->fetch()){
            $dbname = $row['name'];
            break;
        }
        $req->closeCursor();

        if ($dbname !== null) {
            $sqlren = '
                UPDATE *PREFIX*phonetrack_sessions
                SET autopurge='.$this->db_quote_escape_string($value).'
                WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                      AND token='.$this->db_quote_escape_string($token).' ;';
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
        $sqlchk = '
            SELECT name
            FROM *PREFIX*phonetrack_sessions
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND token='.$this->db_quote_escape_string($session).' ;';
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
            $sqlchk = '
                SELECT id
                FROM *PREFIX*phonetrack_devices
                WHERE sessionid='.$this->db_quote_escape_string($session).'
                      AND id='.$this->db_quote_escape_string($device).' ;';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbdevid = null;
            while ($row = $req->fetch()){
                $dbdevid = $row['id'];
                break;
            }
            $req->closeCursor();

            if ($dbdevid !== null) {
                $sqlupd = '
                    UPDATE *PREFIX*phonetrack_devices
                    SET color='.$this->db_quote_escape_string($color).'
                    WHERE id='.$this->db_quote_escape_string($device).'
                          AND sessionid='.$this->db_quote_escape_string($session).' ;';
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
        $sqlchk = '
            SELECT name
            FROM *PREFIX*phonetrack_sessions
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND token='.$this->db_quote_escape_string($session).' ;';
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
            $sqlchk = '
                SELECT id
                FROM *PREFIX*phonetrack_devices
                WHERE sessionid='.$this->db_quote_escape_string($session).'
                      AND id='.$this->db_quote_escape_string($device).' ;';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbdevid = null;
            while ($row = $req->fetch()){
                $dbdevid = $row['id'];
                break;
            }
            $req->closeCursor();

            if ($dbdevid !== null) {
                $sqlupd = '
                    UPDATE *PREFIX*phonetrack_devices
                    SET shape='.$this->db_quote_escape_string($shape).'
                    WHERE id='.$this->db_quote_escape_string($device).'
                          AND sessionid='.$this->db_quote_escape_string($session).' ;';
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
            $sqlchk = '
                SELECT name
                FROM *PREFIX*phonetrack_sessions
                WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                      AND token='.$this->db_quote_escape_string($token).' ;';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbname = null;
            while ($row = $req->fetch()){
                $dbname = $row['name'];
                break;
            }
            $req->closeCursor();

            if ($dbname !== null) {
                $sqlren = '
                    UPDATE *PREFIX*phonetrack_sessions
                    SET name='.$this->db_quote_escape_string($newname).'
                    WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                          AND token='.$this->db_quote_escape_string($token).' ;';
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
            $sqlchk = '
                SELECT name, token
                FROM *PREFIX*phonetrack_sessions
                WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                      AND token='.$this->db_quote_escape_string($token).' ;';
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
                $sqlchk = '
                    SELECT id
                    FROM *PREFIX*phonetrack_devices
                    WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                          AND id='.$this->db_quote_escape_string($deviceid).' ;';
                $req = $this->dbconnection->prepare($sqlchk);
                $req->execute();
                $dbdeviceid = null;
                while ($row = $req->fetch()){
                    $dbdeviceid = $row['id'];
                }
                $req->closeCursor();

                if ($dbdeviceid !== null) {
                    $sqlren = '
                        UPDATE *PREFIX*phonetrack_devices
                        SET name='.$this->db_quote_escape_string($newname).'
                        WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                              AND id='.$this->db_quote_escape_string($dbdeviceid).' ;';
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
            $sqlchk = '
                SELECT name, token
                FROM *PREFIX*phonetrack_sessions
                WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                      AND token='.$this->db_quote_escape_string($token).' ;';
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
                $sqlchk = '
                    SELECT id
                    FROM *PREFIX*phonetrack_devices
                    WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                          AND id='.$this->db_quote_escape_string($deviceid).' ;';
                $req = $this->dbconnection->prepare($sqlchk);
                $req->execute();
                $dbdeviceid = null;
                while ($row = $req->fetch()){
                    $dbdeviceid = $row['id'];
                }
                $req->closeCursor();

                if ($dbdeviceid !== null) {
                    $sqlren = '
                        UPDATE *PREFIX*phonetrack_devices
                        SET alias='.$this->db_quote_escape_string($newalias).'
                        WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                              AND id='.$this->db_quote_escape_string($dbdeviceid).' ;';
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
        $sqlchk = '
            SELECT name, token
            FROM *PREFIX*phonetrack_sessions
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND token='.$this->db_quote_escape_string($token).' ;';
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
            $sqlchk = '
                SELECT name, token
                FROM *PREFIX*phonetrack_sessions
                WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                      AND token='.$this->db_quote_escape_string($newSessionId).' ;';
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
                $sqlchk = '
                    SELECT id, name FROM *PREFIX*phonetrack_devices
                    WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                          AND id='.$this->db_quote_escape_string($deviceid).' ;';
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
                    $sqlchk = '
                        SELECT id, name
                        FROM *PREFIX*phonetrack_devices
                        WHERE sessionid='.$this->db_quote_escape_string($dbdesttoken).'
                              AND name='.$this->db_quote_escape_string($dbdevicename).' ;';
                    $req = $this->dbconnection->prepare($sqlchk);
                    $req->execute();
                    $dbdestname = null;
                    while ($row = $req->fetch()){
                        $dbdestname = $row['name'];
                    }
                    $req->closeCursor();

                    if ($dbdestname === null) {
                        $sqlreaff = '
                            UPDATE *PREFIX*phonetrack_devices
                            SET sessionid='.$this->db_quote_escape_string($dbdesttoken).'
                            WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                                  AND id='.$this->db_quote_escape_string($dbdeviceid).' ;';
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
        $sqlchk = '
            SELECT name, token
            FROM *PREFIX*phonetrack_sessions
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND token='.$this->db_quote_escape_string($token).' ;';
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
            $sqlchk = '
                SELECT id
                FROM *PREFIX*phonetrack_devices
                WHERE sessionid='.$this->db_quote_escape_string($token).'
                      AND id='.$this->db_quote_escape_string($deviceid).' ;';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbdeviceid = null;
            while ($row = $req->fetch()){
                $dbdeviceid = $row['id'];
            }
            $req->closeCursor();

            if ($dbdeviceid !== null) {
                $sqldel = '
                    DELETE FROM *PREFIX*phonetrack_points
                    WHERE deviceid='.$this->db_quote_escape_string($dbdeviceid).' ;';
                $req = $this->dbconnection->prepare($sqldel);
                $req->execute();
                $req->closeCursor();

                $sqldel = '
                    DELETE FROM *PREFIX*phonetrack_geofences
                    WHERE deviceid='.$this->db_quote_escape_string($dbdeviceid).' ;';
                $req = $this->dbconnection->prepare($sqldel);
                $req->execute();
                $req->closeCursor();

                $sqldel = '
                    DELETE FROM *PREFIX*phonetrack_proxims
                    WHERE deviceid1='.$this->db_quote_escape_string($dbdeviceid).'
                          OR deviceid2='.$this->db_quote_escape_string($dbdeviceid).' ;';
                $req = $this->dbconnection->prepare($sqldel);
                $req->execute();
                $req->closeCursor();

                $sqldel = '
                    DELETE FROM *PREFIX*phonetrack_devices
                    WHERE id='.$this->db_quote_escape_string($dbdeviceid).' ;';
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
        // get option value
        $nbpointsload = $this->config->getUserValue($this->userId, 'phonetrack', 'nbpointsload', '10000');

        if (is_array($sessions)) {
            foreach ($sessions as $session) {
                if (is_array($session) and count($session) === 3) {
                    $token = $session[0];
                    $lastTime = $session[1];
                    $firstTime = $session[2];

                    // check if session exists
                    $dbtoken = null;
                    $sqlget = '
                        SELECT token
                        FROM *PREFIX*phonetrack_sessions
                        WHERE token='.$this->db_quote_escape_string($token).' ;';
                    $req = $this->dbconnection->prepare($sqlget);
                    $req->execute();
                    while ($row = $req->fetch()){
                        $dbtoken = $row['token'];
                    }
                    $req->closeCursor();

                    // if not, check it is a shared session
                    if ($dbtoken === null) {
                        $sqlget = '
                            SELECT sessionid
                            FROM *PREFIX*phonetrack_shares
                            WHERE sharetoken='.$this->db_quote_escape_string($token).'
                                  AND username='.$this->db_quote_escape_string($this->userId).' ;';
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
                        $sqldev = '
                            SELECT id
                            FROM *PREFIX*phonetrack_devices
                            WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ;';
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
                                $sqlcolor = '
                                    SELECT color, name, alias, shape
                                    FROM *PREFIX*phonetrack_devices
                                    WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                                          AND id='.$this->db_quote_escape_string($devid).' ;';
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

                            $sqlget = '
                                SELECT id, deviceid, lat, lon, timestamp, accuracy, satellites,
                                       altitude, batterylevel, useragent, speed, bearing
                                FROM *PREFIX*phonetrack_points
                                WHERE deviceid='.$this->db_quote_escape_string($devid).' '.
                                $firstLastSQL.' '.
                                $settingsTimeFilterSQL.' ';
                            // get max number of points to load
                            if (is_numeric($nbpointsload)) {
                                $sqlget .= 'ORDER BY timestamp DESC LIMIT '.intval($nbpointsload);
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
        $sqlfences = '
            SELECT id, name, latmin, latmax, lonmin,
                   lonmax, urlenter, urlleave,
                   urlenterpost, urlleavepost,
                   sendemail, emailaddr, sendnotif
            FROM *PREFIX*phonetrack_geofences
            WHERE deviceid='.$this->db_quote_escape_string($devid).' ;';
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
                sendemail, emailaddr, sendnotif,
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
        $sqlget = '
            SELECT token, public
            FROM *PREFIX*phonetrack_sessions
            WHERE token='.$this->db_quote_escape_string($token).' ;';
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
                $sqlget = '
                    SELECT token
                    FROM *PREFIX*phonetrack_sessions
                    WHERE token='.$this->db_quote_escape_string($token).' ;';
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
                    $sqldev = '
                        SELECT id
                        FROM *PREFIX*phonetrack_devices
                        WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ;';
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
                            $sqlcolor = '
                                SELECT color, name, alias, shape
                                FROM *PREFIX*phonetrack_devices
                                WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                                      AND id='.$this->db_quote_escape_string($devid).' ;';
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

                        $sqlget = '
                            SELECT id, deviceid, lat, lon,
                                   timestamp, accuracy, satellites,
                                   altitude, batterylevel,
                                   useragent, speed, bearing
                            FROM *PREFIX*phonetrack_points
                            WHERE deviceid='.$this->db_quote_escape_string($devid).' '.
                            $firstLastSQL.'
                            ORDER BY timestamp DESC LIMIT 1000 ;';
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
            $nbPointsLoad = 1000;
            if (count($session) > 3) {
                $nbPointsLoad = $session[3];
            }
            $lastposonly = 0;
            $geofencify = 0;

            // check if session exists
            $dbtoken = null;
            $dbpublicviewtoken = null;
            $dbpublic = null;
            $filters = null;
            $deviceNameRestriction = '';
            $sqlget = '
                SELECT publicviewtoken, token, public
                FROM *PREFIX*phonetrack_sessions
                WHERE publicviewtoken='.$this->db_quote_escape_string($publicviewtoken).' ;';
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
                $sqlget = '
                    SELECT sharetoken, sessionid, filters,
                           devicename, lastposonly, geofencify
                    FROM *PREFIX*phonetrack_pubshares
                    WHERE sharetoken='.$this->db_quote_escape_string($publicviewtoken).' ;';
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
                $sqldev = '
                    SELECT id
                    FROM *PREFIX*phonetrack_devices
                    WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' '.
                    $deviceNameRestriction.' ;';
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
                        $sqlcolor = '
                            SELECT color, name, alias, shape
                            FROM *PREFIX*phonetrack_devices
                            WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                                  AND id='.$this->db_quote_escape_string($devid).' ;';
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


                    $sqlget = '
                        SELECT id, deviceid, lat, lon,
                               timestamp, accuracy, satellites,
                               altitude, batterylevel, useragent,
                               speed, bearing
                        FROM *PREFIX*phonetrack_points
                        WHERE deviceid='.$this->db_quote_escape_string($devid).' '.
                        $firstLastSQL.' ';
                    if (intval($lastposonly) === 0) {
                        if (intval($nbPointsLoad) === 0) {
                            $sqlget .= 'ORDER BY timestamp DESC ;';
                        }
                        else {
                            $sqlget .= 'ORDER BY timestamp DESC LIMIT '.intval($nbPointsLoad).' ;';
                        }
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
        $sqlget = '
            SELECT latmin, lonmin, latmax, lonmax, name
            FROM *PREFIX*phonetrack_geofences
            WHERE deviceid='.$this->db_quote_escape_string($devid).' ;';
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
            $sqlchk = '
                SELECT token, public
                FROM *PREFIX*phonetrack_sessions
                WHERE publicviewtoken='.$this->db_quote_escape_string($publicviewtoken).' ;';
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
                $sqlchk = '
                    SELECT sessionid, sharetoken, lastposonly, filters
                    FROM *PREFIX*phonetrack_pubshares
                    WHERE sharetoken='.$this->db_quote_escape_string($publicviewtoken).' ;';
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
            $sqlchk = '
                SELECT name
                FROM *PREFIX*phonetrack_sessions
                WHERE token='.$this->db_quote_escape_string($token).' ;';
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
            'filtersBookmarks'=>[],
            'phonetrack_version'=>$this->appVersion
        ];
        $response = new TemplateResponse('phonetrack', 'main', $params);
        $response->setHeaders(Array('X-Frame-Options'=>''));
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            //->addAllowedChildSrcDomain('*')
            ->addAllowedFrameDomain('*')
            ->addAllowedWorkerSrcDomain('*')
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
        $userFolder = \OC::$server->getUserFolder($this->userId);
        $cleanpath = str_replace(array('../', '..\\'), '',  $path);

        $file = null;
        $sessionName = null;
        $token = null;
        $devices = null;
        $publicviewtoken = null;
        if ($userFolder->nodeExists($cleanpath)){
            $file = $userFolder->get($cleanpath);
            if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                $file->isReadable()){
                if (endswith($file->getName(), '.gpx') or endswith($file->getName(), '.GPX')) {
                    $sessionName = str_replace(['.gpx', '.GPX'], '', $file->getName());
                    $res = $this->createSession($sessionName);
                    $response = $res->getData();
                    if ($response['done'] === 1) {
                        $token = $response['token'];
                        $publicviewtoken = $response['publicviewtoken'];
                        $done = $this->readGpxImportPoints($file, $file->getName(), $token);
                    }
                    else {
                        $done = 2;
                    }
                }
                else if (endswith($file->getName(), '.kml') or endswith($file->getName(), '.KML')) {
                    $sessionName = str_replace(['.kml', '.KML'], '', $file->getName());
                    $res = $this->createSession($sessionName);
                    $response = $res->getData();
                    if ($response['done'] === 1) {
                        $token = $response['token'];
                        $publicviewtoken = $response['publicviewtoken'];
                        $done = $this->readKmlImportPoints($file, $file->getName(), $token);
                    }
                    else {
                        $done = 2;
                    }
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

    private function gpxStartElement($parser, $name, $attrs) {
        //$points, array($lat, $lon, $ele, $timestamp, $acc, $bat, $sat, $ua, $speed, $bearing)
        $this->currentXmlTag = $name;
        if ($name === 'TRK') {
            $this->importDevName = 'device'.$this->trackIndex;
            $this->pointIndex = 1;
            $this->currentPointList = array();
        }
        else if ($name === 'TRKPT') {
            $this->currentPoint = array(null, null, null, $this->pointIndex, null, null,  null, null, null, null);
            if (array_key_exists('LAT', $attrs)) {
                $this->currentPoint[0] = floatval($attrs['LAT']);
            }
            if (array_key_exists('LON', $attrs)) {
                $this->currentPoint[1] = floatval($attrs['LON']);
            }
        }
        //var_dump($attrs);
    }

    private function gpxEndElement($parser, $name) {
        if ($name === 'TRK') {
            // log last track points
            if (count($this->currentPointList) > 0) {
                $this->logMultiple($this->importToken, $this->importDevName, $this->currentPointList);
            }
            $this->trackIndex++;
            unset($this->currentPointList);
        }
        else if ($name === 'TRKPT') {
            // store track point
            array_push($this->currentPointList, $this->currentPoint);
            // if we have enough points, we log them and clean the points array
            if (count($this->currentPointList) >= 500) {
                $this->logMultiple($this->importToken, $this->importDevName, $this->currentPointList);
                unset($this->currentPointList);
                $this->currentPointList = array();
            }
            $this->pointIndex++;
        }
    }

    private function gpxDataElement($parser, $data) {
        //$points, array($lat, $lon, $ele, $timestamp, $acc, $bat, $sat, $ua, $speed, $bearing)
        $d = trim($data);
        if (!empty($d)) {
            if ($this->currentXmlTag === 'ELE') {
                $this->currentPoint[2] = floatval($d);
            }
            else if ($this->currentXmlTag === 'SPEED') {
                $this->currentPoint[8] = floatval($d);
            }
            else if ($this->currentXmlTag === 'SAT') {
                $this->currentPoint[6] = intval($d);
            }
            else if ($this->currentXmlTag === 'COURSE') {
                $this->currentPoint[9] = floatval($d);
            }
            else if ($this->currentXmlTag === 'USERAGENT') {
                $this->currentPoint[7] = $d;
            }
            else if ($this->currentXmlTag === 'BATTERYLEVEL') {
                $this->currentPoint[5] = floatval($d);
            }
            else if ($this->currentXmlTag === 'ACCURACY') {
                $this->currentPoint[4] = floatval($d);
            }
            else if ($this->currentXmlTag === 'TIME') {
                $time = new \DateTime($d);
                $timestamp = $time->getTimestamp();
                $this->currentPoint[3] = $timestamp;
            }
            else if ($this->currentXmlTag === 'NAME') {
                $this->importDevName = $d;
            }
        }
    }

    private function readGpxImportPoints($gpx_file, $gpx_name, $token) {
        $this->importToken = $token;
        $this->trackIndex = 1;
        $xml_parser = xml_parser_create();
        xml_set_object($xml_parser, $this);
        xml_set_element_handler($xml_parser, 'gpxStartElement', 'gpxEndElement');
        xml_set_character_data_handler($xml_parser, 'gpxDataElement');

        $fp = $gpx_file->fopen('r');

        while ($data = fread($fp, 4096000)) {
            if (!xml_parse($xml_parser, $data, feof($fp))) {
                $this->logger->error(
                    'Exception in '.$gpx_name.' parsing at line '.
                      xml_get_current_line_number($xml_parser).' : '.
                      xml_error_string(xml_get_error_code($xml_parser)),
                    array('app' => $this->appName)
                );
                return 5;
            }
        }
        fclose($fp);
        xml_parser_free($xml_parser);
        if ($this->trackIndex === 1) {
            return 6;
        }
        return 1;
    }

    private function kmlStartElement($parser, $name, $attrs) {
        //$points, array($lat, $lon, $ele, $timestamp, $acc, $bat, $sat, $ua, $speed, $bearing)
        $this->currentXmlTag = $name;
        if ($name === 'GX:TRACK') {
            if (array_key_exists('ID', $attrs)) {
                $this->importDevName = $attrs['ID'];
            }
            else {
                $this->importDevName = 'device'.$this->trackIndex;
            }
            $this->pointIndex = 1;
            $this->currentPointList = array();
        }
        else if ($name === 'WHEN') {
            $this->currentPoint = array(null, null, null, $this->pointIndex, null, null,  null, null, null, null);
        }
        //var_dump($attrs);
    }

    private function kmlEndElement($parser, $name) {
        if ($name === 'GX:TRACK') {
            // log last track points
            if (count($this->currentPointList) > 0) {
                $this->logMultiple($this->importToken, $this->importDevName, $this->currentPointList);
            }
            $this->trackIndex++;
            unset($this->currentPointList);
        }
        else if ($name === 'GX:COORD') {
            // store track point
            array_push($this->currentPointList, $this->currentPoint);
            // if we have enough points, we log them and clean the points array
            if (count($this->currentPointList) >= 500) {
                $this->logMultiple($this->importToken, $this->importDevName, $this->currentPointList);
                unset($this->currentPointList);
                $this->currentPointList = array();
            }
            $this->pointIndex++;
        }
    }

    private function kmlDataElement($parser, $data) {
        //$points, array($lat, $lon, $ele, $timestamp, $acc, $bat, $sat, $ua, $speed, $bearing)
        $d = trim($data);
        if (!empty($d)) {
            if ($this->currentXmlTag === 'WHEN') {
                $time = new \DateTime($d);
                $timestamp = $time->getTimestamp();
                $this->currentPoint[3] = $timestamp;
            }
            else if ($this->currentXmlTag === 'GX:COORD') {
                $spl = explode(' ', $d);
                if (count($spl) > 1) {
                    $this->currentPoint[0] = floatval($spl[1]);
                    $this->currentPoint[1] = floatval($spl[0]);
                    if (count($spl) > 2) {
                        $this->currentPoint[2] = floatval($spl[2]);
                    }
                }
            }
        }
    }

    private function readKmlImportPoints($kml_file, $kml_name, $token) {
        $this->importToken = $token;
        $this->trackIndex = 1;
        $xml_parser = xml_parser_create();
        xml_set_object($xml_parser, $this);
        xml_set_element_handler($xml_parser, 'kmlStartElement', 'kmlEndElement');
        xml_set_character_data_handler($xml_parser, 'kmlDataElement');

        $fp = $kml_file->fopen('r');

        while ($data = fread($fp, 4096000)) {
            if (!xml_parse($xml_parser, $data, feof($fp))) {
                $this->logger->error(
                    'Exception in '.$kml_name.' parsing at line '.
                      xml_get_current_line_number($xml_parser).' : '.
                      xml_error_string(xml_get_error_code($xml_parser)),
                    array('app' => $this->appName)
                );
                return 5;
            }
        }
        fclose($fp);
        xml_parser_free($xml_parser);
        if ($this->trackIndex === 1) {
            return 6;
        }
        return 1;
    }

    /**
     * @NoAdminRequired
     */
    public function export($name, $token, $target, $username='', $filterArray=null) {
        date_default_timezone_set('UTC');
        $done = false;
        $warning = 0;
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
        $ofpd = $this->config->getUserValue($userId, 'phonetrack', 'exportoneperdev', 'false');
        $oneFilePerDevice = ($ofpd === 'true');

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
                $sqlget = '
                    SELECT token
                    FROM *PREFIX*phonetrack_sessions
                    WHERE name='.$this->db_quote_escape_string($name).'
                          AND token='.$this->db_quote_escape_string($token).' ;';
                $req = $this->dbconnection->prepare($sqlget);
                $req->execute();
                while ($row = $req->fetch()){
                    $dbtoken = $row['token'];
                }
                $req->closeCursor();

                // if not, check it is a shared session
                if ($dbtoken === null) {
                    $sqlget = '
                        SELECT sessionid
                        FROM *PREFIX*phonetrack_shares
                        WHERE sharetoken='.$this->db_quote_escape_string($token).'
                              AND username='.$this->db_quote_escape_string($userId).' ;';
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
                    // get list of all devices which have points in this session (without filters)
                    $devices = array();
                    $sqldev = '
                        SELECT dev.id AS id, dev.name AS name
                        FROM *PREFIX*phonetrack_devices AS dev, *PREFIX*phonetrack_points AS po
                        WHERE dev.sessionid='.$this->db_quote_escape_string($dbtoken).' AND dev.id=po.deviceid GROUP BY dev.id;';
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

                    // check if there are points in this session (with filters)
                    if ($this->countPointsPerSession($dbtoken, $filterSql) > 0) {
                        // check if all devices of this session (not filtered) have points
                        if ($this->countDevicesPerSession($dbtoken) > count($devices)) {
                            $warning = 2;
                        }
                        // one file for the whole session
                        if (!$oneFilePerDevice) {
                            $gpxHeader = $this->generateGpxHeader($name, count($devices));
                            if (! $dir->nodeExists($newFileName)) {
                                $dir->newFile($newFileName);
                            }
                            $file = $dir->get($newFileName);
                            $fd = $file->fopen('w');
                            fwrite($fd, $gpxHeader);
                        }
                        foreach ($devices as $d) {
                            $devid = $d[0];
                            $devname = $d[1];

                            // check if there are coords for this device (with filters)
                            $nbPoints = $this->countPointsPerDevice($devid, $filterSql);
                            if ($nbPoints > 0) {
                                // generate a file for this device if needed
                                if ($oneFilePerDevice) {
                                    $gpxHeader = $this->generateGpxHeader($name);
                                    // generate file name for this device
                                    $devFileName = str_replace(array('.gpx', '.GPX'), '_'.$devname.'.gpx',  $newFileName);
                                    if (! $dir->nodeExists($devFileName)) {
                                        $dir->newFile($devFileName);
                                    }
                                    $file = $dir->get($devFileName);
                                    $fd = $file->fopen('w');
                                    fwrite($fd, $gpxHeader);
                                }

                                $this->getAndWriteDevicePoints($devid, $devname, $filterSql, $fd, $nbPoints);

                                if ($oneFilePerDevice) {
                                    fwrite($fd, '</gpx>');
                                    fclose($fd);
                                    $file->touch();
                                }
                            }
                            else {
                                $warning = 2;
                            }
                        }
                        if (!$oneFilePerDevice) {
                            fwrite($fd, '</gpx>');
                            fclose($fd);
                            $file->touch();
                        }
                    }
                    else {
                        $warning = 1;
                    }
                    $done = true;
                }
            }
        }

        $response = new DataResponse(
            [
                'done'=>$done,
                'warning'=>$warning
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    private function getAndWriteDevicePoints($devid, $devname, $filterSql, $fd, $nbPoints) {
        $done = 0;

        $gpxText  = '<trk>' . "\n" . ' <name>' . $devname . '</name>' . "\n";
        $gpxText .= ' <trkseg>' . "\n";
        fwrite($fd, $gpxText);

        $chunkSize = 10000;
        $pointIndex = 0;

        while ($pointIndex < $nbPoints) {
            $gpxText = '';
            $sqlget = '
                SELECT *
                FROM *PREFIX*phonetrack_points
                WHERE deviceid='.$this->db_quote_escape_string($devid).' ';
            if ($filterSql !== '') {
                $sqlget .= 'AND '.$filterSql;
            }
            $sqlget .= ' ORDER BY timestamp ASC LIMIT '.$chunkSize.' OFFSET '.$pointIndex.' ;';
            $req = $this->dbconnection->prepare($sqlget);
            $req->execute();
            while ($row = $req->fetch()) {
                $epoch = $row['timestamp'];
                $date = '';
                if (is_numeric($epoch)) {
                    $epoch = intval($epoch);
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

                $gpxExtension = '';
                $gpxText .= '  <trkpt lat="'.$lat.'" lon="'.$lon.'">' . "\n";
                $gpxText .= '   <time>' . $date . '</time>' . "\n";
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
            // write the chunk !
            fwrite($fd, $gpxText);
            $pointIndex = $pointIndex + $chunkSize;
        }
        $gpxText  = ' </trkseg>' . "\n";
        $gpxText .= '</trk>' . "\n";
        fwrite($fd, $gpxText);

        return $done;
    }

    private function countDevicesPerSession($dbtoken) {
        $sqlget = '
            SELECT count(*) AS co
            FROM *PREFIX*phonetrack_devices
            WHERE sessionid='.$this->db_quote_escape_string($dbtoken).';';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        $nbDevices = 0;
        while ($row = $req->fetch()) {
            $nbDevices = intval($row['co']);
        }
        return $nbDevices;
    }

    private function countPointsPerSession($dbtoken, $filterSql) {
        $sqlget = '
            SELECT count(*) AS co
            FROM *PREFIX*phonetrack_devices AS dev, *PREFIX*phonetrack_points AS po
            WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' AND dev.id=po.deviceid ';
        if ($filterSql !== '') {
            $sqlget .= 'AND '.$filterSql;
        }
        $sqlget .= ' ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        $nbPoints = 0;
        while ($row = $req->fetch()) {
            $nbPoints = intval($row['co']);
        }
        return $nbPoints;
    }

    private function countPointsPerDevice($devid, $filterSql) {
        $sqlget = '
            SELECT count(*) AS co
            FROM *PREFIX*phonetrack_points
            WHERE deviceid='.$this->db_quote_escape_string($devid).' ';
        if ($filterSql !== '') {
            $sqlget .= 'AND '.$filterSql;
        }
        $sqlget .= ' ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        $nbPoints = 0;
        while ($row = $req->fetch()) {
            $nbPoints = intval($row['co']);
        }
        return $nbPoints;
    }

    private function getCurrentFilters($username='') {
        $userId = $this->userId;
        if ($username !== '') {
            $userId = $username;
        }
        $fArray = null;
        $f = array();
        $keys = $this->config->getUserKeys($userId, 'phonetrack');
        foreach ($keys as $key) {
            $value = $this->config->getUserValue($userId, 'phonetrack', $key);
            $f[$key] = $value;
        }
        if (array_key_exists('applyfilters', $f) and $f['applyfilters'] === 'true') {
            $fArray = array();
            if (array_key_exists('datemin', $f) and $f['datemin'] !== '') {
                $hourmin =   (array_key_exists('hourmin', $f)   and $f['hourmin']   !== '') ? intval($f['hourmin']) : 0;
                $minutemin = (array_key_exists('minutemin', $f) and $f['minutemin'] !== '') ? intval($f['minutemin']) : 0;
                $secondmin = (array_key_exists('secondmin', $f) and $f['secondmin'] !== '') ? intval($f['secondmin']) : 0;
                $fArray['tsmin'] = intval($f['datemin']) + 3600*$hourmin + 60*$minutemin + $secondmin;
            }
            else {
                if (    array_key_exists('hourmin', $f)   and $f['hourmin'] !== ''
                    and array_key_exists('minutemin', $f) and $f['minutemin'] !== ''
                    and array_key_exists('secondmin', $f) and $f['secondmin'] !== ''
                ) {
                    $dtz = ini_get('date.timezone');
                    if ($dtz === '') {
                        $dtz = 'UTC';
                    }
                    date_default_timezone_set($dtz);
                    $now = new \DateTime();
                    $y = $now->format('Y');
                    $m = $now->format('m');
                    $d = $now->format('d');
                    $h = intval($f['hourmin']);
                    $mi = intval($f['minutemin']);
                    $s = intval($f['secondmin']);
                    $dmin = new \DateTime($y.'-'.$m.'-'.$d.' '.$h.':'.$mi.':'.$s);
                    $fArray['tsmin'] = $dmin->getTimestamp();
                }
            }
            if (array_key_exists('datemax', $f) and $f['datemax'] !== '') {
                $hourmax =   (array_key_exists('hourmax', $f)   and $f['hourmax'] !== '')   ? intval($f['hourmax']) : 23;
                $minutemax = (array_key_exists('minutemax', $f) and $f['minutemax'] !== '') ? intval($f['minutemax']) : 59;
                $secondmax = (array_key_exists('secondmax', $f) and $f['secondmax'] !== '') ? intval($f['secondmax']) : 59;
                $fArray['tsmax'] = intval($f['datemax']) + 3600*$hourmax + 60*$minutemax + $secondmax;
            }
            else {
                if (    array_key_exists('hourmax', $f)   and $f['hourmax'] !== ''
                    and array_key_exists('minutemax', $f) and $f['minutemax'] !== ''
                    and array_key_exists('secondmax', $f) and $f['secondmax'] !== ''
                ) {
                    $dtz = ini_get('date.timezone');
                    if ($dtz === '') {
                        $dtz = 'UTC';
                    }
                    date_default_timezone_set($dtz);
                    $now = new \DateTime();
                    $y = $now->format('Y');
                    $m = $now->format('m');
                    $d = $now->format('d');
                    $h = intval($f['hourmax']);
                    $mi = intval($f['minutemax']);
                    $s = intval($f['secondmax']);
                    $dmax = new \DateTime($y.'-'.$m.'-'.$d.' '.$h.':'.$mi.':'.$s);
                    $fArray['tsmax'] = $dmax->getTimestamp();
                }
            }
            date_default_timezone_set('UTC');
            $lastTS = new \DateTime();
            $lastTS = $lastTS->getTimestamp();
            $lastTSset = false;
            if (array_key_exists('lastdays', $f) and $f['lastdays'] !== '') {
                $lastTS = $lastTS - 24*3600*intval($f['lastdays']);
                $lastTSset = true;
            }
            if (array_key_exists('lasthours', $f) and $f['lasthours'] !== '') {
                $lastTS = $lastTS - 3600*intval($f['lasthours']);
                $lastTSset = true;
            }
            if (array_key_exists('lastmins', $f) and $f['lastmins'] !== '') {
                $lastTS = $lastTS - 60*intval($f['lastmins']);
                $lastTSset = true;
            }
            if ($lastTSset and (!array_key_exists('tsmin', $fArray) or $lastTS > $fArray['tsmin'])) {
                $fArray['tsmin'] = $lastTS;
            }
            foreach (['elevationmin', 'elevationmax', 'accuracymin', 'accuracymax', 'satellitesmin', 'satellitesmax', 'batterymin', 'batterymax', 'speedmax', 'speedmin', 'bearingmax', 'bearingmin', 'lastdays', 'lasthours', 'lastmins'] as $k) {
                if (array_key_exists($k, $f) and $f[$k] !== '') {
                    $fArray[$k] = intval($f[$k]);
                }
            }
        }

        return $fArray;
    }

    private function filterPoint($p, $fArray) {
        return (
                (!array_key_exists('tsmin', $fArray) or intval($p['timestamp']) >= $fArray['tsmin'])
            and (!array_key_exists('tsmax', $fArray) or intval($p['timestamp']) <= $fArray['tsmax'])
            and (!array_key_exists('elevationmax', $fArray) or intval($p['altitude']) <= $fArray['elevationmax'])
            and (!array_key_exists('elevationmin', $fArray) or intval($p['altitude']) >= $fArray['elevationmin'])
            and (!array_key_exists('accuracymax', $fArray) or intval($p['accuracy']) <= $fArray['accuracymax'])
            and (!array_key_exists('accuracymin', $fArray) or intval($p['accuracy']) >= $fArray['accuracymin'])
            and (!array_key_exists('satellitesmax', $fArray) or intval($p['satellites']) <= $fArray['satellitesmax'])
            and (!array_key_exists('satellitesmin', $fArray) or intval($p['satellites']) >= $fArray['satellitesmin'])
            and (!array_key_exists('batterymax', $fArray) or intval($p['batterylevel']) <= $fArray['batterymax'])
            and (!array_key_exists('batterymin', $fArray) or intval($p['batterylevel']) >= $fArray['batterymin'])
            and (!array_key_exists('speedmax', $fArray) or floatval($p['speed']) <= $fArray['speedmax'])
            and (!array_key_exists('speedmin', $fArray) or floatval($p['speed']) >= $fArray['speedmin'])
            and (!array_key_exists('bearingmax', $fArray) or floatval($p['bearing']) <= $fArray['bearingmax'])
            and (!array_key_exists('bearingmin', $fArray) or floatval($p['bearing']) >= $fArray['bearingmin'])
        );
    }

    private function getSqlFilter($fArray) {
        $sql = '';
        if ($fArray !== null) {
            $cond = array();
            if (array_key_exists('tsmin', $fArray)) { array_push($cond, 'timestamp >= '.$this->db_quote_escape_string($fArray['tsmin'])); }
            if (array_key_exists('tsmax', $fArray)) { array_push($cond, 'timestamp <= '.$this->db_quote_escape_string($fArray['tsmax'])); }
            if (array_key_exists('elevationmax', $fArray)) { array_push($cond, 'altitude <= '.$this->db_quote_escape_string($fArray['elevationmax'])); }
            if (array_key_exists('elevationmin', $fArray)) { array_push($cond, 'altitude >= '.$this->db_quote_escape_string($fArray['elvationmin'])); }
            if (array_key_exists('accuracymax', $fArray)) { array_push($cond, 'accuracy <= '.$this->db_quote_escape_string($fArray['accuracymax'])); }
            if (array_key_exists('accuracymin', $fArray)) { array_push($cond, 'accuracy >= '.$this->db_quote_escape_string($fArray['accuracymin'])); }
            if (array_key_exists('satellitesmax', $fArray)) { array_push($cond, 'satellites <= '.$this->db_quote_escape_string($fArray['satellitesmax'])); }
            if (array_key_exists('satellitesmin', $fArray)) { array_push($cond, 'satellites >= '.$this->db_quote_escape_string($fArray['satellitesmin'])); }
            if (array_key_exists('batterymax', $fArray)) { array_push($cond, 'batterylevel <= '.$this->db_quote_escape_string($fArray['batterymax'])); }
            if (array_key_exists('batterymin', $fArray)) { array_push($cond, 'batterylevel >= '.$this->db_quote_escape_string($fArray['batterymin'])); }
            if (array_key_exists('speedmax', $fArray)) { array_push($cond, 'speed <= '.$this->db_quote_escape_string($fArray['speedmax'])); }
            if (array_key_exists('speedmin', $fArray)) { array_push($cond, 'speed >= '.$this->db_quote_escape_string($fArray['speedmin'])); }
            if (array_key_exists('bearingmax', $fArray)) { array_push($cond, 'bearing <= '.$this->db_quote_escape_string($fArray['bearingmax'])); }
            if (array_key_exists('bearingmin', $fArray)) { array_push($cond, 'bearing >= '.$this->db_quote_escape_string($fArray['bearingmin'])); }
            $sql = implode(' AND ', $cond);
        }
        return $sql;
    }

    private function generateGpxHeader($name, $nbdev=0) {
        date_default_timezone_set('UTC');
        $dt = new \DateTime();
        $date = $dt->format('Y-m-d\TH:i:s\Z');
        $gpxText = '<?xml version="1.0" encoding="UTF-8" standalone="no" ?>' . "\n";
        $gpxText .= '<gpx xmlns="http://www.topografix.com/GPX/1/1"' .
            ' xmlns:gpxx="http://www.garmin.com/xmlschemas/GpxExtensions/v3"' .
            ' xmlns:wptx1="http://www.garmin.com/xmlschemas/WaypointExtension/v1"' .
            ' xmlns:gpxtpx="http://www.garmin.com/xmlschemas/TrackPointExtension/v1"' .
            ' creator="PhoneTrack Nextcloud app ' .
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
        if ($nbdev > 0) {
            $gpxText .= ' <desc>' . $nbdev . ' device'.($nbdev > 1 ? 's' : '').'</desc>' . "\n";
        }
        $gpxText .= '</metadata>' . "\n";
        return $gpxText;
    }

    /**
     * @NoAdminRequired
     */
    public function addUserShare($token, $userId) {
        $ok = 0;
        // check if userId exists
        $userIds = [];
        foreach($this->userManager->search('') as $u) {
            if ($u->getUID() !== $this->userId) {
                array_push($userIds, $u->getUID());
            }
        }
        if ($userId !== '' and in_array($userId, $userIds)) {
            // check if session exists and owned by current user
            $sqlchk = '
                SELECT name, token
                FROM *PREFIX*phonetrack_sessions
                WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                      AND token='.$this->db_quote_escape_string($token).' ;';
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
                $sqlchk = '
                    SELECT username, sessionid
                    FROM *PREFIX*phonetrack_shares
                    WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                          AND username='.$this->db_quote_escape_string($userId).' ;';
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
                    $sql = '
                        INSERT INTO *PREFIX*phonetrack_shares
                        (sessionid, username, sharetoken)
                        VALUES ('.
                            $this->db_quote_escape_string($dbtoken).','.
                            $this->db_quote_escape_string($userId).','.
                            $this->db_quote_escape_string($sharetoken).
                        ') ;';
                    $req = $this->dbconnection->prepare($sql);
                    $req->execute();
                    $req->closeCursor();

                    $ok = 1;

                    // SEND NOTIFICATION
                    $manager = \OC::$server->getNotificationManager();
                    $notification = $manager->createNotification();

                    $acceptAction = $notification->createAction();
                    $acceptAction->setLabel('accept')
                        ->setLink('/apps/phonetrack', 'GET');

                    $declineAction = $notification->createAction();
                    $declineAction->setLabel('decline')
                        ->setLink('/apps/phonetrack', 'GET');

                    $notification->setApp('phonetrack')
                        ->setUser($userId)
                        ->setDateTime(new \DateTime())
                        ->setObject('addusershare', $dbtoken)
                        ->setSubject('add_user_share', [$this->userId, $dbname])
                        ->addAction($acceptAction)
                        ->addAction($declineAction)
                        ;

                    $manager->notify($notification);
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
    public function addPublicShare($token, $ignoreFilters=false) {
        $ok = 0;
        $filters = '';
        $sharetoken = '';
        // check if session exists and owned by current user
        $sqlchk = '
            SELECT name, token
            FROM *PREFIX*phonetrack_sessions
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND token='.$this->db_quote_escape_string($token).' ;';
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
            if (!$ignoreFilters) {
                $filterArray = $this->getCurrentFilters();
                if ($filterArray !== null) {
                    $filters = json_encode($filterArray);
                }
            }

            // determine share token
            $sharetoken = md5('share'.$this->userId.$dbname.rand());

            // insert
            $sql = '
                INSERT INTO *PREFIX*phonetrack_pubshares
                (sessionid, sharetoken, filters)
                VALUES ('.
                    $this->db_quote_escape_string($dbtoken).','.
                    $this->db_quote_escape_string($sharetoken).','.
                    $this->db_quote_escape_string($filters).
                ') ;';
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
    public function deleteUserShare($token, $userId) {
        $ok = 0;
        // check if session exists
        $sqlchk = '
            SELECT name, token
            FROM *PREFIX*phonetrack_sessions
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND token='.$this->db_quote_escape_string($token).' ;';
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
            $sqlchk = '
                SELECT username, sessionid
                FROM *PREFIX*phonetrack_shares
                WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                      AND username='.$this->db_quote_escape_string($userId).' ;';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbuserId = null;
            while ($row = $req->fetch()){
                $dbuserId = $row['username'];
                break;
            }
            $req->closeCursor();

            if ($dbuserId !== null) {
                // delete
                $sqldel = '
                    DELETE FROM *PREFIX*phonetrack_shares
                    WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                          AND username='.$this->db_quote_escape_string($userId).' ;';
                $req = $this->dbconnection->prepare($sqldel);
                $req->execute();
                $req->closeCursor();

                $ok = 1;

                // SEND NOTIFICATION
                $manager = \OC::$server->getNotificationManager();
                $notification = $manager->createNotification();

                $acceptAction = $notification->createAction();
                $acceptAction->setLabel('accept')
                    ->setLink('/apps/phonetrack', 'GET');

                $declineAction = $notification->createAction();
                $declineAction->setLabel('decline')
                    ->setLink('/apps/phonetrack', 'GET');

                $notification->setApp('phonetrack')
                    ->setUser($userId)
                    ->setDateTime(new \DateTime())
                    ->setObject('deleteusershare', $dbtoken)
                    ->setSubject('delete_user_share', [$this->userId, $dbname])
                    ->addAction($acceptAction)
                    ->addAction($declineAction)
                    ;

                $manager->notify($notification);
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
        $sqlchk = '
            SELECT name, token
            FROM *PREFIX*phonetrack_sessions
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND token='.$this->db_quote_escape_string($token).' ;';
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
            $sqlchk = '
                SELECT sharetoken, sessionid
                FROM *PREFIX*phonetrack_pubshares
                WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                      AND sharetoken='.$this->db_quote_escape_string($sharetoken).' ;';
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
                $sqldel = '
                    DELETE FROM *PREFIX*phonetrack_pubshares
                    WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                          AND sharetoken='.$this->db_quote_escape_string($dbsharetoken).' ;';
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
            $sqlchk = '
                SELECT name, token
                FROM *PREFIX*phonetrack_sessions
                WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                      AND token='.$this->db_quote_escape_string($token).' ;';
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
                $sqlchk = '
                    SELECT name, sessionid, nametoken
                    FROM *PREFIX*phonetrack_devices
                    WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                          AND name='.$this->db_quote_escape_string($devicename).' ;';
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
                    $sql = '
                        INSERT INTO *PREFIX*phonetrack_devices
                        (sessionid, name, nametoken)
                        VALUES ('.
                        $this->db_quote_escape_string($dbtoken).','.
                        $this->db_quote_escape_string($devicename).','.
                        $this->db_quote_escape_string($nametoken).
                        ') ;';
                    $req = $this->dbconnection->prepare($sql);
                    $req->execute();
                    $req->closeCursor();

                    $ok = 1;
                }
                // if there is an entry but no token, name is free to be reserved
                // so we update the entry
                else if ($dbdevicenametoken === '' or $dbdevicenametoken === null) {
                    $nametoken = md5('nametoken'.$this->userId.$dbdevicename.rand());
                    $sqlupd = '
                        UPDATE *PREFIX*phonetrack_devices
                        SET nametoken='.$this->db_quote_escape_string($nametoken).'
                        WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                              AND name='.$this->db_quote_escape_string($dbdevicename).' ;';
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
            $sqlchk = '
                SELECT name, token
                FROM *PREFIX*phonetrack_sessions
                WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                      AND token='.$this->db_quote_escape_string($token).' ;';
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
                $sqlchk = '
                    SELECT name, sessionid, nametoken
                    FROM *PREFIX*phonetrack_devices
                    WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                          AND name='.$this->db_quote_escape_string($devicename).' ;';
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
                    $sqlupd = '
                        UPDATE *PREFIX*phonetrack_devices
                        SET nametoken='.$this->db_quote_escape_string('').'
                        WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                              AND name='.$this->db_quote_escape_string($dbdevicename).' ;';
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
        $sqlchk = '
            SELECT name
            FROM *PREFIX*phonetrack_sessions
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($userid).'
                  AND token='.$this->db_quote_escape_string($token).' ;';
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
        $sqlchk = '
            SELECT name
            FROM *PREFIX*phonetrack_devices
            WHERE sessionid='.$this->db_quote_escape_string($token).'
                  AND id='.$this->db_quote_escape_string($devid).' ;';
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
    public function addFiltersBookmark($name, $filters) {
        $ok = 0;
        $bookid = null;
        // check there is no bookmark with this name already
        $sqlchk = '
            SELECT name
            FROM *PREFIX*phonetrack_filtersb
            WHERE name='.$this->db_quote_escape_string($name).'
                  AND username='.$this->db_quote_escape_string($this->userId).' ;';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbbookname = null;
        while ($row = $req->fetch()){
            $dbbookname = $row['name'];
            break;
        }
        $req->closeCursor();

        if ($dbbookname === null) {
            // insert
            $sql = '
                INSERT INTO *PREFIX*phonetrack_filtersb
                (username, name, filterjson)
                VALUES ('.
                     $this->db_quote_escape_string($this->userId).','.
                     $this->db_quote_escape_string($name).','.
                     $this->db_quote_escape_string($filters).'
                ) ;';
            $req = $this->dbconnection->prepare($sql);
            $req->execute();
            $req->closeCursor();

            $sqlchk = '
                SELECT id
                FROM *PREFIX*phonetrack_filtersb
                WHERE name='.$this->db_quote_escape_string($name).'
                      AND username='.$this->db_quote_escape_string($this->userId).' ;';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            while ($row = $req->fetch()){
                $bookid = $row['id'];
                break;
            }
            $req->closeCursor();

            $ok = 1;
        }
        else {
            $ok = 2;
        }

        $response = new DataResponse(
            [
                'done'=>$ok,
                'bookid'=>$bookid
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
    public function deleteFiltersBookmark($bookid) {
        $ok = 0;
        $sqldel = '
            DELETE FROM *PREFIX*phonetrack_filtersb
            WHERE id='.$this->db_quote_escape_string($bookid).'
                  AND username='.$this->db_quote_escape_string($this->userId).' ;';
        $req = $this->dbconnection->prepare($sqldel);
        $req->execute();
        $req->closeCursor();

        $ok = 1;

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

    private function getFiltersBookmarks() {
        $res = [];
        $sql = '
            SELECT id, username, name, filterjson
            FROM *PREFIX*phonetrack_filtersb
            WHERE username='.$this->db_quote_escape_string($this->userId).' ;';
        $req = $this->dbconnection->prepare($sql);
        $req->execute();
        while ($row = $req->fetch()){
            $bookid = $row['id'];
            $name = $row['name'];
            $filters = $row['filterjson'];
            $res[$bookid] = [$name, $filters];
        }
        $req->closeCursor();

        return $res;
    }

    /**
     * @NoAdminRequired
     */
    public function addGeofence($token, $device, $fencename, $latmin, $latmax, $lonmin, $lonmax,
                                $urlenter, $urlleave, $urlenterpost, $urlleavepost, $sendemail, $emailaddr, $sendnotif) {
        $ok = 0;
        $fenceid = null;
        if ($this->sessionExists($token, $this->userId) and $this->deviceExists($device, $token)) {
            // check there is no fence with this name already
            $sqlchk = '
                SELECT name
                FROM *PREFIX*phonetrack_geofences
                WHERE name='.$this->db_quote_escape_string($fencename).'
                      AND deviceid='.$this->db_quote_escape_string($device).' ;';
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
                $sql = '
                    INSERT INTO *PREFIX*phonetrack_geofences
                    (name, deviceid, latmin, latmax,
                     lonmin, lonmax, urlenter, urlleave,
                     urlenterpost, urlleavepost, sendemail, emailaddr, sendnotif)
                    VALUES ('.
                         $this->db_quote_escape_string($fencename).','.
                         $this->db_quote_escape_string($device).','.
                         $this->db_quote_escape_string(floatval($latmin)).','.
                         $this->db_quote_escape_string(floatval($latmax)).','.
                         $this->db_quote_escape_string(floatval($lonmin)).','.
                         $this->db_quote_escape_string(floatval($lonmax)).','.
                         $this->db_quote_escape_string($urlenter).','.
                         $this->db_quote_escape_string($urlleave).','.
                         $this->db_quote_escape_string(intval($urlenterpost)).','.
                         $this->db_quote_escape_string(intval($urlleavepost)).','.
                         $this->db_quote_escape_string(intval($sendemail)).','.
                         $this->db_quote_escape_string($emailaddr).','.
                         $this->db_quote_escape_string(intval($sendnotif)).'
                    ) ;';
                $req = $this->dbconnection->prepare($sql);
                $req->execute();
                $req->closeCursor();

                $sqlchk = '
                    SELECT id
                    FROM *PREFIX*phonetrack_geofences
                    WHERE name='.$this->db_quote_escape_string($fencename).'
                          AND deviceid='.$this->db_quote_escape_string($device).' ;';
                $req = $this->dbconnection->prepare($sqlchk);
                $req->execute();
                while ($row = $req->fetch()){
                    $fenceid = $row['id'];
                    break;
                }
                $req->closeCursor();

                $user = $this->userManager->get($this->userId);
                $userEmail = $user->getEMailAddress();
                if (!empty($userEmail)) {
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
    public function deleteGeofence($token, $device, $fenceid) {
        $ok = 0;
        if ($this->sessionExists($token, $this->userId) and $this->deviceExists($device, $token)) {
            $sqldel = '
                DELETE FROM *PREFIX*phonetrack_geofences
                WHERE deviceid='.$this->db_quote_escape_string($device).'
                      AND id='.$this->db_quote_escape_string($fenceid).' ;';
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
    public function addProxim($token, $device, $sid, $dname, $lowlimit, $highlimit,
                                $urlclose, $urlfar, $urlclosepost, $urlfarpost, $sendemail, $emailaddr, $sendnotif) {
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
                $sqlchk = '
                    SELECT id, sessionid, sharetoken
                    FROM *PREFIX*phonetrack_shares
                    WHERE username='.$this->db_quote_escape_string($this->userId).'
                          AND sharetoken='.$this->db_quote_escape_string($sid).' ;';
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
                $sqlchk = '
                    SELECT id
                    FROM *PREFIX*phonetrack_devices
                    WHERE name='.$this->db_quote_escape_string($dname).'
                          AND sessionid='.$this->db_quote_escape_string($targetSessionId).' ;';
                $req = $this->dbconnection->prepare($sqlchk);
                $req->execute();
                while ($row = $req->fetch()){
                    $targetDeviceId = $row['id'];
                    break;
                }
                $req->closeCursor();

                if ($targetDeviceId !== null) {
                    // insert
                    $sql = '
                        INSERT INTO *PREFIX*phonetrack_proxims
                        (deviceid1, deviceid2, lowlimit, highlimit, urlclose, urlfar,
                         urlclosepost, urlfarpost, sendemail, emailaddr, sendnotif)
                        VALUES ('.
                            $this->db_quote_escape_string($device).','.
                            $this->db_quote_escape_string($targetDeviceId).','.
                            $this->db_quote_escape_string(intval($lowlimit)).','.
                            $this->db_quote_escape_string(intval($highlimit)).','.
                            $this->db_quote_escape_string($urlclose).','.
                            $this->db_quote_escape_string($urlfar).','.
                            $this->db_quote_escape_string(intval($urlclosepost)).','.
                            $this->db_quote_escape_string(intval($urlfarpost)).','.
                            $this->db_quote_escape_string(intval($sendemail)).','.
                            $this->db_quote_escape_string($emailaddr).','.
                            $this->db_quote_escape_string(intval($sendnotif)).
                        ') ;';
                    $req = $this->dbconnection->prepare($sql);
                    $req->execute();
                    $req->closeCursor();

                    $sqlchk = '
                        SELECT MAX(id) as maxid
                        FROM *PREFIX*phonetrack_proxims
                        WHERE deviceid1='.$this->db_quote_escape_string($device).'
                              AND deviceid2='.$this->db_quote_escape_string($targetDeviceId).' ;';
                    $req = $this->dbconnection->prepare($sqlchk);
                    $req->execute();
                    while ($row = $req->fetch()){
                        $proximid = $row['maxid'];
                        break;
                    }
                    $req->closeCursor();

                    $user = $this->userManager->get($this->userId);
                    $userEmail = $user->getEMailAddress();
                    if (!empty($userEmail)) {
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
            $sqlchk = '
                SELECT id, deviceid1
                FROM *PREFIX*phonetrack_proxims
                WHERE id='.$this->db_quote_escape_string($proximid).'
                      AND deviceid1='.$this->db_quote_escape_string($device).' ;';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            while ($row = $req->fetch()){
                $dbproximid = $row['id'];
                break;
            }
            $req->closeCursor();

            if ($dbproximid !== null) {
                $sqldel = '
                    DELETE FROM *PREFIX*phonetrack_proxims
                    WHERE id='.$this->db_quote_escape_string($dbproximid).'
                          AND deviceid1='.$this->db_quote_escape_string($device).' ;';
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
    public function getUserList() {
        $userNames = [];
        foreach($this->userManager->search('') as $u) {
            if ($u->getUID() !== $this->userId) {
                //array_push($userNames, $u->getUID());
                $userNames[$u->getUID()] = $u->getDisplayName();
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
        $sqlchk = '
            SELECT name
            FROM *PREFIX*phonetrack_sessions
            WHERE token='.$this->db_quote_escape_string($token).' ;';
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
            $sqlgetres = '
                SELECT id, name
                FROM *PREFIX*phonetrack_devices
                WHERE sessionid='.$this->db_quote_escape_string($token).'
                      AND name='.$this->db_quote_escape_string($devicename).' ;';
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
                $sql = '
                    INSERT INTO *PREFIX*phonetrack_devices
                    (name, sessionid)
                    VALUES ('.
                        $this->db_quote_escape_string($devicename).','.
                        $this->db_quote_escape_string($token).
                    ') ;';
                $req = $this->dbconnection->prepare($sql);
                $req->execute();
                $req->closeCursor();

                // get the newly created device id
                $sqlgetdid = '
                    SELECT id
                    FROM *PREFIX*phonetrack_devices
                    WHERE sessionid='.$this->db_quote_escape_string($token).'
                          AND name='.$this->db_quote_escape_string($devicename).' ;';
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

            // insert by packets of 500
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

                $sql = '
                    INSERT INTO *PREFIX*phonetrack_points
                    (deviceid, lat, lon, timestamp,
                     accuracy, satellites, altitude, batterylevel,
                     useragent, speed, bearing)
                    VALUES '.$values.' ;';
                $req = $this->dbconnection->prepare($sql);
                $req->execute();
                $req->closeCursor();
            }

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

        $dirpath = $this->config->getUserValue($userId, 'phonetrack', 'autoexportpath', '/PhoneTrack_export');

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
        return $dir;
    }

    private function cronAutoPurge($limit_purge) {
        date_default_timezone_set('UTC');
        foreach (array('day'=>'1', 'week'=>'7', 'month'=>'31') as $s => $nbDays) {
            // get all sessions with this auto purge value
            $sessions = array();
            $sqlget = '
                SELECT token, autoexport FROM *PREFIX*phonetrack_sessions
                WHERE autopurge='.$this->db_quote_escape_string($s).' ;';
            $req = $this->dbconnection->prepare($sqlget);
            $req->execute();
            while ($row = $req->fetch()){
                $sessions[$row['token']] = $row['autoexport'];
            }
            $req->closeCursor();

            $devices = array();
            foreach ($sessions as $token => $autoexport) {
                $sqlget = '
                    SELECT id
                    FROM *PREFIX*phonetrack_devices
                    WHERE sessionid='.$this->db_quote_escape_string($token).' ;';
                $req = $this->dbconnection->prepare($sqlget);
                $req->execute();
                while ($row = $req->fetch()) {
                    if ($autoexport === 'daily') {
                        $ts = $limit_purge[0] - 24*3600*$nbDays;
                    }
                    elseif ($autoexport === 'weekly') {
                        $ts = $limit_purge[1] - 24*3600*$nbDays;
                    }
                    elseif ($autoexport === 'monthly' || $autoexport === 'no') {
                        // if autoexport === 'no', leave the data longer in the db
                        $ts = $limit_purge[2] - 24*3600*$nbDays;
                    }
                    $devices[$row['id']] = $ts;
                }
                $req->closeCursor();
            }

            foreach ($devices as $did => $ts) {
                $sqldel = '
                    DELETE FROM *PREFIX*phonetrack_points
                    WHERE deviceid='.$this->db_quote_escape_string($did).'
                          AND timestamp<'.$this->db_quote_escape_string($ts).' ;';
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
        $dtz = ini_get('date.timezone');
        if ($dtz === '') {
            $dtz = 'UTC';
        }
        date_default_timezone_set($dtz);

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
        $dailySuffix = '_'.$dateMaxDay->format('Y-m-d').'_daily';

        // current week
        $now = new \DateTime();
        $y = $now->format('Y');
        $m = $now->format('m');
        $d = $now->format('d');
        $dateWeekMax = new \DateTime($y.'-'.$m.'-'.$d);
        $maxWeekTimestamp = $dateWeekMax->getTimestamp();
        //now is Monday
        if (intval($now->format('N')) === 1) {
            $now->modify('-1 day');
        }
        while (intval($now->format('N')) !== 1) {
            $now->modify('-1 day');
        }
        $y = intval($now->format('Y'));
        $m = intval($now->format('m'));
        $d = intval($now->format('d'));
        $dateWeekMin =  new \DateTime($y.'-'.$m.'-'.$d);
        $minWeekTimestamp = $dateWeekMin->getTimestamp();
        $weeklySuffix = '_'.$dateWeekMin->format('Y-m-d').'_weekly';

        // current month
        $now = new \DateTime();
        $y = $now->format('Y');
        $m = $now->format('m');
        $d = $now->format('d');
        $dateMonthMax = new \DateTime($y.'-'.$m.'-'.$d);
        $maxMonthTimestamp = $dateMonthMax->getTimestamp();
        //now is first of month
        if (intval($now->format('d')) === 1) {
            $now->modify('-1 day');
        }
        while (intval($now->format('d')) !== 1) {
            $now->modify('-1 day');
        }
        $y = intval($now->format('Y'));
        $m = intval($now->format('m'));
        $d = intval($now->format('d'));
        $dateMonthMin = new \DateTime($y.'-'.$m.'-'.$d);
        $minMonthTimestamp = $dateMonthMin->getTimestamp();
        $monthlySuffix = '_'.$dateMonthMin->format('Y-m-d').'_monthly';

        $weekFilterArray = array();
        $weekFilterArray['tsmin'] = $minWeekTimestamp;
        $weekFilterArray['tsmax'] = $maxWeekTimestamp;
        $dayFilterArray = array();
        $dayFilterArray['tsmin'] = $minDayTimestamp;
        $dayFilterArray['tsmax'] = $maxDayTimestamp;
        $monthFilterArray = array();
        $monthFilterArray['tsmin'] = $minMonthTimestamp;
        $monthFilterArray['tsmax'] = $maxMonthTimestamp;

        //set upper limit for purge function
        $limit_purge = array($minDayTimestamp, $minWeekTimestamp, $minMonthTimestamp);

        date_default_timezone_set('UTC');
        foreach($this->userManager->search('') as $u) {
            $userName = $u->getUID();

            $sqlget = '
                SELECT name, token, autoexport
                FROM *PREFIX*phonetrack_sessions
                WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($userName).' ;';
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
        // to avoid deleting data before it has been potentially exported
        $this->cronAutoPurge($limit_purge);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function APIPing() {
        $response = new DataResponse(
            [$this->userId]
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
     * @NoCSRFRequired
     */
    public function APIgetLastPositionsPublic($sessionid) {
        $result = array();
        // check if session exists
        $dbtoken = null;
        $sqlget = '
            SELECT publicviewtoken, token
            FROM *PREFIX*phonetrack_sessions
            WHERE publicviewtoken='.$this->db_quote_escape_string($sessionid).'
                  AND public=1 ;';
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
            $sqldev = '
                SELECT id
                FROM *PREFIX*phonetrack_devices
                WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ;';
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
                $sqlname = '
                    SELECT name
                    FROM *PREFIX*phonetrack_devices
                    WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                          AND id='.$this->db_quote_escape_string($devid).' ;';
                $req = $this->dbconnection->prepare($sqlname);
                $req->execute();
                $col = '';
                while ($row = $req->fetch()){
                    $name = $row['name'];
                }
                $req->closeCursor();

                $entry = array();
                $sqlget = '
                    SELECT lat, lon, timestamp, batterylevel, useragent,
                           satellites, accuracy, altitude, speed, bearing
                    FROM *PREFIX*phonetrack_points
                    WHERE deviceid='.$this->db_quote_escape_string($devid).'
                    ORDER BY timestamp DESC LIMIT 1 ;';
                $req = $this->dbconnection->prepare($sqlget);
                $req->execute();
                while ($row = $req->fetch()){
                    $entry['useragent'] = $row['useragent'];
                    unset($row['useragent']);
                    foreach ($row as $k => $v) {
                        $entry[$k] = is_numeric($v) ? floatval($v) : null;
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

    /**
     * @NoAdminRequired
     * @PublicPage
     * @NoCSRFRequired
     */
    public function APIgetPositionsPublic($sessionid, $limit=null) {
        $result = array();
        // check if session exists
        $dbtoken = null;
        $sqlget = '
            SELECT publicviewtoken, token
            FROM *PREFIX*phonetrack_sessions
            WHERE publicviewtoken='.$this->db_quote_escape_string($sessionid).'
                  AND public=1 ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            $dbtoken = $row['token'];
            $dbpubtoken = $row['publicviewtoken'];
        }
        $req->closeCursor();

        $dbFilters = null;
        $dbDevicename = null;
        $dbLastPosOnly = null;
        $dbGeofencify = null;
        if ($dbtoken === null) {
            $sqlget = '
            SELECT sessionid, sharetoken, filters, devicename, lastposonly, geofencify
            FROM *PREFIX*phonetrack_pubshares
            WHERE sharetoken='.$this->db_quote_escape_string($sessionid).' ;';
            $req = $this->dbconnection->prepare($sqlget);
            $req->execute();
            while ($row = $req->fetch()){
                $dbtoken = $row['sessionid'];
                $dbpubtoken = $row['sharetoken'];
                $dbFilters = json_decode($row['filters'], True);
                $dbDevicename = $row['devicename'];
                $dbLastPosOnly = $row['lastposonly'];
                $dbGeofencify = $row['geofencify'];
            }
            $req->closeCursor();
        }

        // session exists
        if ($dbtoken !== null) {
            // get list of devices
            $devices = array();

            $deviceNameRestriction = '';
            if ($dbDevicename !== null and $dbDevicename !== '') {
                $deviceNameRestriction = ' AND name='.$this->db_quote_escape_string($dbDevicename).' ';
            }
            $sqldev = '
                SELECT id
                FROM *PREFIX*phonetrack_devices
                WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                '.$deviceNameRestriction.' ;';
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
                $color = null;
                $sqlname = '
                    SELECT name, color
                    FROM *PREFIX*phonetrack_devices
                    WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                          AND id='.$this->db_quote_escape_string($devid).' ;';
                $req = $this->dbconnection->prepare($sqlname);
                $req->execute();
                $col = '';
                while ($row = $req->fetch()){
                    $name = $row['name'];
                    $color = $row['color'];
                }
                $req->closeCursor();

                $entries = [];
                $sqlLimit = '';
                if (intval($dbLastPosOnly) === 1) {
                    $sqlLimit = 'LIMIT 1';
                }
                elseif (is_numeric($limit)) {
                    $sqlLimit = 'LIMIT '.intval($limit);
                }
                $sqlget = '
                    SELECT lat, lon, timestamp, batterylevel, useragent,
                           satellites, accuracy, altitude, speed, bearing
                    FROM *PREFIX*phonetrack_points
                    WHERE deviceid='.$this->db_quote_escape_string($devid).'
                    ORDER BY timestamp DESC '.$sqlLimit.' ;';
                $req = $this->dbconnection->prepare($sqlget);
                $req->execute();
                while ($row = $req->fetch()){
                    if ($dbFilters === null or $this->filterPoint($row, $dbFilters)) {
                        $entry = [];
                        $entry['useragent'] = $row['useragent'];
                        unset($row['useragent']);
                        foreach ($row as $k => $v) {
                            $entry[$k] = is_numeric($v) ? floatval($v) : null;
                        }
                        array_unshift($entries, $entry);
                    }
                }
                $req->closeCursor();
                if (count($entries) > 0) {
                    $result[$dbpubtoken][$name] = [
                        'color' => $color,
                        'points' => $entries
                    ];
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

    /**
     * get last positions of a user's session
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function APIgetLastPositionsUser($sessionid) {
        $result = array();
        // check if session exists
        $dbtoken = null;
        $sqlget = '
            SELECT token
            FROM *PREFIX*phonetrack_sessions
            WHERE token='.$this->db_quote_escape_string($sessionid).'
                  AND '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            $dbtoken = $row['token'];
        }
        $req->closeCursor();

        // check if session is shared with current user
        if ($dbtoken === null) {
            $sqlget = '
                SELECT sessionid
                FROM *PREFIX*phonetrack_shares
                WHERE sharetoken='.$this->db_quote_escape_string($sessionid).'
                      AND username='.$this->db_quote_escape_string($this->userId).' ;';
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
            $sqldev = '
                SELECT id
                FROM *PREFIX*phonetrack_devices
                WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ;';
            $req = $this->dbconnection->prepare($sqldev);
            $req->execute();
            while ($row = $req->fetch()){
                array_push($devices, $row['id']);
            }
            $req->closeCursor();

            // get the coords for each device
            $result[$sessionid] = array();

            foreach ($devices as $devid) {
                $name = null;
                $color = null;
                $sqlname = '
                    SELECT name, color
                    FROM *PREFIX*phonetrack_devices
                    WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                          AND id='.$this->db_quote_escape_string($devid).' ;';
                $req = $this->dbconnection->prepare($sqlname);
                $req->execute();
                $col = '';
                while ($row = $req->fetch()){
                    $name = $row['name'];
                    $color = $row['color'];
                }
                $req->closeCursor();

                $entry = array();
                $sqlget = '
                    SELECT lat, lon, timestamp, batterylevel, useragent,
                           satellites, accuracy, altitude, speed, bearing
                    FROM *PREFIX*phonetrack_points
                    WHERE deviceid='.$this->db_quote_escape_string($devid).'
                    ORDER BY timestamp DESC LIMIT 1 ;';
                $req = $this->dbconnection->prepare($sqlget);
                $req->execute();
                while ($row = $req->fetch()){
                    $entry['useragent'] = $row['useragent'];
                    unset($row['useragent']);
                    foreach ($row as $k => $v) {
                        $entry[$k] = is_numeric($v) ? floatval($v) : null;
                    }
                }
                $req->closeCursor();
                if (count($entry) > 0) {
                    $entry['color'] = $color;
                    $result[$sessionid][$name] = $entry;
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

    /**
     * get positions of a user's session
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function APIgetPositionsUser($sessionid, $limit=null) {
        $result = array();
        // check if session exists
        $dbtoken = null;
        $sqlget = '
            SELECT token
            FROM *PREFIX*phonetrack_sessions
            WHERE token='.$this->db_quote_escape_string($sessionid).'
                  AND '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            $dbtoken = $row['token'];
        }
        $req->closeCursor();

        // check if session is shared with current user
        if ($dbtoken === null) {
            $sqlget = '
                SELECT sessionid
                FROM *PREFIX*phonetrack_shares
                WHERE sharetoken='.$this->db_quote_escape_string($sessionid).'
                      AND username='.$this->db_quote_escape_string($this->userId).' ;';
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
            $sqldev = '
                SELECT id
                FROM *PREFIX*phonetrack_devices
                WHERE sessionid='.$this->db_quote_escape_string($dbtoken).' ;';
            $req = $this->dbconnection->prepare($sqldev);
            $req->execute();
            while ($row = $req->fetch()){
                array_push($devices, $row['id']);
            }
            $req->closeCursor();

            // get the coords for each device
            $result[$sessionid] = array();

            foreach ($devices as $devid) {
                $name = null;
                $color = null;
                $sqlname = '
                    SELECT name, color
                    FROM *PREFIX*phonetrack_devices
                    WHERE sessionid='.$this->db_quote_escape_string($dbtoken).'
                          AND id='.$this->db_quote_escape_string($devid).' ;';
                $req = $this->dbconnection->prepare($sqlname);
                $req->execute();
                $col = '';
                while ($row = $req->fetch()){
                    $name = $row['name'];
                    $color = $row['color'];
                }
                $req->closeCursor();

                $entries = [];
                $sqlLimit = '';
                if (is_numeric($limit)) {
                    $sqlLimit = 'LIMIT '.intval($limit);
                }
                $sqlget = '
                    SELECT lat, lon, timestamp, batterylevel, useragent,
                           satellites, accuracy, altitude, speed, bearing
                    FROM *PREFIX*phonetrack_points
                    WHERE deviceid='.$this->db_quote_escape_string($devid).'
                    ORDER BY timestamp DESC '.$sqlLimit.' ;';
                $req = $this->dbconnection->prepare($sqlget);
                $req->execute();
                while ($row = $req->fetch()){
                    $entry = [];
                    $entry['useragent'] = $row['useragent'];
                    unset($row['useragent']);
                    foreach ($row as $k => $v) {
                        $entry[$k] = is_numeric($v) ? floatval($v) : null;
                    }
                    array_unshift($entries, $entry);
                }
                $req->closeCursor();
                if (count($entries) > 0) {
                    $result[$sessionid][$name] = [
                        'color' => $color,
                        'points' => $entries
                    ];
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

    /**
     * check if there already is a public share restricted on that device
     * if not => add it
     * returns the share token
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function APIshareDevice($sessionid, $devicename) {
        $result = array('code'=>0, 'sharetoken'=>'', 'done'=>0);
        // check if session exists and is owned by current user
        $sqlchk = '
            SELECT token
            FROM *PREFIX*phonetrack_sessions
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND token='.$this->db_quote_escape_string($sessionid).' ;';
        $req = $this->dbconnection->prepare($sqlchk);
        $req->execute();
        $dbtoken = null;
        while ($row = $req->fetch()){
            $dbtoken = $row['token'];
            break;
        }
        $req->closeCursor();

        if ($dbtoken !== null) {
            $dbsharetoken = null;
            $sqlget = '
                SELECT sharetoken
                FROM *PREFIX*phonetrack_pubshares
                WHERE sessionid='.$this->db_quote_escape_string($sessionid).'
                      AND devicename='.$this->db_quote_escape_string($devicename).' ;';
            $req = $this->dbconnection->prepare($sqlget);
            $req->execute();
            while ($row = $req->fetch()){
                $dbsharetoken = $row['sharetoken'];
            }
            $req->closeCursor();

            // public share exists
            if ($dbsharetoken !== null) {
                $result['sharetoken'] = $dbsharetoken;
                $result['code'] = 1;
                $result['done'] = 1;
            }
            else {
                // let's create the public share without filters
                $resp = $this->addPublicShare($dbtoken, true);
                $data = $resp->getData();
                $done = $data['done'];
                $sharetoken = $data['sharetoken'];
                if ($done === 1) {
                    $resp2 = $this->setPublicShareDevice($dbtoken, $sharetoken, $devicename);
                    $data2 = $resp2->getData();
                    $done2 = $data2['done'];
                    if ($done2 === 1) {
                        $result['sharetoken'] = $sharetoken;
                        $result['done'] = 1;
                    }
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
