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
use \OCP\IL10N;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\IServerContainer;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\DB\QueryBuilder\IQueryBuilder;

use OCA\PhoneTrack\Service\SessionService;
use OCA\PhoneTrack\Db\SessionMapper;
use OCA\PhoneTrack\Db\DeviceMapper;
use OCA\PhoneTrack\Activity\ActivityManager;

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

    public function __construct($AppName,
                                IRequest $request,
                                IServerContainer $serverContainer,
                                IConfig $config,
                                IManager $shareManager,
                                IAppManager $appManager,
                                IUserManager $userManager,
                                ILogger $logger,
                                IL10N $trans,
                                ActivityManager $activityManager,
                                SessionMapper $sessionMapper,
                                DeviceMapper $deviceMapper,
				SessionService $sessionService,
                                $UserId
                                ){
        parent::__construct($AppName, $request);
        $this->logger = $logger;
        $this->appName = $AppName;
        $this->trans = $trans;
        $this->activityManager = $activityManager;
        $this->sessionMapper = $sessionMapper;
        $this->sessionService = $sessionService;
        $this->deviceMapper = $deviceMapper;
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
        if ($UserId !== null and $UserId !== '' and $serverContainer !== null){
            // path of user files folder relative to DATA folder
            $this->userfolder = $serverContainer->getUserFolder($UserId);
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
            SELECT servername, type, url, layers, token,
                   version, format, opacity, transparent,
                   minzoom, maxzoom, attribution
            FROM *PREFIX*phonetrack_tileserver
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
            AND type='.$this->db_quote_escape_string($type).';';
        $req = $this->dbconnection->prepare($sqlts);
        $req->execute();
        $tss = [];
        while ($row = $req->fetch()){
            $tss[$row["servername"]] = [];
            foreach (['servername', 'type', 'url', 'token', 'layers', 'version', 'format',
                      'opacity', 'transparent', 'minzoom', 'maxzoom', 'attribution'] as $field) {
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
        $mbtss = $this->getUserTileServers('mapboxtile');
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
            'usermapboxtileservers'=>$mbtss,
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
        $response->addHeader("Access-Control-Allow-Origin", "*");
        $csp = new ContentSecurityPolicy();
        $csp->allowInlineScript()
        ->allowEvalScript()
        ->allowInlineStyle()
        ->addAllowedScriptDomain('*')
        ->addAllowedStyleDomain('*')
        ->addAllowedFontDomain('*')
        ->addAllowedImageDomain('*')
        ->addAllowedConnectDomain('*')
        ->addAllowedMediaDomain('*')
        ->addAllowedObjectDomain('*')
        ->addAllowedFrameDomain('*')
        ->addAllowedChildSrcDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    private function getReservedNames($token) {
        $result = [];

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
                array_push($result, ['token'=>$dbnametoken, 'name'=>$dbdevicename]);
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
        $sessions = [];
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
            array_push($sessions, [
                $dbname, $dbtoken, $dbpublicviewtoken, $devices, $dbpublic,
                $sharedWith, $reservedNames, $publicShares, $dbautoexport, $dbautopurge, $dblocked
            ]);
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
            array_push($sessions, [$dbname, $dbsharetoken, $userNameDisplay, $devices]);
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
        $sessions = [];
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
            array_push($sessions, [
                $dbname, $dbtoken, $dbpublicviewtoken, $devices, $dbpublic, $sharedWith,
                $reservedNames, $publicShares, $dbautoexport, $dbautopurge
            ]);
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
            array_push($sessions, [$dbname, $dbsharetoken, $dbpublicviewtoken, $devices, $dbpublic, $dbuser]);
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
        $devices = [];
        $sqlget = '
            SELECT id, name, alias, color, nametoken, shape
            FROM *PREFIX*phonetrack_devices
            WHERE sessionid='.$this->db_quote_escape_string($sessionid).'
            ORDER BY LOWER(name) ASC ;';
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
                [
                    'token'=>$row['sharetoken'],
                    'filters'=>$row['filters'],
                    'devicename'=>$row['devicename'],
                    'lastposonly'=>$row['lastposonly'],
                    'geofencify'=>$row['geofencify']
                ]
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
            $dids = [];
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
                    $escapedPointIds = [];
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
        $result = [];
        $colors = [];
        $shapes = [];
        $names = [];
        $aliases = [];
        $geofences = [];
        $proxims = [];
        // manage sql optim filters (time only)
        $fArray = $this->sessionService->getCurrentFilters($this->userId);
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
                        $devices = [];
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
                        $result[$token] = [];

                        foreach ($devices as $devid) {
                            $resultDevArray = [];

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
                                    $shapes[$token] = [];
                                }
                                $shapes[$token][$devid] = $shape;
                                if (!array_key_exists($token, $colors)) {
                                    $colors[$token] = [];
                                }
                                $colors[$token][$devid] = $col;
                                if (!array_key_exists($token, $names)) {
                                    $names[$token] = [];
                                }
                                $names[$token][$devid] = $name;
                                if (!array_key_exists($token, $aliases)) {
                                    $aliases[$token] = [];
                                }
                                $aliases[$token][$devid] = $alias;
                                // geofences
                                if (!array_key_exists($token, $geofences)) {
                                    $geofences[$token] = [];
                                }
                                if (!array_key_exists($devid, $geofences[$token])) {
                                    $geofences[$token][$devid] = [];
                                }
                                $geofences[$token][$devid] = $this->getGeofences($devid);
                                // proxims
                                if (!array_key_exists($token, $proxims)) {
                                    $proxims[$token] = [];
                                }
                                if (!array_key_exists($devid, $proxims[$token])) {
                                    $proxims[$token][$devid] = [];
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
                                $entry = [
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
                                ];
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
        $geofences = [];
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
            $fence = [];
            foreach ($row as $k => $v) {
                $fence[$k] = $v;
            }
            array_push($geofences, $fence);
        }
        $req->closeCursor();
        return $geofences;
    }

    private function getProxims($devid) {
        $proxims = [];
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
            $proxim = [];
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
        $result = [];
        $colors = [];
        $shapes = [];
        $names = [];
        $aliases = [];
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
                    $devices = [];
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
                    $result[$token] = [];

                    foreach ($devices as $devid) {
                        $resultDevArray = [];

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
                                $shapes[$dbtoken] = [];
                            }
                            $shapes[$dbtoken][$devid] = $shape;
                            if (!array_key_exists($dbtoken, $colors)) {
                                $colors[$dbtoken] = [];
                            }
                            $colors[$dbtoken][$devid] = $col;
                            if (!array_key_exists($dbtoken, $names)) {
                                $names[$dbtoken] = [];
                            }
                            $names[$dbtoken][$devid] = $name;
                            if (!array_key_exists($dbtoken, $aliases)) {
                                $aliases[$dbtoken] = [];
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
                            $entry = [
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
                            ];
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
        $result = [];
        $colors = [];
        $shapes = [];
        $names = [];
        $aliases = [];
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
                $devices = [];
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
                $result[$dbpublicviewtoken] = [];

                foreach ($devices as $devid) {
                    $resultDevArray = [];

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
                            $shapes[$dbpublicviewtoken] = [];
                        }
                        $shapes[$dbpublicviewtoken][$devid] = $shape;
                        if (!array_key_exists($dbpublicviewtoken, $colors)) {
                            $colors[$dbpublicviewtoken] = [];
                        }
                        $colors[$dbpublicviewtoken][$devid] = $col;
                        if (!array_key_exists($dbpublicviewtoken, $names)) {
                            $names[$dbpublicviewtoken] = [];
                        }
                        $names[$dbpublicviewtoken][$devid] = $name;
                        if (!array_key_exists($dbpublicviewtoken, $aliases)) {
                            $aliases[$dbpublicviewtoken] = [];
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
                            $entry = [
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
                            ];
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
        $fences = [];
        $sqlget = '
            SELECT latmin, lonmin, latmax, lonmax, name
            FROM *PREFIX*phonetrack_geofences
            WHERE deviceid='.$this->db_quote_escape_string($devid).' ;';
        $req = $this->dbconnection->prepare($sqlget);
        $req->execute();
        while ($row = $req->fetch()){
            $lat = (floatval($row['latmin']) + floatval($row['latmax'])) / 2;
            $lon = (floatval($row['lonmin']) + floatval($row['lonmax'])) / 2;
            $fences[$row['name']] = [
                $lat, $lon, floatval($row['latmin']), floatval($row['latmax']),
                floatval($row['lonmin']), floatval($row['lonmax'])
            ];
        }
        return $fences;
    }

    private function geofencify($token, $ptk, $devtab) {
        $result = [];
        if (count($devtab) > 0) {
            foreach ($devtab as $devid => $entries) {
                $geofencesCenter = $this->getDeviceFencesCenter($devid);
                if (count($geofencesCenter) > 0) {
                    $result[$devid] = [];
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
            return [
                $entry[0], $geofencesCenter[$nearestName][0], $geofencesCenter[$nearestName][1],
                $entry[3], null, null, null, null, null, null, null
            ];
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
                $response = $this->publicWebLog($dbtoken, '');
                if (!is_string($response)) {
                    $response->setHeaderDetails($this->trans->t('Watch session'));
                }
                return $response;
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
                    $response = $this->publicWebLog($dbtoken, '', $lastposonly, $filters);
                    if (!is_string($response)) {
                        $response->setHeaderDetails($this->trans->t('Watch session'));
                    }
                    return $response;
                }
                else {
                    return 'Session does not exist or is not public';
                }
            }
        }
        else {
            return 'Session does not exist or is not public';
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
                SELECT name, public
                FROM *PREFIX*phonetrack_sessions
                WHERE token='.$this->db_quote_escape_string($token).' ;';
            $req = $this->dbconnection->prepare($sqlchk);
            $req->execute();
            $dbname = null;
            $dbpublic = null;
            while ($row = $req->fetch()){
                $dbname = $row['name'];
                $dbpublic = $row['public'];
                break;
            }
            $req->closeCursor();

            if ($dbname !== null and intval($dbpublic) === 1) {
            }
            else {
                return 'Session does not exist or is not public';
            }
        }
        else {
            return 'Session does not exist or is not public';
        }

        require_once('tileservers.php');
        if (!isset($baseTileServers) ) {
            $baseTileServers = '';
        }
        $params = [
            'username'=>'',
            'basetileservers'=>$baseTileServers,
            'usertileservers'=>[],
            'usermapboxtileservers'=>[],
            'useroverlayservers'=>[],
            'usertileserverswms'=>[],
            'useroverlayserverswms'=>[],
            'publicsessionname'=>$dbname,
            'lastposonly'=>$lastposonly,
            'sharefilters'=>$filters,
            'filtersBookmarks'=>[],
            'phonetrack_version'=>$this->appVersion
        ];
        $response = new PublicTemplateResponse('phonetrack', 'main', $params);
        $response->setHeaderTitle($this->trans->t('PhoneTrack public access'));
        $response->setHeaderDetails($this->trans->t('Log to session %s', [$dbname]));
        $response->setFooterVisible(false);
        $response->setHeaders(['X-Frame-Options'=>'']);
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
        $cleanpath = str_replace(['../', '..\\'], '',  $path);

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
                else if (endswith($file->getName(), '.json') or endswith($file->getName(), '.JSON')) {
                    $sessionName = str_replace(['.json', '.JSON'], '', $file->getName());
                    $res = $this->createSession($sessionName);
                    $response = $res->getData();
                    if ($response['done'] === 1) {
                        $token = $response['token'];
                        $publicviewtoken = $response['publicviewtoken'];
                        $done = $this->readJsonImportPoints($file, $file->getName(), $token);
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
            $this->currentPointList = [];
        }
        else if ($name === 'TRKPT') {
            $this->currentPoint = [null, null, null, $this->pointIndex, null, null,  null, null, null, null];
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
                $this->currentPointList = [];
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
                    ['app' => $this->appName]
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
            $this->currentPointList = [];
        }
        else if ($name === 'WHEN') {
            $this->currentPoint = [null, null, null, $this->pointIndex, null, null,  null, null, null, null];
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
                $this->currentPointList = [];
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
                    ['app' => $this->appName]
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

    private function readJsonImportPoints($json_file, $json_name, $token) {
        $importDevName = 'importedDevice';
        $jsonArray = json_decode($json_file->getContent(), true);

        $currentPointList = [];
        if (array_key_exists('locations', $jsonArray) and is_array($jsonArray['locations'])) {
            foreach ($jsonArray['locations'] as $loc) {
                // get point info
                //$points, array($lat, $lon, $ele, $timestamp, $acc, $bat, $sat, $ua, $speed, $bearing)
                $point = [null, null, null, null, null, null,  null, null, null, null];
                if (array_key_exists('timestampMs', $loc) and is_numeric($loc['timestampMs'])
                    and array_key_exists('latitude', $loc) and is_numeric($loc['latitude'])
                    and array_key_exists('longitude', $loc) and is_numeric($loc['longitude']))
                {
                    $point[0] = $loc['latitude'];
                    $point[1] = $loc['longitude'];
                    $ts = intval(intval($loc['timestampMs']) / 1000);
                    $point[3] = $ts;
                    if (array_key_exists('latitude', $loc) and is_numeric($loc['latitude'])) {
                        $point[4] = $loc['accuracy'];
                    }
                }
                // add point
                array_push($currentPointList, $point);
                if (count($currentPointList) >= 500) {
                    $this->logMultiple($token, $importDevName, $currentPointList);
                    unset($currentPointList);
                    $currentPointList = [];
                }
            }
            if (count($currentPointList) > 0) {
                $this->logMultiple($token, $importDevName, $currentPointList);
            }
        }

        return 1;
    }

    /**
     * @NoAdminRequired
     */
    public function export($name, $token, $target, $username='', $filterArray=null) {
	$warning = 0;
	$done = false;
        if ($this->userId !== null and $this->userId !== '') {
            $userId = $this->userId;
	    $doneAndWarning = $this->sessionService->export($name, $token, $target, $userId, $filterArray);
	    $done = $doneAndWarning[0];
	    $warning = $doneAndWarning[1];
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

                    // activity
                    $sessionObj = $this->sessionMapper->findByToken($dbtoken);
                    $this->activityManager->triggerEvent(
                        ActivityManager::PHONETRACK_OBJECT_SESSION, $sessionObj,
                        ActivityManager::SUBJECT_SESSION_SHARE,
                        ['who'=>$userId, 'type'=>'u']
                    );

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
                $filterArray = $this->sessionService->getCurrentFilters($this->userId);
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
                // activity
                $sessionObj = $this->sessionMapper->findByToken($dbtoken);
                $this->activityManager->triggerEvent(
                    ActivityManager::PHONETRACK_OBJECT_SESSION, $sessionObj,
                    ActivityManager::SUBJECT_SESSION_UNSHARE,
                    ['who'=>$userId, 'type'=>'u']
                );

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
        try {
            foreach($this->userManager->search('') as $u) {
                if ($u->getUID() !== $this->userId) {
                    //array_push($userNames, $u->getUID());
                    $userNames[$u->getUID()] = $u->getDisplayName();
                }
            }
        }
        catch (\Throwable $t) {
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

            $valuesStrings = [];
            foreach ($points as $point) {
                // correct timestamp if needed
                $time = $point[3];
                if (is_numeric($time)) {
                    $time = floatval($time);
                    if ($time > 10000000000.0) {
                        $time = $time / 1000;
                    }
                }

                $lat        = $this->db_quote_escape_string(number_format($point[0], 8, '.', ''));
                $lon        = $this->db_quote_escape_string(number_format($point[1], 8, '.', ''));
                $alt        = is_numeric($point[2]) ? $this->db_quote_escape_string(number_format($point[2], 2, '.', '')) : 'NULL';
                $time       = is_numeric($time) ? $this->db_quote_escape_string(number_format($time, 0, '.', '')) : 'NULL';
                $acc        = is_numeric($point[4]) ? $this->db_quote_escape_string(number_format($point[4], 2, '.', '')) : 'NULL';
                $bat        = is_numeric($point[5]) ? $this->db_quote_escape_string(number_format($point[5], 2, '.', '')) : 'NULL';
                $sat        = is_numeric($point[6]) ? $this->db_quote_escape_string(number_format($point[6], 0, '.', '')) : 'NULL';
                $speed      = is_numeric($point[8]) ? $this->db_quote_escape_string(number_format($point[8], 3, '.', '')) : 'NULL';
                $bearing    = is_numeric($point[9]) ? $this->db_quote_escape_string(number_format($point[9], 2, '.', '')) : 'NULL';
                $useragent  = $point[7];

                $oneVal = '(';
                $oneVal .= $this->db_quote_escape_string($dbdeviceid).',';
                $oneVal .= $lat.',';
                $oneVal .= $lon.',';
                $oneVal .= $time.',';
                $oneVal .= $acc.',';
                $oneVal .= $sat.',';
                $oneVal .= $alt.',';
                $oneVal .= $bat.',';
                $oneVal .= $this->db_quote_escape_string($useragent).',';
                $oneVal .= $speed.',';
                $oneVal .= $bearing.') ';

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
        $result = [];
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
            $devices = [];
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
            $result[$dbpubtoken] = [];

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

                $entry = [];
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
        $result = [];
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
            $devices = [];

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
            $result[$dbpubtoken] = [];

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
        $result = [];
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
            $devices = [];
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
            $result[$sessionid] = [];

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

                $entry = [];
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
        $result = [];
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
            $devices = [];
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
            $result[$sessionid] = [];

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
        $result = ['code'=>0, 'sharetoken'=>'', 'done'=>0];
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
